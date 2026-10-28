# Performance Audit — 2026-10-26

Scope: Laravel backend (controllers, `app/Support`, MCP tools), database schema, SQLite configuration and the Vite/React bundle. Commit audited: `b550427`.

## Summary

The app is in good shape for its current size (2 orchards, 40 trees, under 100 rows per record table). Eager loading is used consistently, the records pages are paginated, and the frontend is code-split per page. There are no outright N+1 bugs on list pages.

The risks are about **growth over seasons**, not today's load:

1. Almost no indexes on foreign keys or date columns, and every date filter is wrapped in `strftime()`, so SQLite scans whole tables.
2. Some per-orchard and per-season loops run queries that grow by one or more for every orchard or season.
3. SQLite is the store for sessions, cache and the queue, but runs in its default rollback-journal mode with no busy timeout.

| # | Finding | Impact | Effort |
|---|---------|--------|--------|
| 1 | Missing indexes on FK, date and `deleted_at` columns | High (grows with data) | Low |
| 2 | `whereDate()` on `date` columns prevents index use | High (grows with data) | Medium |
| 3 | SQLite not in WAL mode, no `busy_timeout` | Medium (lock errors under concurrent writes) | Low |
| 4 | Overview: 7 queries per orchard + duplicated issue queries | Medium | Medium |
| 5 | Tree page: loads the whole tree history, 2 queries per season | Medium | Low |
| 6 | Orchard page: duplicate tree/issue queries, unbounded sales | Low–Medium | Low |
| 7 | Care records page reloads all trees and open issues on every page change | Low | Low |
| 8 | Unbounded lists: expenses page, trash hydrates up to 1,400 rows | Low | Low |
| 9 | `preventLazyLoading()` not enabled | Low (guard rail) | Trivial |
| 10 | Photos streamed through PHP | Low | Medium |

---

## 1. Missing indexes (High)

`foreignId()->constrained()` creates a foreign key but **no index** on SQLite (MySQL adds one automatically; SQLite and Postgres do not). The only indexes on farm tables are:

- `trees (orchard_id, code)` unique
- `care_record_tree (care_record_id, tree_id)` unique
- `care_record_tree_issue (care_record_id, tree_issue_id)` unique
- `tree_issues (resolved_on, follow_up_on)`
- `observations (tree_id, observed_on)`

Verified with `EXPLAIN QUERY PLAN`:

```
SELECT * FROM harvests WHERE tree_id = 1 AND deleted_at IS NULL
→ SCAN harvests

SELECT * FROM care_records WHERE … ORDER BY performed_on DESC, id DESC LIMIT 50
→ SCAN care_records + USE TEMP B-TREE FOR ORDER BY
```

Columns that are filtered, joined or sorted on with no supporting index:

| Table | Column(s) | Used by |
|-------|-----------|---------|
| `harvests` | `tree_id`, `harvested_on` | tree timeline, season yields, orchard yields, records page |
| `care_records` | `orchard_id`, `performed_on` | orchard page, overview `latestCare`, records page |
| `care_record_tree` | `tree_id` | tree timeline (`$tree->careRecords()`) |
| `care_record_tree_issue` | `tree_issue_id` | `GetTreatmentOutcomes` (`whereHas('careRecords')`) |
| `sales` | `orchard_id`, `sold_on` | orchard page, season totals |
| `expenses` | `season_id`, `spent_on` | season totals, expenses page |
| `tree_issues` | `tree_id` | every issue summary (`whereIn('tree_id', …)`) |
| `observation_photos` | `observation_id` | tree timeline |
| `seasons` | `orchard_id` | `currentSeason`, season lists |
| `trees` | `removed_on` | `active()` scope (partially covered by the unique index when `orchard_id` is given) |
| all soft-deleted tables | `deleted_at`, `created_via` | every query (soft-delete scope), `RecentAiRecords` |

**Recommendation:** one new migration adding composite indexes that match the access paths, for example:

```php
$table->index(['tree_id', 'harvested_on']);      // harvests
$table->index(['orchard_id', 'performed_on']);   // care_records
$table->index('tree_id');                        // care_record_tree, tree_issues
$table->index('tree_issue_id');                  // care_record_tree_issue
$table->index(['orchard_id', 'sold_on']);        // sales
$table->index(['season_id', 'spent_on']);        // expenses
$table->index('observation_id');                 // observation_photos
$table->index(['orchard_id', 'ended_on']);       // seasons
```

Plus a plain index on each table's date column for the unfiltered records pages (`ORDER BY <date> DESC, id DESC`). Only worth it after finding #2 is fixed, otherwise the range filters still cannot use them.

## 2. `whereDate()` defeats indexes (High)

The `date` cast stores values as `2026-08-24 00:00:00`, which is why the code uses `whereDate()` everywhere. On SQLite, `whereDate()` compiles to `strftime('%Y-%m-%d', column) >= ?`, which wraps the column in a function, so no index can ever be used for the date range.

Affected:

