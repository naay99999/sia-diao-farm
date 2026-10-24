# Desktop Navigation, Overview and Records Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** A farm-wide overview page with a tree map per orchard, a records section (tabbed tables, filters, trash) for checking what was recorded, Claude connection and findings under settings, and a desktop-first menu.

**Architecture:** Backend `App\Support` classes compute the tree layout, orchard summaries, record filters and record restore so controllers stay thin and MCP shares the restore rule. New Inertia pages `overview`, `records/*` and `settings/ai`/`settings/findings`; the tree map is a standalone `TreeMap` component that only consumes layout data, so its drawing can be replaced later.

**Tech Stack:** Laravel 13, PHP 8.5, Inertia v3 + React 19, Tailwind v4, shadcn/ui, Wayfinder, Pest 4.

**Spec:** `docs/superpowers/specs/2026-10-24-desktop-navigation-overview-records-design.md` (read it; it is in Thai).

## Global Constraints

- All user-facing text in Thai, including `aria-label` and `sr-only`. Code identifiers in English.
- No new Composer or npm dependencies. Adding a shadcn component file with `npx shadcn@latest add table` is allowed (it only writes `resources/js/components/ui/table.tsx`).
- UI follows `DESIGN.md`: desktop first, every page readable at 390px, tables become rows/cards below `md`, no horizontal page scroll, sizes `text-lg md:text-base`, `h-12 md:h-9`.
- Reuse `Heading`, `SectionTabs`, `EmptyState`, `Badge`, `RowActions`, `useConfirm`, `PageContainer`, and the existing record forms (`CareRecordFormBody`, `HarvestFields`, `SaleFields`, `ObservationEditForm`).
- Frontend routes via Wayfinder imports (`@/routes/...`, `@/actions/...`); run `php artisan wayfinder:generate` after adding routes if the Vite plugin is not running.
- Old links keep working: `/expenses`, `/orchards`, `/t/{tree}`, `/settings/ai`.
- Every PHP change has a Pest feature test. Before each commit touching PHP run `vendor/bin/pint --dirty --format agent`. Frontend tasks finish with `npm run types:check && npm run lint:check && npm run build`.
- Overview queries are constant per orchard, never per tree (spec anti-goal: no N+1).

## Review Focus

1. **Tree codes that do not parse** (`โคนหลัง`, `12`, `A-`) must land in the "อื่นๆ" row, not crash or vanish → Task 1 test `unparseable codes go to the other row`.
2. **Orchard with no open season** shows "no season" instead of zeros from an arbitrary window → Task 2 test `orchard without an open season has a null season`.
3. **Editing a record from the records page returns to the same filtered records URL**, not the tree page → Task 7 test `updating from the records page returns to it`.
4. **Restoring an open issue next to another open one** is refused in the app exactly as in MCP → Task 5 test `app restore refuses an open issue beside another open one`.
5. **Deleting a finding used only by soft-deleted observations** is refused (the FK is `restrictOnDelete`, trashed rows still reference it) → Task 11 test `finding used by a trashed observation cannot be deleted`.

---

### Task 1: Tree layout from tree codes

**Files:**
- Create: `app/Support/TreeLayout.php`
- Test: `tests/Feature/Farm/TreeLayoutTest.php`

**Interfaces:**
- Produces:
  - `TreeLayout::parseCode(string $code): ?array{row: string, position: int}`
  - `TreeLayout::forOrchard(Orchard $orchard): array<int, array{row: string|null, trees: array<int, array{id: int, code: string, position: int|null, status: 'due'|'issue'|'normal'}>}>` — `row` null is the "อื่นๆ" row.
  - `TreeLayout::statusCounts(array $layout): array{due: int, issue: int, normal: int}`

- [ ] **Step 1: Write the failing tests**

