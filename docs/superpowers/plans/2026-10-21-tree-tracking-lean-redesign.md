# Tree Tracking + Lean Redesign Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Replace the generic plot/crop-cycle/grade ERP with a lean orchard → tree model (one QR per tree, per-orchard seasons, bulk care/harvest entry) for a single owner growing only durian, and delete everything else.

**Architecture:** Fresh schema (no data migration — old migrations are deleted/edited in place). Eloquent models `Orchard`, `Tree`, `Season`, `CareRecord` (+ pivot `care_record_tree`), `Harvest`, `Sale`, `Expense`. Season membership of care records / harvests / sales is computed at query time from dates; only `expenses.season_id` is stored. Controllers stay in `App\Http\Controllers\Farm`, form requests in `App\Http\Requests\Farm`, pages in `resources/js/pages/farm/**`, Wayfinder for all URLs.

**Tech Stack:** Laravel 13, Fortify, Inertia v3 + React 19, Tailwind v4, Wayfinder, Pest v4, SQLite, `bacon/bacon-qr-code` v3 (already installed via Fortify).

**Spec:** `docs/superpowers/specs/2026-10-21-tree-tracking-lean-redesign-design.md`

## Global Constraints

- All user-facing text, `aria-label` and `sr-only` text in Thai; code identifiers in English (CLAUDE.md).
- Years displayed as พ.ศ. (CE + 543), as the old dashboard did.
- No new dependencies. Only change: add `"bacon/bacon-qr-code": "^3.0"` to `composer.json` `require` (already in `composer.lock`). Removing unused npm/radix packages is approved.
- No `recorded_by` columns anywhere; no roles.
- Mobile-first for the tree page, bulk care form, harvest grid (usable at 375px width).
- Follow existing conventions: `Store*/Update*Request` form requests with `authorize(): true`; `Inertia::flash('toast', ['type' => 'success', 'message' => '…'])` then `to_route(...)`; `#[Fillable([...])]` attribute + `@property` PHPDoc on models; enums with `label(): string` returning Thai; pages call `setLayoutProps({ breadcrumbs })`; Pest feature tests under `tests/Feature/Farm`.
- After PHP changes: `vendor/bin/pint --dirty --format agent`. Every task ends with its tests green: `php artisan test --compact <files>`.
- Seasons: dates inclusive on both ends; `ended_on = null` means "open, up to today"; at most one open season per orchard; seasons in the same orchard must not overlap.

## Review Focus

1. **QR scanned while logged out** → after login the owner lands on that tree's page, not the home page. (Test in Task 3.)
2. **Bulk forms receive a removed tree or a tree from another orchard** (stale page, tampered post) → rejected with a validation error, nothing saved. (Tests in Tasks 5 and 6.)
3. **Harvest grid submitted with only some rows filled, or none** → only filled rows saved; all-empty → validation error on `rows`. (Test in Task 6.)
4. **New season overlaps an existing one**, including starting before an open season, or an open-ended season created when a later closed season exists → validation error on `started_on`. (Test in Task 4.)
5. **A record dated exactly on a season's `started_on` or `ended_on`** → counted in that season, not the neighbour. (Test in Task 4.)

---

### Task 1: Strip starter-kit and auth cruft

**Files:**
- Delete: `app/Enums/UserRole.php`, `tests/Feature/Farm/UserRoleTest.php`, `database/migrations/2026_06_27_100000_add_role_to_users_table.php`, `app/Http/Requests/Settings/ProfileDeleteRequest.php`, `app/Http/Requests/Settings/TwoFactorAuthenticationRequest.php`, `app/Actions/Fortify/ResetUserPassword.php`, `tests/Feature/Auth/PasswordResetTest.php`, `resources/js/pages/auth/forgot-password.tsx`, `resources/js/pages/auth/reset-password.tsx`, `resources/js/pages/settings/appearance.tsx`, `resources/js/pages/settings/security.tsx`, `resources/js/components/delete-user.tsx`, `resources/js/components/appearance-tabs.tsx`, `resources/js/hooks/use-appearance.tsx`, `resources/js/components/nav-footer.tsx`, `resources/js/layouts/auth/auth-card-layout.tsx`, `resources/js/layouts/auth/auth-split-layout.tsx`, `resources/js/layouts/app/app-header-layout.tsx` (and `app-header.tsx` if nothing else imports it), `app/Http/Controllers/Settings/SecurityController.php`, `tests/Feature/Settings/SecurityTest.php`
- Modify: `app/Models/User.php` (drop role cast/property/`isAdmin`), `database/factories/UserFactory.php` (drop role + admin state), users migration (drop two-factor columns if present), `config/fortify.php` (features `[]`), `app/Providers/FortifyServiceProvider.php` (login view only, `canResetPassword` removed), `resources/js/pages/auth/login.tsx` (no forgot link), `routes/settings.php`, `app/Http/Controllers/Settings/ProfileController.php`, `resources/js/pages/settings/profile.tsx`, `resources/js/layouts/settings/layout.tsx` (no nav — single page), `resources/js/components/app-sidebar.tsx` (no footer nav), `routes/console.php` (remove `inspire`), `resources/views/app.blade.php` + `HandleInertiaRequests` if they read the appearance cookie (force light)
- Test: `tests/Feature/Settings/ProfileUpdateTest.php`, `tests/Feature/AppBrandingTest.php`, `tests/Feature/Auth/AuthenticationTest.php`