- `app/Models/Season.php:108` — `applyDateWindow()`, used by orchard yields, season page, overview totals, tree season yields, records season filter
- `app/Support/RecordFilters.php:46-47` — `from` / `to` filters
- `app/Models/Season.php:87-89` — `scopeCovering()`
- `app/Mcp/Tools/ListRecords.php` — `from` / `to`
- `app/Http/Controllers/Farm/OrchardController.php` — `whereRaw('date(harvests.harvested_on) >= date(seasons.started_on)')`

**Recommendation (pick one):**

- **A. Compare as plain ranges.** Because every stored value is midnight, `where(col, '>=', 'Y-m-d')` and `where(col, '<', nextDay)` give the same results and are index-friendly. Change `applyDateWindow()` and `RecordFilters::apply()` in one place each.
- **B. Store dates as `Y-m-d`.** Cast with `'date:Y-m-d'` and set `$dateFormat` or a custom cast so writes store only the date, then migrate existing rows. Cleaner long-term, but touches every model and needs a data migration.

A is the smaller change and enough on its own.

## 3. SQLite concurrency settings (Medium)

`config/database.php:35-45` leaves `journal_mode`, `busy_timeout` and `synchronous` as `null`. `.env.example` also puts sessions, cache and the queue on the `database` driver, so every request writes to the same SQLite file (session touch, cache, jobs).

In rollback-journal mode, a writer blocks readers, and with no busy timeout a concurrent writer fails at once with `database is locked`. This is most likely when an MCP client writes several records quickly while someone uses the app.

**Recommendation:**

```php
'busy_timeout' => 5000,
'journal_mode' => 'wal',
'synchronous' => 'normal',
```

Optionally move `CACHE_STORE` to `file` (or Redis if available) so cache writes stop competing for the database lock.

## 4. Overview page query count (Medium)

`OverviewController::index` → `OrchardSummary::all()` (`app/Support/OrchardSummary.php:21`) runs, **per orchard**:

- `TreeLayout::forOrchard()` — 2 queries (trees, open issues)
- `seasonTotals()` — 3 aggregate queries (harvests, sales, expenses)
- `latestCare()` ×2 — 2 queries

That is 1 + 7×N. On top of that:

- `IssueSummary::dueForFarm()` and `openForFarm()` run the same open-issue query twice (4 queries each with eager loads), though due issues are a subset of open ones and each summary already carries `isDue`.
- `RecentAiRecords::latest()` runs 6 model queries plus their eager loads (~18 queries) to show 5 rows.