```php
test('codes parse into row and position', function (string $code, ?array $expected) {
    expect(TreeLayout::parseCode($code))->toBe($expected);
})->with([
    ['A-05', ['row' => 'A', 'position' => 5]],
    ['a5', ['row' => 'A', 'position' => 5]],
    ['AB-12', ['row' => 'AB', 'position' => 12]],
    ['โคนหลัง', null],
    ['12', null],
    ['A-', null],
]);

test('layout groups active trees by row in order and marks status', function () {
    // orchard with trees B-02, A-10, A-02, AA-01, removed A-03, and 'โคนหลัง'
    // A-02 has an open issue due yesterday, A-10 an open issue due next week
    // expect rows ['A', 'B', 'AA', null]; row A trees ['A-02','A-10'] with statuses ['due','issue']
    // removed A-03 absent; null row holds 'โคนหลัง' with position null
});

test('unparseable codes go to the other row', function () { /* only 'โคนหลัง' and '12' → one row, row null, sorted by code */ });

test('layout runs a constant number of queries', function () {
    // 30 trees vs 3 trees: DB::enableQueryLog(), count queries for forOrchard; counts equal
});
```

- [ ] **Step 2: Run** `php artisan test --compact tests/Feature/Farm/TreeLayoutTest.php` — expect FAIL (class missing).
- [ ] **Step 3: Implement `TreeLayout`.** Regex `/^([A-Za-z]+)-?(\d+)$/u`, row uppercased, position `(int)`. Status from one `TreeIssue::query()->open()` query over the orchard's active tree ids: any issue with `follow_up_on <= today` → `due`, else any open → `issue`, else `normal`. Rows sort by row length then alphabetically; trees by position then code; null row last, sorted by code. Skip `removed_on` trees (`Tree::active()`).
- [ ] **Step 4: Run** the test file — expect PASS.
- [ ] **Step 5: Commit** `feat: lay out an orchard's trees in rows from their codes`.

### Task 2: Orchard summaries and farm-wide issues

**Files:**
- Create: `app/Support/OrchardSummary.php`
- Modify: `app/Support/IssueSummary.php` (add farm-wide methods)
- Test: `tests/Feature/Farm/OrchardSummaryTest.php`

**Interfaces:**
- Consumes: `TreeLayout::forOrchard`, `TreeLayout::statusCounts`.
- Produces:
  - `OrchardSummary::all(): array<int, array{id: int, name: string, treeCount: int, statusCounts: array{due:int, issue:int, normal:int}, layout: array (TreeLayout shape), season: array{id:int, name:string, startedOn:string, yieldKg:float, salesTotal:float, expensesTotal:float}|null, latestFertilizer: array{date:string, product:string|null}|null, latestPesticide: array{date:string, product:string|null}|null}>` ordered by orchard name.
  - `IssueSummary::openForFarm(): array` and `IssueSummary::dueForFarm(): array` — same shape as `forIssue` plus `orchardId: int, orchardName: string`.

- [ ] **Step 1: Write the failing tests**

```php
test('summary carries season totals for the open season', function () {
    // open season 2026-01-01..; harvests 12.5 kg in window + 3 kg before it; sale 4000 in window;
    // expense 900 with season_id; expect season yieldKg 12.5, salesTotal 4000.0, expensesTotal 900.0
});
test('orchard without an open season has a null season', function () { /* only closed season → season null */ });
test('latest fertilizer and pesticide are the newest of each type', function () {
    // fertilizer 'ปุ๋ยคอก' 2026-09-01, fertilizer '15-15-15' 2026-10-01, pesticide 'อะบาเม็กติน' 2026-10-10, soft-deleted pesticide 2026-10-20
    // expect latestFertilizer ['date' => '2026-10-01', 'product' => '15-15-15'], latestPesticide date '2026-10-10'
});
test('farm-wide due issues span orchards and name the orchard', function () { /* two orchards, one due each → 2 rows with orchardName */ });
```