**Interfaces:**
- Produces: routes `profile.edit` (GET `settings/profile`), `profile.update` (PATCH), `user-password.update` (PUT `settings/password`, throttle 6,1) — all handled by `ProfileController` (`edit`, `update`, `updatePassword(PasswordUpdateRequest)`). `settings` redirects to `settings/profile`.

- [x] **Step 1: Update tests first**
  - `ProfileUpdateTest`: delete the two account-deletion tests; move "password can be updated" and "correct password must be provided to update password" from `SecurityTest` here, posting to `route('user-password.update')` and asserting redirect to `route('profile.edit')`.
  - Add `test('account deletion route no longer exists')`: `expect(Route::has('profile.destroy'))->toBeFalse()`; same file, `test('appearance and security pages no longer exist')` for `appearance.edit` and `security.edit`.
  - `AuthenticationTest`: delete the two-factor test; add `test('password reset routes are disabled')`: `expect(Route::has('password.request'))->toBeFalse()`.
  - `AppBrandingTest`: delete the appearance-cookie test.
- [x] **Step 2: Run** `php artisan test --compact tests/Feature/Settings tests/Feature/Auth tests/Feature/AppBrandingTest.php` — expect FAIL (routes still exist).
- [x] **Step 3: Apply the deletions/modifications above.** Profile page renders the existing profile form and the password form stacked on one page.
- [x] **Step 4: Run** the same tests + `npm run types` (or `npx tsc --noEmit`) + `npm run lint` — expect PASS / no errors.
- [x] **Step 5: Commit** `refactor: remove roles, appearance, account deletion, password reset and starter-kit leftovers`

---

### Task 2: Replace the old farm domain with Orchards

**Files:**
- Delete: every old farm migration (`2026_06_27_1*`), models `FruitType FruitVariety Plot CropCycle ActivityType ExpenseCategory Activity Expense Grade Harvest HarvestItem Sale SaleItem`, their factories, controllers in `app/Http/Controllers/Farm/*`, requests in `app/Http/Requests/Farm/*`, enums `CropCycleStage CropCycleStatus ExpenseScope`, `app/Http/Controllers/DashboardController.php`, all `tests/Feature/Farm/*`, `tests/Feature/DashboardTest.php`, pages `resources/js/pages/farm/**`, `resources/js/pages/dashboard.tsx`, `resources/js/components/ui/placeholder-pattern.tsx`, old types in `resources/js/types/farm.ts`
- Create: migration `create_orchards_table`, `app/Models/Orchard.php`, `database/factories/OrchardFactory.php`, `app/Http/Controllers/Farm/OrchardController.php`, `app/Http/Requests/Farm/StoreOrchardRequest.php` (also used for update), `resources/js/pages/farm/orchards/index.tsx`, `resources/js/pages/farm/orchards/show.tsx`, `tests/Feature/Farm/OrchardControllerTest.php`
- Modify: `routes/web.php`, `config/fortify.php` (`'home' => '/orchards'`), `resources/js/components/app-sidebar.tsx` (items: สวน → `orchards.index`; ค่าใช้จ่าย added in Task 8), `resources/js/types/farm.ts`