With 2 orchards that is roughly 45 queries per overview load. Fine today, but it grows linearly with orchards, and every query is a table scan (see #1, #2).

**Recommendations:**

- Derive `dueIssues` from `openIssues` in PHP (`array_filter(..., fn ($i) => $i['isDue'])`), as `GetFarmOverview` already does. Saves 4 queries. The same applies to `OrchardController::show` (lines 99-100).
- Batch `TreeLayout` across orchards: load all active trees and all open issues once and group by `orchard_id`.
- Batch `latestCare` with one grouped query per care type, or one window-function query.
- Optionally defer `recentAiRecords` with `Inertia::defer()` and a skeleton, since it is the least important block on the page.

## 5. Tree page (Medium)

`app/Support/TreeTimeline.php:25-87` loads **every** care record, harvest and observation of the tree (with photos), builds arrays for all of them, then keeps 50 (`->take($limit)`). After a few years of weekly care across a row, a single tree page hydrates hundreds of models. `photoUrls()` also signs a URL for every photo of every observation, including the ones dropped by `take()`.

**Recommendation:** add `->orderByDesc(<date>)->orderByDesc('id')->limit($limit)` to each of the three queries before merging. The merged top 50 is still correct, because none of the top 50 can come from below position 50 in its own list.

`app/Support/TreeStats.php:41-46` (`seasonYields`) runs 2 `SUM` queries **per season** (N+1 that grows every year). Replace with one grouped query joining `seasons` on the date window, like `OrchardController::index` already does for current-season yields.

## 6. Orchard page (Low–Medium)

`OrchardController::show`:

- Trees are queried three times: `trees` (line 83), `activeTrees` (line 101) and inside `TreeLayout::forOrchard()` (line 87). When `show_removed` is off, `activeTrees` is the same set as `trees`; derive it in PHP.
- Open issues are queried three times: `dueIssues`, `openIssues` and inside `TreeLayout`. See #4.
- `sales` (line 102) loads every sale the orchard has ever had, with no limit. This grows each season. Limit it to the current season, or paginate.

## 7. Care records page reloads form options (Low)

`RecordController::index` (line 55) adds `careEditOptions()` on every care page, including page 2, 3… and every filter change: all active trees plus all open issues with eager loads. `RecordFilters::options()` (orchards, all seasons with orchard, members) likewise reloads on every visit.

**Recommendation:** `Inertia::once()` for these props so they are sent once per visit and reused on pagination and filter changes. Alternatively `Inertia::optional()` and load them when the edit dialog opens.

## 8. Unbounded lists (Low)

- `ExpenseController::index` loads all matching expenses with no pagination, because totals are computed from the same collection. Compute totals with a grouped `SUM` query and paginate the rows like the other records pages.
- `TrashController::index` (lines 18-41) loads up to 200 rows from each of 7 tables (1,400 models plus relations) to show 200. Acceptable as is. It could fetch ids and `deleted_at` with a `UNION`, then hydrate only the top 200.

## 9. Lazy-loading guard (Low)

`Model::preventLazyLoading()` is not enabled. The code relies on careful `with()` calls; one forgotten relation in a `map()` would silently add a query per row. `TreeStats::latestStage()` already lazy-loads `$tree->orchard->currentSeason`. That is harmless (one query), but this is the kind of thing the guard catches.

**Recommendation:** in `AppServiceProvider::configureDefaults()`:

```php
Model::preventLazyLoading(! app()->isProduction());
```

then run the test suite and fix anything it reports.

## 10. Photo delivery (Low)

The `photos` disk (`config/filesystems.php:63`) is local with `'serve' => true`, so each photo is streamed by a PHP worker through a signed route. A tree page with many observation photos ties up workers for every image. This is fine at this scale. If photo volume grows, serve them through the web server (e.g. `X-Sendfile` / `X-Accel-Redirect`) or move them to object storage with signed URLs.

---

## Frontend

- Pages are code-split by Inertia/Vite (61 chunks in the last build); the shared `app` chunk is ~180 KB on disk (minified, before gzip), CSS ~91 KB. No heavy dependencies (no chart or date libraries). React Compiler is on.
- Navigation links use `prefetch`. Good.
- `HandleInertiaRequests::share()` sends the full `User` model on every response. It is small today; restrict it to the fields the UI uses if columns are added.
- Largest pages: `farm/orchards/show.tsx` (1,061 lines) and `farm/trees/show.tsx` (635 lines). Their cost is dominated by prop size (#5, #6), not rendering.

No frontend changes are urgent.

## Deployment checklist

Not visible in the repo, so worth confirming on the server:

- `php artisan optimize` (config, route, event and view cache) on deploy
- OPcache enabled, with `validate_timestamps=0` in production
- `APP_DEBUG=false`
- A queue worker running (`QUEUE_CONNECTION=database`)

## Suggested order

1. #3 SQLite WAL + busy timeout (config only)
2. #2 option A, then #1 indexes (one migration)
3. #5 timeline limits + season yields grouping
4. #4 / #6 deduplicate issue and tree queries
5. #9 enable `preventLazyLoading` in non-production
6. #7, #8 as the data grows

---

## Status — 2026-10-26

| # | Status | Change |
|---|--------|--------|
| 1 | Fixed | Migration `2026_10_26_080746_add_performance_indexes_to_farm_tables`. `deleted_at` indexes were tried and dropped: without `ANALYZE` stats SQLite picked them over the date index for the records pages. |
| 2 | Fixed | `App\Support\DateFilter` (option A) replaces `whereDate()` in `Season`, `TreeIssue::scopeDue`, `RecordFilters`, `ListRecords`; joins compare columns directly. |
| 3 | Fixed | `config/database.php`: WAL, `busy_timeout` 5000, `synchronous` normal (overridable via `DB_JOURNAL_MODE`, `DB_BUSY_TIMEOUT`, `DB_SYNCHRONOUS`). Cache store left on `database`. |
| 4 | Fixed | Overview runs a fixed number of queries (`TreeLayout::forOrchards`, grouped season totals, one window-function query for latest care); due issues derived from open issues; recent AI records deferred with a skeleton. |
| 5 | Fixed | Timeline limits each kind before merging; `TreeStats::seasonYields` is one grouped query. |
| 6 | Fixed | Orchard page loads trees and open issues once; sales list capped at 20 with totals over all sales and a link to the records page. |
| 7 | Fixed | Option props are lazy closures; filter and page changes send `except` for them (partial reload). |
| 8 | Fixed | Expenses page paginated (50 per page) with totals from grouped `SUM`; trash reads ids first and hydrates only the 200 shown. |
| 9 | Fixed | `Model::preventLazyLoading()` outside production. The suite found no lazy loads. |
| 10 | Fixed (2026-10-27) | Laravel still checks the signed URL, then answers with `X-Accel-Redirect` (`PHOTOS_ACCEL_REDIRECT=true` in `compose.yaml`); `docker/Caddyfile` intercepts it and FrankenPHP's `file_server` sends the file. Verified in the built image: correct bytes and `Content-Type`, range requests (206), unsigned and `../` URLs refused. |

Deployment checklist, checked against `Dockerfile`, `docker/` and `compose.yaml`: `php artisan optimize` runs in the entrypoint, OPcache runs with `validate_timestamps=0`, and `APP_DEBUG` is `false`. No queued jobs exist in the app, so no queue worker is needed.