- [ ] **Step 2: Run** the test file — expect FAIL.
- [ ] **Step 3: Implement.** Season totals use the same queries as `SeasonController::show` (`applyDateWindow` on harvests joined to trees, on `orchard->sales()`, and `season->expenses()->sum('amount')`); a later refactor may share them, do not change `SeasonController` here. Latest care: one query per orchard per type on `careRecords()` ordered `performed_on desc, id desc`. `IssueSummary` farm methods use the existing private query without the orchard filter, eager-loading `tree.orchard:id,name`.
- [ ] **Step 4: Run** — expect PASS.
- [ ] **Step 5: Commit** `feat: summarise each orchard and list issues across the farm`.

### Task 3: Overview page backend and home redirect

**Files:**
- Create: `app/Http/Controllers/Farm/OverviewController.php`
- Modify: `routes/web.php` (`/` → `overview`), `app/Http/Controllers/Farm/OrchardController.php` (`show` adds `treeLayout`)
- Test: `tests/Feature/Farm/OverviewControllerTest.php`, update `tests/Feature/Farm/OrchardControllerTest.php`

**Interfaces:**
- Consumes: `OrchardSummary::all()`, `IssueSummary::dueForFarm()`, `IssueSummary::openForFarm()`, `RecentAiRecords::latest()`, `TreeLayout::forOrchard()`.
- Produces: route `GET /overview` named `overview` rendering `overview` with props `orchards`, `dueIssues`, `openIssues`, `recentAiRecords`; `orchards.show` gains prop `treeLayout`.

- [ ] **Step 1: Write the failing tests**: `home redirects signed-in users to the overview` (`get('/')->assertRedirect(route('overview'))`), `guests are sent to login`, `overview renders orchards and farm-wide issues` (`assertInertia` component `overview`, `has('orchards', 2)`, `has('dueIssues', 1)`), `orchard page carries its tree layout` (`has('treeLayout.0.trees')`).
- [ ] **Step 2: Run** — expect FAIL.
- [ ] **Step 3: Implement** the invokable-style `index(): Response` and wire routes inside the `auth, verified` group.
- [ ] **Step 4: Run** both test files — expect PASS.
- [ ] **Step 5: Commit** `feat: overview page backend and make it the home page`.

### Task 4: Overview page and tree map UI

**Files:**
- Create: `resources/js/pages/overview.tsx`, `resources/js/components/farm/tree-map.tsx`, `resources/js/components/farm/orchard-summary-card.tsx`
- Modify: `resources/js/types/farm.ts` (types `TreeMapRow`, `TreeMapTree`, `TreeStatus`, `OrchardOverview`, `FarmIssueSummary`), `resources/js/pages/farm/orchards/show.tsx` (render `TreeMap` above the tabs)

**Interfaces:**
- Consumes: Task 3 props.
- Produces: `<TreeMap rows={TreeMapRow[]} />` — draws only; no fetching. Each tree is a link to `trees.show` with `aria-label` like `ต้น A-05 ถึงวันดูซ้ำ`.

- [ ] **Step 1: Build `TreeMap`.** Plain SVG grid: one line per row with the row label at the start ("อื่นๆ" for null), circles at `position` (gaps where positions are missing), status colours from tokens only — `due` = `destructive`, `issue` = `chart-3`, `normal` = `primary`; legend with counts in text. Scrolls inside its own box on narrow screens, never the page.
- [ ] **Step 2: Build `overview.tsx`.** Desktop: two columns from `lg` — left a card per orchard (`OrchardSummaryCard`: name link, map, status counts, season numbers or "ยังไม่มีรอบที่เปิดอยู่", latest fertilizer/pesticide with date and product); right (sticky, per `DESIGN.md`) "ต้องดูซ้ำ", "ปัญหาค้าง" (each row: orchard · tree code · finding, links to tree), and recent Claude records. `EmptyState` when there are no orchards.
- [ ] **Step 3: Add `TreeMap`** to the orchard page.
- [ ] **Step 4: Verify** `npm run types:check && npm run lint:check && npm run build` — all succeed; open `/overview` at 1280px and 390px with seeded data and check no horizontal page scroll.
- [ ] **Step 5: Commit** `feat: overview page with a tree map per orchard`.

### Task 5: One restore rule for app and MCP