**Interfaces:**
- Produces: `Orchard { id, name: string, location: ?string, notes: ?string }`; routes `orchards.index|store|show|update|destroy` (`Route::resource(...)->except(['create','edit'])`, forms are dialogs/inline on index/show); `/` redirects authenticated users to `orchards.index` (guests to `login`); `dashboard` route removed. TS type `Orchard` exported from `@/types/farm`.

- [x] **Step 1: Write failing tests** in `OrchardControllerTest`: guests redirected to login from `orchards.index`; `test('home redirects to orchards')` (`get('/')` as user → redirect `route('orchards.index')`); store with `name='สวนบ้านล่าง'` creates row and redirects to `orchards.show`; `name` required; update changes name; destroy removes an orchard with no trees.
- [x] **Step 2: Run** `php artisan test --compact tests/Feature/Farm/OrchardControllerTest.php` — FAIL.
- [x] **Step 3: Do the deletions, then create migration/model/factory/controller/request/pages.** Index lists orchards (cards; details filled in Task 9). Show page is a skeleton with header + edit/delete; later tasks add sections. Toasts: `เพิ่มสวนแล้ว`, `บันทึกสวนแล้ว`, `ลบสวนแล้ว`; delete confirm `ลบสวนนี้และข้อมูลทั้งหมดในสวน?`.
- [x] **Step 4: Run** `php artisan migrate:fresh --seed`, the test file, `npx tsc --noEmit`, `npm run lint` — PASS.
- [x] **Step 5: Commit** `refactor: replace plot/crop-cycle domain with orchards`

---

### Task 3: Trees, removal, and the QR short URL

**Files:**
- Create: migration `create_trees_table`, `app/Models/Tree.php`, `TreeFactory` (state `removed()`), `app/Http/Controllers/Farm/TreeController.php`, `app/Http/Requests/Farm/StoreTreeRequest.php`, `UpdateTreeRequest.php`, `StoreTreeBatchRequest.php`, `resources/js/pages/farm/trees/show.tsx`, `tests/Feature/Farm/TreeControllerTest.php`, `tests/Feature/Farm/TreeModelTest.php`
- Modify: `Orchard` (relation), `routes/web.php`, `pages/farm/orchards/show.tsx` (tree list + add one / add many dialogs + "แสดงต้นที่ไม่อยู่แล้ว" toggle via `?show_removed=1`), `types/farm.ts`

**Interfaces:**
- Produces:
  - `trees`: `orchard_id` (cascade), `code` string, unique (`orchard_id`,`code`), `variety` ?string, `planted_on` ?date, `removed_on` ?date, `notes` ?text.
  - `Tree::scopeActive(Builder $query): void` (`removed_on` null); `Tree::isRemoved(): bool`; `Orchard::trees(): HasMany`. (`Tree::hasRecords()` and the destroy guard arrive in Task 6, once both record relations exist.)
  - Routes: `orchards.trees.store` POST `orchards/{orchard}/trees`; `orchards.trees.batch` POST `orchards/{orchard}/trees/batch` (`prefix` string, `start` int ≥1, `count` int 1–500, `variety` ?string → codes `{prefix}{start…}` zero-padded to 3 digits, e.g. `A-001`; skips none — duplicate code fails validation for the whole batch); `trees.show` GET `trees/{tree}`; `trees.update` PATCH; `trees.remove` PATCH `trees/{tree}/remove` (`removed_on` date, default today); `trees.restore` PATCH `trees/{tree}/restore`; `trees.destroy` DELETE (guarded in Task 6); `trees.qr` GET `t/{tree}` → redirect to `trees.show`, inside `auth` middleware so Laravel stores the intended URL.

- [x] **Step 1: Write failing tests:** store single tree; batch with `prefix='A-', start=1, count=3` creates `A-001..A-003`; batch fails when `A-002` already exists (no rows created); code unique per orchard but same code allowed in another orchard; `test('scanning a QR while logged out returns to the tree after login')` — `get('/t/'.$tree->id)` → redirect login; `post(route('login.store'), creds)` → `assertRedirect(route('trees.show', $tree))`; remove sets `removed_on` and the orchard show prop `trees` excludes it unless `show_removed=1`; restore clears it; destroy works for a tree with no records.
- [x] **Step 2: Run** `php artisan test --compact tests/Feature/Farm/TreeControllerTest.php tests/Feature/Farm/TreeModelTest.php` — FAIL.
- [x] **Step 3: Implement.** Tree page (mobile-first): header `code` · orchard name · variety, badge `ไม่อยู่แล้ว` when removed, edit/remove/restore actions in a menu; placeholder area for action buttons and timeline (Tasks 5–6).
- [x] **Step 4: Run** tests + `npx tsc --noEmit` — PASS.
- [x] **Step 5: Commit** `feat: add trees with removal and QR short URL`