**Files:**
- Create: `app/Support/RecordRestorer.php`
- Modify: `app/Mcp/Tools/RestoreRecord.php` (use it; keep its English messages)
- Test: `tests/Feature/Farm/RecordRestorerTest.php`; existing `tests/Feature/Mcp/NotesAndDeletionTest.php` must still pass unchanged

**Interfaces:**
- Produces:
  - `RecordRestorer::conflict(Model $record): ?RestoreConflict` where `enum App\Enums\RestoreConflict: string { case NotDeleted = 'not_deleted'; case OpenIssueExists = 'open_issue_exists'; }` (create the enum in `app/Enums/RestoreConflict.php`).
  - `RecordRestorer::restore(Model $record): void` — calls `conflict` first and throws `LogicException` if one exists.

- [ ] **Step 1: Write the failing tests**: `conflict is null for a deleted record`, `not deleted records report NotDeleted`, `app restore refuses an open issue beside another open one` (two open issues same tree+finding, one trashed → `OpenIssueExists`), `restore brings the record back`.
- [ ] **Step 2: Run** — expect FAIL.
- [ ] **Step 3: Implement**; move `hasOtherOpenIssue` from `RestoreRecord` into `RecordRestorer`; `RestoreRecord` maps each `RestoreConflict` case to its current message.
- [ ] **Step 4: Run** `php artisan test --compact tests/Feature/Farm/RecordRestorerTest.php tests/Feature/Mcp` — expect PASS.
- [ ] **Step 5: Commit** `refactor: share the record restore rule between app and MCP`.

### Task 6: Record filters and records index backend

**Files:**
- Create: `app/Support/RecordFilters.php`, `app/Http/Requests/Farm/RecordFilterRequest.php`, `app/Http/Controllers/Farm/RecordController.php`
- Modify: `routes/web.php`, `app/Http/Controllers/Farm/ExpenseController.php` (index moves to `records/expenses`, uses `RecordFilters`)
- Test: `tests/Feature/Farm/RecordControllerTest.php`, update `tests/Feature/Farm/ExpenseControllerTest.php`

**Interfaces:**
- Produces:
  - `RecordFilterRequest` validates `orchard_id` (exists), `season_id` (exists), `from`, `to` (dates, `to` after_or_equal `from`), `created_by` (exists users), `via` (`in:app,mcp`).
  - `RecordFilters::apply(Builder $query, array $filters, string $dateColumn, ?string $orchardColumn, ?string $orchardRelation = null): Builder` — `orchardColumn` for direct `orchard_id`, `orchardRelation` (`tree`, `season`) for `whereHas`. `season_id` applies that season's orchard plus `Season::applyDateWindow` on `$dateColumn` (for expenses: `where('season_id', ...)` instead).
  - Route `GET /records/{type}` named `records.index`, `type` constrained to `care|observations|harvests|sales`; `GET /records/expenses` named `expenses.index` (path change only, name kept); `GET /expenses` named `expenses.legacy` redirecting to `expenses.index` with the same query string; `GET /records` redirects to `records.index` `care`.
  - Props for `records/index`: `type`, `records` (`paginate(50)->withQueryString()`), `filters`, `orchards` `[{id, name}]`, `seasons` `[{id, label}]`, `members` `[{id, name}]`. Every row carries `fromAi`, `aiBy`, `createdBy` (name or null), plus the fields its edit form needs (same keys `TreeTimeline` passes as `record`) and `orchard`/`tree` `{id, name|code}`. Observation rows add `issueStatus: 'open'|'resolved'|null`.

- [ ] **Step 1: Write the failing tests**:

```php
test('records index lists each type newest first', fn (string $type) => /* 3 rows, assertInertia has('records.data', 3) */)
    ->with(['care', 'observations', 'harvests', 'sales']);
test('records filter by orchard, dates, who and via', function () {
    // care in orchard A by member via mcp on 2026-10-01; care in orchard B by owner via app on 2026-09-01
    // ?orchard_id=A → 1; ?via=mcp → 1; ?created_by=owner → 1; ?from=2026-09-15 → 1
});
test('season filter keeps the season orchard and window', function () { /* season A 2026-01-01..2026-06-30; care A 2026-03-01, A 2026-08-01, B 2026-03-01 → 1 */ });
test('expenses filter by orchard through their season and drop season-less ones', function () {});
test('unknown record type is not found', fn () => /* get('/records/bogus')->assertNotFound() */);
test('old expenses link keeps its query', fn () => /* get('/expenses?from=2026-01-01')->assertRedirect(route('expenses.index', ['from' => '2026-01-01'])) */);
test('records index paginates at 50', function () { /* 51 harvests → records.data 50, records.last_page 2 */ });
```

- [ ] **Step 2: Run** — expect FAIL.
- [ ] **Step 3: Implement.** Per type: care (`performed_on`, `orchard_id`), observations (`observed_on`, relation `tree`, with `tree.orchard`, `finding`, `issue`), harvests (`harvested_on`, relation `tree`), sales (`sold_on`, `orchard_id`), expenses (`spent_on`, relation `season`). Eager-load `creator:id,name`, `updater:id,name` to avoid N+1. Expenses keep `get()` and totals by category, now over the filtered query.
- [ ] **Step 4: Run** both test files — expect PASS.
- [ ] **Step 5: Commit** `feat: filtered records index for every record type`.

### Task 7: Return to the records page after edit or delete

**Files:**
- Create: `app/Support/ReturnTo.php`
- Modify: `update`/`destroy` in `CareRecordController`, `HarvestController`, `ObservationController`, `SaleController`
- Test: `tests/Feature/Farm/ReturnToRecordsTest.php`; existing controller tests must pass unchanged

**Interfaces:**
- Produces: `ReturnTo::recordsOr(Request $request, RedirectResponse $default): RedirectResponse` — if the `Referer` is same-host and its path starts with `/records/`, redirect to that full URL (query kept); otherwise return `$default`.

- [ ] **Step 1: Write the failing tests**: `updating from the records page returns to it` (dataset over the four types; `->from(route('records.index', ['type' => 'care', 'orchard_id' => $id]))->patch(...)->assertRedirect(that url)`), `deleting from the records page returns to it`, `a foreign referer falls back to the usual page`.
- [ ] **Step 2: Run** — expect FAIL.
- [ ] **Step 3: Implement** and wrap each existing `to_route(...)` return in `ReturnTo::recordsOr($request, ...)`.
- [ ] **Step 4: Run** `php artisan test --compact tests/Feature/Farm` — expect PASS.
- [ ] **Step 5: Commit** `feat: edits from the records page return to it`.

### Task 8: Trash backend

**Files:**
- Create: `app/Support/RecordSummary.php`, `app/Http/Controllers/Farm/TrashController.php`
- Modify: `app/Support/RecentAiRecords.php` (build rows with `RecordSummary`), `routes/web.php`
- Test: `tests/Feature/Farm/TrashControllerTest.php`; `tests/Feature/AssistantTest.php` must still pass

**Interfaces:**
- Consumes: `RecordTypes::DELETABLE`, `RecordTypes::findWithTrashed`, `RecordRestorer`.
- Produces:
  - `RecordSummary::for(Model $record): array{kind: string, title: string, subject: string, href: string|null}` — Thai `kind` labels as in `RecentAiRecords` today plus `ปัญหา` (issue) and `โน้ต` (note).
  - `GET /records/trash` named `records.trash` rendering `records/trash` with `items: array<int, {type: string, id: int, kind, title, subject, deletedAt: string}>` — newest deleted first, at most 200 across all types.
  - `PATCH /records/trash/{type}/{id}` named `records.trash.restore`, `type` constrained to `RecordTypes::DELETABLE`. Conflicts become validation errors on `id`: NotDeleted → `บันทึกนี้ไม่ได้อยู่ในถังขยะ`, OpenIssueExists → `ต้นนี้มีปัญหาเดียวกันที่ยังเปิดอยู่ ให้บันทึกการดูซ้ำในเรื่องเดิมแทน`. Success toast `กู้คืนแล้ว`, redirect back to `records.trash`.