---

### Task 4: Seasons

**Files:**
- Create: migration `create_seasons_table`, `app/Models/Season.php`, `SeasonFactory` (states `closed()`), `app/Http/Controllers/Farm/SeasonController.php`, `app/Http/Requests/Farm/StoreSeasonRequest.php` (also update), `resources/js/pages/farm/seasons/show.tsx` (skeleton; filled in Task 9), `tests/Feature/Farm/SeasonControllerTest.php`, `tests/Feature/Farm/SeasonModelTest.php`
- Modify: `Orchard` (`seasons()`, `currentSeason(): HasOne` = `ended_on` null), orchard show page (season section: current season, start / close / edit, list of past seasons linking to `seasons.show`)

**Interfaces:**
- Produces:
  - `seasons`: `orchard_id` (cascade), `name`, `started_on`, `ended_on` ?, `notes` ?.
  - `Season::effectiveEnd(): Carbon` (`ended_on ?? today`); `Season::containsDate(CarbonInterface $date): bool`; `Season::scopeCovering(Builder $query, int $orchardId, CarbonInterface $date): void`.
  - Generic date-window helper used by later tasks: `Season::applyDateWindow(Builder $query, string $column): Builder` → `whereBetween($column, [started_on, effectiveEnd()])`.
  - Routes: `orchards.seasons.store`, `seasons.show`, `seasons.update`, `seasons.close` (PATCH, `ended_on` default today, must be ≥ `started_on`), `seasons.destroy`.
  - Validation (in request, using route orchard / season's orchard, ignoring self on update): `ended_on` after_or_equal `started_on`; no overlap with any other season of the orchard (treat null end as +∞ for the open one and for the candidate); creating an open season when one is already open → error on `started_on` `มีรอบที่ยังไม่ปิดอยู่แล้ว`; overlap error `ช่วงวันซ้อนกับรอบอื่น`.

- [x] **Step 1: Write failing tests:** create open season; second open season rejected; closed season 2025-06-01..2026-03-31 then new season starting 2026-03-31 rejected, starting 2026-04-01 accepted; open season starting 2026-06-01 exists → closed season 2026-05-01..2026-07-01 rejected; `test('boundary dates belong to the season')` — season 2025-06-01..2026-03-31: `containsDate('2025-06-01')` and `containsDate('2026-03-31')` true, `'2026-04-01'` false; `Season::covering($orchard->id, date)` returns the right one across two adjacent seasons; close sets `ended_on`.
- [x] **Step 2: Run** the two test files — FAIL.
- [x] **Step 3: Implement.** Default season name suggestion in the UI: `ฤดู {พ.ศ. of started_on}`.
- [x] **Step 4: Run** — PASS.
- [x] **Step 5: Commit** `feat: add per-orchard seasons with overlap rules`

---

### Task 5: Care records (single and bulk)

**Files:**
- Create: migrations `create_care_records_table`, `create_care_record_tree_table`; `app/Enums/CareType.php`; `app/Models/CareRecord.php`; `CareRecordFactory`; `app/Http/Controllers/Farm/CareRecordController.php`; `app/Http/Requests/Farm/StoreCareRecordRequest.php` (also update); `resources/js/pages/farm/care-records/create.tsx` (bulk form); `resources/js/components/farm/care-record-form.tsx` (shared fields); `tests/Feature/Farm/CareRecordControllerTest.php`
- Modify: `Tree` (`careRecords(): BelongsToMany`), `Orchard` (`careRecords(): HasMany`), tree show page (buttons + dialog), orchard show page (button `บันทึกการดูแลหลายต้น` + recent care list with edit/delete)

**Interfaces:**
- Produces:
  - `CareType`: `Fertilizer = 'fertilizer'` → `ใส่ปุ๋ย`, `Pesticide = 'pesticide'` → `ฉีดยา`, `Other = 'other'` → `อื่น ๆ`.
  - `care_records`: `orchard_id` (cascade), `type`, `performed_on`, `product` ?, `quantity` ? string, `notes` ?. Pivot `care_record_tree` (`care_record_id`, `tree_id`, both cascade, unique pair).
  - Routes: `orchards.care-records.create` GET (bulk page, all active trees pre-checked), `orchards.care-records.store` POST (body: `type`, `performed_on`, `product`, `quantity`, `notes`, `tree_ids: int[]` min 1), `care-records.update` PATCH (same body), `care-records.destroy` DELETE. The tree-page dialog posts to `orchards.care-records.store` with `tree_ids=[tree.id]` and redirects back to the tree (`redirect_to=tree` flag or `back()`).
  - Validation: each `tree_ids.*` must exist, belong to the route orchard, and have `removed_on` null.
  - Toasts: `บันทึกการดูแลแล้ว`, `ลบบันทึกแล้ว`.

- [x] **Step 1: Write failing tests:** bulk record for 3 trees creates 1 care record with 3 pivot rows; single-tree record from tree page redirects to `trees.show`; `test('bulk care rejects removed trees and trees from other orchards')` — both cases error on `tree_ids.0` and create no rows; empty `tree_ids` rejected; update replaces tree set; destroy removes record and pivot.
- [x] **Step 2: Run** `php artisan test --compact tests/Feature/Farm/CareRecordControllerTest.php` — FAIL.
- [x] **Step 3: Implement.** Bulk page: select-all / select-none toggle, list of active trees with checkboxes (large tap targets), type as three big toggle buttons. Tree page: three buttons `ใส่ปุ๋ย` `ฉีดยา` `ผลผลิต` (last wired in Task 6); first two open the form dialog with type preset and date = today.
- [x] **Step 4: Run** tests + `npx tsc --noEmit` — PASS.
- [x] **Step 5: Commit** `feat: record fertilizer and pesticide care per tree or in bulk`

---

### Task 6: Harvests (single and grid) + tree timeline

**Files:**
- Create: migration `create_harvests_table`, `app/Models/Harvest.php`, `HarvestFactory`, `app/Http/Controllers/Farm/HarvestController.php`, `app/Http/Requests/Farm/StoreHarvestRequest.php`, `UpdateHarvestRequest.php`, `StoreHarvestBatchRequest.php`, `resources/js/pages/farm/harvests/create.tsx` (grid), `tests/Feature/Farm/HarvestControllerTest.php`
- Modify: `Tree` (`harvests(): HasMany`, `hasRecords(): bool` = any harvest or care record), `TreeController@destroy` (when `hasRecords()`, redirect back with validation error on `tree`: `ลบไม่ได้เพราะต้นนี้มีบันทึกแล้ว`), `TreeController@show` (timeline prop), tree show page (`ผลผลิต` dialog, timeline, per-season yield summary), orchard show page (button `บันทึกผลผลิตทั้งสวน`)

**Interfaces:**
- Produces:
  - `harvests`: `tree_id` (cascade), `harvested_on`, `weight_kg` decimal(10,2), `fruit_count` ?unsigned int, `notes` ?.
  - Routes: `trees.harvests.store` POST (`harvested_on`, `weight_kg` >0, `fruit_count` ?int ≥1, `notes`) → redirect `trees.show`; `orchards.harvests.create` GET (grid of active trees); `orchards.harvests.batch` POST (`harvested_on`, `rows: array<{tree_id, weight_kg?, fruit_count?}>`); `harvests.update` PATCH; `harvests.destroy` DELETE.
  - Batch rule: rows with empty `weight_kg` are dropped before validation (`prepareForValidation`); after filtering `rows` must be non-empty (error `กรอกน้ำหนักอย่างน้อย 1 ต้น` on `rows`); each `tree_id` belongs to the orchard and is active.
  - Tree show props: `timeline: list<{kind: 'care'|'harvest', id, date, title, detail}>` newest first (care title = `CareType::label()` + product), limit 50; `seasonYields: list<{seasonId, seasonName, weightKg, fruitCount}>` via `Season::applyDateWindow`.

- [x] **Step 1: Write failing tests:** single harvest stores and redirects to tree; `test('harvest grid saves only filled rows')` — 3 rows, one with `weight_kg=''` → 2 harvests; `test('harvest grid with no filled rows fails')` → error on `rows`; grid rejects removed tree / other-orchard tree; `weight_kg=0` rejected; tree `hasRecords()` true after a harvest and `trees.destroy` then errors with `ลบไม่ได้เพราะต้นนี้มีบันทึกแล้ว`; tree show `timeline` merges a care record and a harvest in date-desc order; `seasonYields` sums only harvests inside each season.
- [x] **Step 2: Run** `php artisan test --compact tests/Feature/Farm/HarvestControllerTest.php tests/Feature/Farm/TreeControllerTest.php` — FAIL.
- [x] **Step 3: Implement.** Grid: one date input, then one row per tree (`code`, kg input `inputMode="decimal"`, count input `inputMode="numeric"`), sticky save button.
- [x] **Step 4: Run** — PASS.
- [x] **Step 5: Commit** `feat: record harvests per tree and by orchard grid; tree timeline`

---

### Task 7: Sales

**Files:**
- Create: migration `create_sales_table`, `app/Models/Sale.php`, `SaleFactory`, `app/Http/Controllers/Farm/SaleController.php`, `app/Http/Requests/Farm/StoreSaleRequest.php` (also update), `tests/Feature/Farm/SaleControllerTest.php`
- Modify: `Orchard` (`sales()`), orchard show page (sales section: list + add/edit dialog)

**Interfaces:**
- Produces: `sales`: `orchard_id` (cascade), `sold_on`, `buyer_name` ?, `weight_kg` decimal(10,2) >0, `amount` decimal(12,2) >0, `notes` ?. Routes `orchards.sales.store`, `sales.update`, `sales.destroy` → redirect `orchards.show`. UI shows computed price/kg (`amount / weight_kg`, 2 decimals). Toasts `บันทึกการขายแล้ว`, `ลบการขายแล้ว`.

- [x] **Step 1: Write failing tests:** store/update/destroy; `weight_kg` and `amount` required and > 0; `buyer_name` optional.
- [x] **Step 2: Run** — FAIL. **Step 3: Implement.** **Step 4: Run** — PASS.
- [x] **Step 5: Commit** `feat: record orchard sales`

---

### Task 8: Expenses

**Files:**
- Create: migration `create_expenses_table`, `app/Enums/ExpenseCategory.php`, `app/Models/Expense.php`, `ExpenseFactory`, `app/Http/Controllers/Farm/ExpenseController.php`, `app/Http/Requests/Farm/StoreExpenseRequest.php` (also update), `resources/js/pages/farm/expenses/index.tsx`, `tests/Feature/Farm/ExpenseControllerTest.php`
- Modify: `Season` (`expenses(): HasMany`), sidebar (add `ค่าใช้จ่าย`)

**Interfaces:**
- Produces:
  - `ExpenseCategory`: `Fertilizer` `ปุ๋ย`, `Pesticide` `ยา/สารเคมี`, `Labor` `ค่าแรง`, `Utilities` `น้ำ/ไฟ/น้ำมัน`, `Equipment` `อุปกรณ์`, `Other` `อื่น ๆ` (string values = snake_case of the key).
  - `expenses`: `season_id` ? (nullOnDelete), `category`, `spent_on`, `amount` decimal(12,2) >0, `notes` ?.
  - Routes `expenses.index` (query `from`, `to` optional dates), `expenses.store`, `expenses.update`, `expenses.destroy`.
  - Index props: `expenses` (filtered, newest first, with `season.orchard`), `total: float`, `totalsByCategory: list<{category, label, total}>`, `seasons` (for the optional select, labelled `{orchard} · {season}`), `categories` (value/label list).

- [x] **Step 1: Write failing tests:** store without season; store with season; category must be a valid enum value; `amount` > 0; filter `from=2026-01-01&to=2026-01-31` excludes a 2026-02-01 expense and `total` equals the sum of included ones; `totalsByCategory` groups correctly.
- [x] **Step 2: Run** — FAIL. **Step 3: Implement.** **Step 4: Run** — PASS.
- [x] **Step 5: Commit** `feat: track expenses with fixed categories and optional season`

---

### Task 9: Season summary and orchard cards

**Files:**
- Modify: `SeasonController@show`, `pages/farm/seasons/show.tsx`, `OrchardController@index`, `pages/farm/orchards/index.tsx`, `OrchardController@show` (current-season yield per tree in the tree list)
- Test: `tests/Feature/Farm/SeasonControllerTest.php`, `tests/Feature/Farm/OrchardControllerTest.php`

**Interfaces:**
- Consumes: `Season::applyDateWindow`, `Orchard::currentSeason`.
- Produces:
  - `seasons.show` props: `season` (with orchard), `totalYieldKg`, `totalFruitCount`, `treeYields: list<{treeId, code, weightKg, fruitCount}>` (all trees of the orchard incl. removed ones that have harvests in the window, sorted weight desc), `salesTotal`, `salesWeightKg`, `sales`, `expensesTotal` (only expenses with this `season_id`), `careRecordCount`.
  - `orchards.index` props per orchard: `trees_count` (active), `current_season` (name or null), `current_yield_kg`.

- [x] **Step 1: Write failing tests:** season summary counts a harvest of a removed tree; excludes harvests/sales dated outside the window; `expensesTotal` ignores expenses without `season_id`; orchard index `current_yield_kg` equals sum of harvests in the open season only.
- [x] **Step 2: Run** — FAIL. **Step 3: Implement** (aggregate with SQL `sum`; no N+1 — one query per metric). **Step 4: Run** — PASS.
- [x] **Step 5: Commit** `feat: season summaries and orchard overview`

---

### Task 10: QR label printing

**Files:**
- Create: `app/Support/TreeQrCode.php`, `resources/js/pages/farm/orchards/qr.tsx`, `tests/Feature/Farm/TreeQrTest.php`
- Modify: `composer.json` (add `"bacon/bacon-qr-code": "^3.0"`, then `composer update --lock`), `OrchardController` (`qr` action), `routes/web.php`, orchard show page (button `พิมพ์ QR`)

**Interfaces:**
- Produces: `TreeQrCode::svg(Tree $tree): string` — SVG for `route('trees.qr', $tree)` (absolute URL), via `BaconQrCode\Writer` + `SvgImageBackEnd`, 200px. Route `orchards.qr` GET `orchards/{orchard}/qr` → props `labels: list<{code, orchardName, svg}>` for active trees only, ordered by code.
- Page: no app chrome when printing (`@media print` hides sidebar/header), A4 grid 4 columns × 6 rows of labels (QR + `code` large + orchard name small), each label `break-inside: avoid`; on-screen button `พิมพ์` calls `window.print()`.

- [x] **Step 1: Write failing tests:** `TreeQrCode::svg($tree)` starts with `<?xml` or `<svg` and contains no error; `orchards.qr` returns labels for active trees only (removed tree excluded), in code order.
- [x] **Step 2: Run** — FAIL. **Step 3: Implement.** **Step 4: Run** — PASS; manually confirm a printed label scans to `/t/{id}` (phone camera against browser print preview).
- [x] **Step 5: Commit** `feat: printable QR labels per orchard`

---

### Task 11: Seeder, dependency cleanup, docs, full verification

**Files:**
- Modify: `database/seeders/DatabaseSeeder.php`, `package.json`/lockfile, `README.md`; delete `docs/screenshots/*` and the README screenshots table; delete unused `resources/js/components/ui/*`
- Test: full suite

- [x] **Step 1: Seeder:** one owner user (`name` `เจ้าของสวน`, email/password from `env('OWNER_EMAIL', 'owner@example.com')` / `env('OWNER_PASSWORD', 'password')`); in `local` env also 2 orchards × 20 trees, one open season each, a few care records/harvests/sales/expenses. Add `OWNER_EMAIL`/`OWNER_PASSWORD` to `.env.example`.
- [x] **Step 2: Remove unused UI components and npm packages:** for each file in `components/ui` and each `@radix-ui/*` dependency, `grep -r` for imports; delete/uninstall the unreferenced ones (expected at least `navigation-menu`, `toggle-group`, `toggle` if unused).
- [x] **Step 3: README:** features list and getting-started match the new system (orchards, trees + QR, seasons, care, harvest, sales, expenses; seeding the owner; resetting a password via `php artisan tinker`).
- [x] **Step 4: Verify:** `php artisan migrate:fresh --seed`, `php artisan test --compact`, `vendor/bin/phpstan analyse` (if configured), `vendor/bin/pint --dirty --format agent`, `npx tsc --noEmit`, `npm run lint`, `npm run build` — all clean. `grep -rn "Plot\|CropCycle\|Grade\|FruitType\|isAdmin\|recorded_by" app resources/js tests database` returns nothing.
- [x] **Step 5: Commit** `chore: seed owner, drop unused components and dependencies, update README`