- [ ] **Step 1: Write the failing tests**: `trash lists deleted records of every type` (one trashed per type → 7 items, live records absent), `restoring brings the record back`, `restoring an open issue beside another open one shows the Thai error`, `unknown trash type is not found`.
- [ ] **Step 2: Run** — expect FAIL.
- [ ] **Step 3: Implement.**
- [ ] **Step 4: Run** the trash and assistant tests — expect PASS.
- [ ] **Step 5: Commit** `feat: trash page backend with restore`.

### Task 9: Records and trash UI

**Files:**
- Create: `resources/js/layouts/records-layout.tsx`, `resources/js/pages/records/index.tsx`, `resources/js/pages/records/trash.tsx`, `resources/js/components/farm/record-filters.tsx`, `resources/js/components/farm/ai-badge.tsx`, `resources/js/components/ui/table.tsx` (via `npx shadcn@latest add table`)
- Move: `resources/js/pages/farm/expenses/index.tsx` → `resources/js/pages/records/expenses.tsx` (update `ExpenseController` render name)
- Modify: `resources/js/pages/farm/trees/show.tsx` (use `AiBadge` instead of its inline badge), `resources/js/types/farm.ts`, `DESIGN.md` (add a "Tables" component section: desktop `Table`, below `md` rows per "Lists and Rows")

**Interfaces:**
- Consumes: Task 6 and 8 props, `ReturnTo` behaviour.
- Produces: `<AiBadge by={string|null} />`; `<RecordFilters filters orchards seasons members types />` that updates the URL query with `router.get(..., { preserveState: true, preserveScroll: true })`.

- [ ] **Step 1: `RecordsLayout`** — `Heading` "บันทึก", link tabs (การดูแล, สภาพต้น/ปัญหา, ผลผลิต, การขาย, ค่าใช้จ่าย, ถังขยะ) styled like the settings tabs, keeping the current filter query when switching type.
- [ ] **Step 2: `records/index.tsx`** — filter bar, table on desktop (date, orchard/tree, what, amount, who + `AiBadge`, `RowActions`), rows on mobile, pagination links, `EmptyState` per type. Edit opens a `Dialog` with the matching existing form posting to the existing `update` route; delete uses `useConfirm` then the existing `destroy` route. "จดใหม่" is a dropdown of orchards: care → `orchards.care-records.create`, harvests → `orchards.harvests.create`, sales and observations → `orchards.show`.
- [ ] **Step 3: `records/trash.tsx`** — list with kind, title, subject, deleted date and a "กู้คืน" button; show the `id` error as a toast or inline alert.
- [ ] **Step 4: Move the expenses page** into `RecordsLayout`, behaviour unchanged except filters now come from `RecordFilters`.
- [ ] **Step 5: Verify** `npm run types:check && npm run lint:check && npm run build`, then `php artisan test --compact tests/Feature/Farm/ExpenseControllerTest.php` (render name changed).
- [ ] **Step 6: Commit** `feat: records pages with filters, tables and trash`.

### Task 10: Claude connection moves to settings

**Files:**
- Create: `app/Http/Controllers/Settings/AiConnectionController.php`, `resources/js/pages/settings/ai.tsx`
- Modify: `app/Http/Controllers/AssistantController.php` (drop `myConnections`, `setup`, `members` props; keep `recentAiRecords`; `destroy` moves to the new controller), `routes/settings.php` (replace the `settings/ai` redirect with `GET settings/ai` named `ai-connections.index`; `DELETE settings/ai/{user}/{client}` named `ai-connections.destroy`), `routes/web.php` (drop `assistant.connections.destroy`), `resources/js/pages/assistant/index.tsx` (remove the connection tab, keep the guide and recent records), `resources/js/layouts/settings/layout.tsx` (tab "เชื่อมต่อ Claude" for everyone)
- Test: `tests/Feature/Settings/AiConnectionTest.php`, update `tests/Feature/AssistantTest.php`

**Interfaces:**
- Produces: `settings/ai` props `myConnections`, `setup` (owner only, else null), `members` (owner only, else null) — same shapes and logic as today's `AssistantController`.

- [ ] **Step 1: Move the connection tests** from `AssistantTest` to `AiConnectionTest` against the new routes, and add `assistant page no longer carries connection setup` (`missing('setup')`).
- [ ] **Step 2: Run** — expect FAIL.
- [ ] **Step 3: Implement** backend and the two page changes.
- [ ] **Step 4: Run** `php artisan test --compact tests/Feature/AssistantTest.php tests/Feature/Settings` and the frontend checks — expect PASS.
- [ ] **Step 5: Commit** `feat: move the Claude connection to settings`.

### Task 11: Findings settings

**Files:**
- Create: `app/Http/Controllers/Settings/FindingController.php`, `app/Http/Requests/Settings/FindingRequest.php`, `resources/js/pages/settings/findings.tsx`
- Modify: `routes/settings.php`, `resources/js/layouts/settings/layout.tsx` (tab "สิ่งที่พบ")
- Test: `tests/Feature/Settings/FindingManagementTest.php`

**Interfaces:**
- Produces: `GET settings/findings` (`findings.index`, props `findings: [{id, name, kind, inUse: bool}]` grouped client-side by kind), `POST settings/findings` (`findings.store`), `PATCH settings/findings/{finding}` (`findings.update`), `DELETE settings/findings/{finding}` (`findings.destroy`). Open to every signed-in user. Name required, trimmed, max 100, unique; kind `in:problem,stage`, editable only while not in use.

- [ ] **Step 1: Write the failing tests**: `members can list, add and rename findings`, `finding names are unique`, `unused finding can be deleted`, `finding in use cannot be deleted` (live observation), `finding used by a trashed observation cannot be deleted`, `finding used by a trashed issue cannot be deleted`. Refusal message: `รายการนี้มีบันทึกใช้อยู่ ลบไม่ได้`.
- [ ] **Step 2: Run** — expect FAIL.
- [ ] **Step 3: Implement**; `inUse` checks observations and tree issues with `withTrashed()`.
- [ ] **Step 4: Run** the test file and frontend checks — expect PASS.
- [ ] **Step 5: Commit** `feat: manage findings in settings`.

### Task 12: Desktop-first navigation

**Files:**
- Modify: `resources/js/lib/navigation.ts`, `resources/js/types/navigation.ts` (`NavItem.placement?: 'everywhere' | 'desktop'`), `resources/js/components/app-sidebar.tsx`, `resources/js/components/app-bottom-nav.tsx`, `resources/js/layouts/settings/layout.tsx` (phone-only link "บันทึกทั้งหมด" to `records.index` `care`), `resources/js/components/app-logo` link target → `overview`
- Test: `tests/Feature/AppBrandingTest.php` only if it asserts the logo link; otherwise frontend checks

**Interfaces:**
- Produces: `mainNavItems` = ภาพรวม (`/overview`, icon `LayoutDashboard`), สวน, บันทึก (`/records`, `desktop`, icon `NotebookText`), ผู้ช่วย AI, ตั้งค่า (`/settings`, `desktop`, icon `Settings`). Bottom bar shows items without `desktop` plus `accountNavItem` → exactly 4: ภาพรวม, สวน, ผู้ช่วย AI, บัญชี. Remove the old "ค่าใช้จ่าย" item (its page lives under บันทึก).

- [ ] **Step 1: Implement** and keep `matches` so `/records`, `/expenses` highlight บันทึก and `/t/` still highlights สวน.
- [ ] **Step 2: Verify** frontend checks pass, then click through `/overview`, `/records/care`, `/settings/ai` at 1280px and 390px.
- [ ] **Step 3: Run** the whole suite `php artisan test --compact` — expect PASS.
- [ ] **Step 4: Commit** `feat: desktop-first navigation with overview, records and settings`.
