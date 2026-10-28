# บัญชีคนในครอบครัว และหน้า "ผู้ช่วย AI" Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** ให้เจ้าของจัดการบัญชีคนในครอบครัวได้ และมีหน้า "ผู้ช่วย AI" ระดับเมนูหลักที่สอนว่าทำอะไรผ่าน Claude ได้บ้าง พร้อมส่วนจัดการการเชื่อมต่อของทุกคนสำหรับเจ้าของ

**Architecture:** เพิ่ม `users.is_owner` และ `users.deactivated_at` แล้วบังคับสิทธิ์ด้วย Gate `manage-members` ส่วนบัญชีที่ปิดใช้งานจะถูกกันไว้สามชั้น คือตอนล็อกอิน (Fortify `authenticateUsing`), ด้วย middleware `EnsureUserIsActive` (ทั้ง web และ MCP) และด้วยการ revoke token ของ Passport ทั้งหมด โค้ดที่ query หรือ revoke token ย้ายจาก `AiConnectionController` ไปไว้ที่ `App\Support\AiConnections` ให้หน้าสมาชิกและหน้าผู้ช่วย AI ใช้ร่วมกัน หน้า settings "เชื่อมต่อ Claude" เดิมถูกแทนที่ด้วย `AssistantController` + `pages/assistant/index.tsx`

**Tech Stack:** Laravel 13, Fortify, Passport, Inertia v3 + React 19, Tailwind v4, Wayfinder, Pest 4

**Spec:** `docs/superpowers/specs/2026-10-23-family-accounts-and-ai-page-design.md`

## Global Constraints

- ข้อความที่ผู้ใช้เห็นเป็นภาษาไทยทั้งหมด รวม `aria-label`, `sr-only`, toast และ validation message ส่วนชื่อในโค้ดเป็นภาษาอังกฤษ
- ห้ามใช้คำว่า MCP, OAuth หรือ token ในข้อความที่ผู้ใช้เห็น
- ห้ามเพิ่ม dependency
- ทำตาม convention เดิม: `#[Fillable]`, `casts()`, PHPDoc `@property`, `Inertia::flash('toast', ['type' => 'success', 'message' => '…'])` (ใช้ข้อความไทยตรง ๆ แบบ `AiConnectionController`), Pest แบบ `test()` ที่สร้าง `$this->user` ใน `beforeEach`
- UI เป็นไปตาม `DESIGN.md` และใช้ component ที่มี (`Heading`, `EmptyState`, `useConfirm`, `Badge`, `Button`, `Input`, `Dialog`, `DropdownMenu`)
- หลังแก้ PHP ให้รัน `vendor/bin/pint --dirty --format agent` ทุก task
- `is_owner` ห้ามอยู่ใน `#[Fillable]` ให้ตั้งค่าด้วย `forceFill` เท่านั้น

## Review Focus

1. เจ้าของเปลี่ยนอีเมลตัวเองแล้ว seeder รันซ้ำ (ทุกครั้งที่ deploy) → ต้องไม่มีบัญชีใหม่ และเจ้าของยังเป็นคนเดิม (Task 1)
2. สมาชิกพิมพ์ URL หรือส่ง request ไปที่ route จัดการสมาชิกเอง → ต้องได้ 403 (Task 3)
3. บัญชีที่ถูกปิดใช้งานระหว่างที่ยังล็อกอินค้างอยู่ หรือ Claude ใช้ token เดิม → request ถัดไปต้องถูกปฏิเสธ (Task 2)
4. เจ้าของส่ง request ปิดใช้งานบัญชีตัวเอง → ต้องถูกปฏิเสธ (Task 3)
5. สมาชิกส่ง request ยกเลิกการเชื่อมต่อของคนอื่น → ต้องได้ 403 (Task 5)

---

### Task 1: เจ้าของและสถานะปิดใช้งานในตาราง users

**Files:**
- Create: `database/migrations/2026_10_23_000001_add_owner_and_deactivation_to_users_table.php` (ใช้ `php artisan make:migration`)
- Modify: `app/Models/User.php`, `database/factories/UserFactory.php`, `database/seeders/DatabaseSeeder.php`
- Test: `tests/Feature/Farm/DatabaseSeederTest.php`, `tests/Feature/Settings/UserStatusTest.php` (ใหม่)

**Interfaces:**
- Produces: คอลัมน์ `users.is_owner` (boolean, default false) และ `users.deactivated_at` (timestamp nullable), cast เป็น `bool` และ `datetime`, method `User::isActive(): bool`, factory state `UserFactory::owner()` และ `UserFactory::deactivated()`

- [x] **Step 1: เขียน test ที่ fail**

ใน `DatabaseSeederTest.php`:
```php
it('marks the seeded account as the owner', function () {
    $this->seed(DatabaseSeeder::class);
    expect(User::sole()->is_owner)->toBeTrue();
});

it('does not create another owner when the owner changed their email', function () {
    $this->seed(DatabaseSeeder::class);
    User::sole()->update(['email' => 'changed@example.com']);

    $this->seed(DatabaseSeeder::class);

    expect(User::count())->toBe(1)
        ->and(User::sole()->email)->toBe('changed@example.com')
        ->and(User::sole()->is_owner)->toBeTrue();
});

it('does not hand ownership to a member who holds the owner email', function () {
    $owner = User::factory()->owner()->create(['email' => 'real-owner@example.com']);
    $member = User::factory()->create(['email' => config('farm.owner.email')]);

    $this->seed(DatabaseSeeder::class);

    expect($member->fresh()->is_owner)->toBeFalse()
        ->and($owner->fresh()->is_owner)->toBeTrue()
        ->and(User::count())->toBe(2);
});
```
ใน `UserStatusTest.php`:
```php
test('users are active until deactivated', function () {
    expect(User::factory()->create()->isActive())->toBeTrue()
        ->and(User::factory()->deactivated()->create()->isActive())->toBeFalse();
});
```

- [x] **Step 2: รัน test ให้เห็นว่า fail**

Run: `php artisan test --compact tests/Feature/Farm/DatabaseSeederTest.php tests/Feature/Settings/UserStatusTest.php`
Expected: FAIL เพราะยังไม่มีคอลัมน์ `is_owner` และ factory state

- [x] **Step 3: เขียน migration, model, factory และ seeder**

- migration `up()`: เพิ่มสองคอลัมน์ แล้ว backfill โดยตั้ง `is_owner = true` ให้บัญชีที่ `email = config('farm.owner.email')` ถ้าไม่มีบัญชีไหนตรง ให้ใช้บัญชีที่ `id` ต่ำสุด (ถ้าตารางว่างไม่ต้องทำอะไร) ส่วน `down()` ลบสองคอลัมน์
- `User`: เพิ่ม `@property bool $is_owner` และ `@property Carbon|null $deactivated_at`, cast ทั้งสองคอลัมน์ และเพิ่ม `isActive(): bool` (คืน true เมื่อ `deactivated_at === null`)
- `DatabaseSeeder::run()`: ถ้ามี `User::where('is_owner', true)` อยู่แล้ว ให้ข้ามการสร้างเจ้าของ ถ้ายังไม่มี ให้ใช้ `firstOrCreate` ตามเดิมแล้ว `forceFill(['is_owner' => true])->save()` ส่วนการเช็ก `OWNER_PASSWORD` ให้คงไว้เฉพาะตอนที่ต้องสร้างเจ้าของ

- [x] **Step 4: รัน test ให้ผ่าน**

Run: `php artisan test --compact tests/Feature/Farm/DatabaseSeederTest.php tests/Feature/Settings/UserStatusTest.php`
Expected: PASS

- [x] **Step 5: Commit**

```bash
git add database app/Models/User.php tests/Feature/Farm/DatabaseSeederTest.php tests/Feature/Settings/UserStatusTest.php
git commit -m "feat: mark the owner account and allow deactivating users"
```

---

### Task 2: กันบัญชีที่ปิดใช้งาน และแยกโค้ดการเชื่อมต่อ AI

**Files:**
- Create: `app/Support/AiConnections.php`, `app/Http/Middleware/EnsureUserIsActive.php`
- Modify: `app/Models/User.php`, `app/Providers/FortifyServiceProvider.php`, `bootstrap/app.php`, `routes/ai.php`, `app/Http/Controllers/Settings/AiConnectionController.php`
- Test: `tests/Feature/Settings/UserStatusTest.php`

**Interfaces:**
- Consumes: `User::isActive()`, `UserFactory::deactivated()` (Task 1)
- Produces:
  - `AiConnections::forUser(int $userId): array<int, array{clientId: string, name: string, connectedOn: string}>` ย้าย logic ของ `liveTokens()` และการ group ตาม client มาจาก `AiConnectionController::edit()` ทั้งหมด
  - `AiConnections::revoke(int $userId, string $clientId): void` ย้ายมาจาก `AiConnectionController::destroy()`
  - `AiConnections::revokeAll(int $userId): void` revoke access token และ refresh token ทุกตัวของ user
  - `User::deactivate(): void` ตั้ง `deactivated_at = now()` แล้วเรียก `AiConnections::revokeAll($this->id)`
  - `User::activate(): void` ตั้ง `deactivated_at = null`
  - `AiConnectionController` เปลี่ยนไปใช้ `AiConnections` (พฤติกรรมเดิมต้องไม่เปลี่ยน ให้ `AiConnectionTest` ผ่านเหมือนเดิม)

- [x] **Step 1: เขียน test ที่ fail** (ใน `UserStatusTest.php` ใช้ helper `issueToken()` จาก `AiConnectionTest.php` โดยย้าย helper ไปไว้ใน `tests/Pest.php` เพื่อใช้ร่วมกัน)

```php
test('deactivated users cannot log in', function () {
    $user = User::factory()->deactivated()->create();

    $this->post(route('login.store'), ['email' => $user->email, 'password' => 'password'])
        ->assertSessionHasErrors(['email' => 'บัญชีนี้ถูกปิดใช้งานแล้ว']);

    $this->assertGuest();
});

test('a session that outlives deactivation is logged out on the next request', function () {
    $user = User::factory()->create();
    $this->actingAs($user);
    $user->deactivate();

    $this->get(route('orchards.index'))->assertRedirect(route('login'));
    $this->assertGuest();
});

test('deactivating revokes every ai connection token', function () {
    $user = User::factory()->create();
    $token = issueToken($user, Client::factory()->create());

    $user->deactivate();

    expect($token->fresh()->revoked)->toBeTrue()
        ->and(RefreshToken::where('access_token_id', $token->id)->value('revoked'))->toBeTrue()
        ->and(AiConnections::forUser($user->id))->toBe([]);
});

test('the farm server refuses a deactivated user', function () {
    $user = User::factory()->deactivated()->create();
    Passport::actingAs($user);

    $this->postJson('/mcp/farm', ['jsonrpc' => '2.0', 'id' => 1, 'method' => 'tools/list'])->assertForbidden();
});
```

- [x] **Step 2: รัน test ให้เห็นว่า fail**

Run: `php artisan test --compact tests/Feature/Settings/UserStatusTest.php`
Expected: FAIL

- [x] **Step 3: เขียนโค้ด**

- `FortifyServiceProvider`: เพิ่ม `Fortify::authenticateUsing()` ให้ตรวจอีเมลและรหัสผ่านตามปกติ (`Hash::check`) ถ้ารหัสถูกแต่ `! $user->isActive()` ให้ throw `ValidationException::withMessages([Fortify::username() => 'บัญชีนี้ถูกปิดใช้งานแล้ว'])` ถ้ารหัสผิดให้คืน `null` (Fortify จะแสดงข้อความ `auth.failed` เดิม)
- `EnsureUserIsActive::handle()`: ถ้ามี user และ `! isActive()` แล้ว request เป็น `mcp/*` หรือ `expectsJson()` ให้ `abort(403)` ถ้าไม่ใช่ ให้ logout guard `web`, invalidate session, regenerate token แล้ว redirect ไป `route('login')`
- ลงทะเบียน middleware: append ใน `$middleware->web()` ของ `bootstrap/app.php` และเพิ่มต่อจาก `auth:api` ใน `routes/ai.php`

- [x] **Step 4: รัน test ให้ผ่าน**

Run: `php artisan test --compact tests/Feature/Settings tests/Feature/Auth tests/Feature/Mcp/FarmServerAuthTest.php`
Expected: PASS

- [x] **Step 5: Commit**

```bash
git commit -am "feat: lock deactivated users out of the app and the farm server"
```
(ต้อง `git add` ไฟล์ใหม่ด้วย)

---

### Task 3: หน้า "ตั้งค่า → สมาชิก" สำหรับเจ้าของ

**Files:**
- Create: `app/Http/Controllers/Settings/MemberController.php`, `app/Http/Requests/Settings/StoreMemberRequest.php`, `app/Http/Requests/Settings/UpdateMemberRequest.php`, `app/Http/Requests/Settings/ResetMemberPasswordRequest.php`, `resources/js/pages/settings/members.tsx`
- Modify: `app/Providers/AppServiceProvider.php`, `routes/settings.php`, `resources/js/layouts/settings/layout.tsx`, `resources/js/types/auth.ts`
- Test: `tests/Feature/Settings/MemberManagementTest.php`

**Interfaces:**
- Consumes: `is_owner`, `isActive()`, `deactivate()`, `activate()` (Task 1–2), `AiConnections::forUser()` (Task 2)
- Produces:
  - Gate `manage-members` ผ่านเมื่อ `$user->is_owner`
  - route ทั้งหมดอยู่ในกลุ่ม `auth` ของ `routes/settings.php` และใส่ `->can('manage-members')`:

    | method | path | name | action |
    |---|---|---|---|
    | GET | `settings/members` | `members.index` | `index` |
    | POST | `settings/members` | `members.store` | `store` |
    | PATCH | `settings/members/{user}` | `members.update` | `update` |
    | PUT | `settings/members/{user}/password` | `members.password` | `resetPassword` |
    | PATCH | `settings/members/{user}/deactivate` | `members.deactivate` | `deactivate` |
    | PATCH | `settings/members/{user}/activate` | `members.activate` | `activate` |

  - Inertia `settings/members` รับ props `members: Array<{id: number, name: string, email: string, isOwner: boolean, isActive: boolean, isConnected: boolean}>` (เรียงให้เจ้าของขึ้นก่อน ตามด้วยชื่อ) และ `passwordRules: string`
  - TS `User` type เพิ่ม `is_owner: boolean`

- [x] **Step 1: เขียน test ที่ fail**

```php
beforeEach(function () {
    $this->user = User::factory()->owner()->create();
});

test('only the owner can open the members page', function () {
    $member = User::factory()->create();
    $this->actingAs($member)->get(route('members.index'))->assertForbidden();
    $this->actingAs($member)->post(route('members.store'), [])->assertForbidden();
    $this->actingAs($this->user)->get(route('members.index'))
        ->assertInertia(fn (Assert $page) => $page->component('settings/members')->has('members', 2)
            ->where('members.0.isOwner', true));
});

test('the owner adds a member who can then log in', function () {
    $this->actingAs($this->user)->post(route('members.store'), [
        'name' => 'แม่', 'email' => 'mae@example.com', 'password' => 'password',
    ])->assertRedirect(route('members.index'));

    $member = User::where('email', 'mae@example.com')->sole();
    expect($member->is_owner)->toBeFalse()->and($member->email_verified_at)->not->toBeNull();
    expect(Hash::check('password', $member->password))->toBeTrue();
});

test('the owner edits a member and resets their password', function () { /* update name/email; resetPassword then Hash::check new value */ });

test('the owner deactivates and reactivates a member', function () {
    $member = User::factory()->create();
    $this->actingAs($this->user)->patch(route('members.deactivate', $member));
    expect($member->fresh()->isActive())->toBeFalse();
    $this->actingAs($this->user)->patch(route('members.activate', $member));
    expect($member->fresh()->isActive())->toBeTrue();
});

test('the owner cannot deactivate the owner account', function () {
    $this->actingAs($this->user)->patch(route('members.deactivate', $this->user))->assertForbidden();
    expect($this->user->fresh()->isActive())->toBeTrue();
});

test('the page says who has claude connected', function () { /* issueToken for one member → isConnected true, others false */ });
```

- [x] **Step 2: รัน test ให้เห็นว่า fail**

Run: `php artisan test --compact tests/Feature/Settings/MemberManagementTest.php`
Expected: FAIL

- [x] **Step 3: เขียน backend**

- `StoreMemberRequest`: ใช้ `profileRules()` และ `'password' => ['required', 'string', Password::default()]` (ไม่ต้องมี confirmed เพราะเจ้าของพิมพ์รหัสเอง) กำหนด `attributes()` เป็นภาษาไทยแบบ `StoreFollowUpRequest`
- `UpdateMemberRequest`: ใช้ `profileRules($this->route('user')->id)`
- `ResetMemberPasswordRequest`: ใช้ `password` ตามกฎเดียวกับ store
- `store`: สร้าง user พร้อม `email_verified_at = now()` (route farm ใช้ middleware `verified`)
- `deactivate`: ถ้า `$user->is_owner` ให้ `abort(403)`
- ทุก action flash toast ภาษาไทย เช่น "เพิ่มสมาชิกแล้ว", "บันทึกแล้ว", "ตั้งรหัสผ่านใหม่แล้ว", "ปิดใช้งานแล้ว", "เปิดใช้งานแล้ว" แล้ว `to_route('members.index')`

- [x] **Step 4: รัน test ให้ผ่าน**

Run: `php artisan test --compact tests/Feature/Settings/MemberManagementTest.php`
Expected: PASS

- [x] **Step 5: เขียนหน้า `settings/members.tsx` และเมนู settings**

- หน้าตาตาม `.superpowers/brainstorm/*/content/members.html`: รายการบัญชี (มีป้าย "เจ้าของ" หรือ "ปิดใช้งาน" และบรรทัดรองเป็นอีเมลกับ "Claude เชื่อมอยู่" / "ยังไม่เชื่อม"), ปุ่ม "เพิ่มสมาชิก" ที่เปิด `Dialog` ฟอร์ม, และ `DropdownMenu` ต่อบัญชี (แก้ชื่อ/อีเมล, ตั้งรหัสผ่านใหม่, ปิดใช้งาน/เปิดใช้งาน) โดยแถวของเจ้าของไม่มีเมนูปิดใช้งาน
- การปิดใช้งานต้องยืนยันด้วย `useConfirm` (destructive) และบอกว่า "จะล็อกอินไม่ได้ และ Claude ที่เชื่อมด้วยบัญชีนี้จะใช้ไม่ได้ทันที"
- ใต้ช่องรหัสผ่านเขียนว่า "บอกรหัสนี้กับเขาเอง เขาเปลี่ยนเองได้ที่ ตั้งค่า → โปรไฟล์"
- `settings/layout.tsx`: เพิ่มแท็บ "สมาชิก" เฉพาะเมื่อ `auth.user.is_owner` และแก้ description ของ Heading เป็น "บัญชีของคุณและสมาชิกในครอบครัว" เมื่อเป็นเจ้าของ

- [x] **Step 6: ตรวจ frontend**

Run: `npx tsc --noEmit && npx eslint resources/js && npm run build`
Expected: ไม่มี error

- [x] **Step 7: Commit**

```bash
git add app routes resources/js tests
git commit -m "feat: owner manages family member accounts"
```

---

### Task 4: ป้าย AI บอกชื่อคนบันทึก

**Files:**
- Modify: `app/Models/Concerns/TracksProvenance.php`, `app/Support/TreeTimeline.php`, `app/Support/RecentAiRecords.php`, `resources/js/types/farm.ts`, `resources/js/pages/farm/trees/show.tsx`
- Test: `tests/Feature/Farm/ProvenanceTest.php`, `tests/Feature/Farm/TreeObservationPropsTest.php` (หรือ test หน้าต้นที่ตรวจ `timeline` อยู่แล้ว)

**Interfaces:**
- Produces:
  - `TracksProvenance::creator(): BelongsTo<User>` และ `updater(): BelongsTo<User>` (foreign key `created_by` / `updated_by`)
  - `TracksProvenance::aiActorName(): ?string` ถ้า `created_via === mcp` คืน `creator?->name` ถ้าไม่ใช่แต่ `updated_via === mcp` คืน `updater?->name` นอกนั้นคืน `null`
  - timeline entry เพิ่ม `aiBy: string | null` (คง `fromAi` ไว้)
  - row ของ `RecentAiRecords::latest()` เพิ่ม `by: string | null` (ชื่อคนสร้าง)

- [x] **Step 1: เขียน test ที่ fail**

```php
test('the ai badge names whoever used ai', function () {
    $mae = User::factory()->create(['name' => 'แม่']);
    $owner = User::factory()->create(['name' => 'นราธิป']);
    $tree = Tree::factory()->create();

    $this->actingAs($owner);
    $harvest = Harvest::factory()->create(['tree_id' => $tree->id]);
    expect($harvest->aiActorName())->toBeNull();

    $this->actingAs($mae);
    Context::add(Provenance::VIA_KEY, Provenance::VIA_MCP);
    $harvest->update(['weight_kg' => 30]);
    expect($harvest->fresh()->aiActorName())->toBe('แม่');

    $created = Harvest::factory()->create(['tree_id' => $tree->id]);
    Context::forget(Provenance::VIA_KEY);
    $this->actingAs($owner);
    $created->update(['weight_kg' => 31]);
    expect($created->fresh()->aiActorName())->toBe('แม่');
});
```
และ test ว่า prop `timeline.*.aiBy` ของหน้าต้นกับ `recentAiRecords.0.by` มีชื่อคนสร้าง

- [x] **Step 2: รัน test ให้เห็นว่า fail**

Run: `php artisan test --compact tests/Feature/Farm/ProvenanceTest.php tests/Feature/Settings/AiConnectionTest.php`
Expected: FAIL

- [x] **Step 3: เขียนโค้ด** eager-load `creator:id,name` และ `updater:id,name` ใน `TreeTimeline` และ `creator:id,name` ใน `RecentAiRecords` เพื่อไม่ให้เกิด N+1 ในหน้า `trees/show.tsx` ให้ป้ายแสดง `AI · {entry.aiBy}` เมื่อมีชื่อ และแสดง `AI` เมื่อไม่มีชื่อ โดย `sr-only` ยังเป็น "บันทึกโดย"

- [x] **Step 4: รัน test ให้ผ่าน**

Run: `php artisan test --compact tests/Feature/Farm tests/Feature/Settings && npx tsc --noEmit`
Expected: PASS

- [x] **Step 5: Commit**

```bash
git commit -am "feat: show who recorded through ai on the badge"
```

---

### Task 5: Backend ของหน้า "ผู้ช่วย AI"

**Files:**
- Create: `app/Http/Controllers/AssistantController.php`, `tests/Feature/AssistantTest.php`
- Modify: `routes/web.php`, `routes/settings.php`
- Delete: `app/Http/Controllers/Settings/AiConnectionController.php`, `tests/Feature/Settings/AiConnectionTest.php` (ย้าย test ที่ยังใช้ได้ไปไว้ใน `AssistantTest.php`; ผู้ใช้อนุมัติแล้วในการย้ายหน้านี้)

**Interfaces:**
- Consumes: `AiConnections` (Task 2), `RecentAiRecords` ที่มี `by` (Task 4), Gate `manage-members` (Task 3)
- Produces:
  - `GET /assistant` → `assistant.index` (อยู่ในกลุ่ม `auth`, `verified` ของ `routes/web.php`)
  - `DELETE /assistant/connections/{user}/{client}` → `assistant.connections.destroy` อนุญาตเมื่อผู้ใช้เป็นเจ้าของ หรือ `{user}` เป็นตัวเอง นอกนั้น 403 แล้ว redirect กลับ `assistant.index` พร้อม toast "ยกเลิกการเชื่อมต่อแล้ว"
  - `settings/ai` → `Route::redirect` ไป `/assistant` และลบ route `ai-connection.*`
  - Inertia `assistant/index` รับ props:
    - `myConnections: Connection[]` (จาก `AiConnections::forUser(auth id)`)
    - `recentAiRecords: Array<{kind, title, subject, recordedOn, href, by}>`
    - `setup: {mcpUrl: string, isPubliclyReachable: boolean} | null` (เฉพาะเจ้าของ ย้าย `isPubliclyReachable()` และค่าคงที่ `MCP_PATH` มาจากคอนโทรลเลอร์เดิม)
    - `members: Array<{userId: number, name: string, connections: Connection[]}> | null` (เฉพาะเจ้าของ นับเฉพาะบัญชีที่ active เจ้าของขึ้นก่อน)

- [x] **Step 1: เขียน test ที่ fail** ย้าย test จาก `AiConnectionTest.php` ให้เรียก `route('assistant.index')` และ component `assistant/index` แล้วเพิ่ม

```php
test('members see their own connection but not the setup or other people', function () {
    $member = User::factory()->create();
    issueToken($member, Client::factory()->create(['name' => 'Claude']));

    $this->actingAs($member)->get(route('assistant.index'))
        ->assertInertia(fn (Assert $page) => $page->component('assistant/index')
            ->has('myConnections', 1)->where('setup', null)->where('members', null));
});

test('the owner sees every active member and their connections', function () { /* owner + member + deactivated → members has 2 */ });

test('the owner can revoke a member connection', function () { /* token revoked */ });

test('a member cannot revoke someone else\'s connection', function () {
    $other = User::factory()->create();
    $client = Client::factory()->create();
    $token = issueToken($other, $client);

    $this->actingAs(User::factory()->create())
        ->delete(route('assistant.connections.destroy', [$other, $client->id]))->assertForbidden();
    expect($token->fresh()->revoked)->toBeFalse();
});

test('the old settings address redirects to the assistant page', function () {
    $this->actingAs($this->user)->get('/settings/ai')->assertRedirect('/assistant');
});
```

- [x] **Step 2: รัน test ให้เห็นว่า fail**

Run: `php artisan test --compact tests/Feature/AssistantTest.php`
Expected: FAIL

- [x] **Step 3: เขียน `AssistantController` และ route** แล้วรัน `php artisan wayfinder:generate` (หรือ build) เพื่อสร้างฟังก์ชัน `@/routes/assistant` ใน frontend ระหว่างรอ Task 6 ให้ `resources/js/pages/settings/ai-connection.tsx` ถูกลบพร้อมลิงก์ในเมนู settings เพื่อไม่ให้ import ที่หายไปทำให้ build พัง

- [x] **Step 4: รัน test ให้ผ่าน**

Run: `php artisan test --compact tests/Feature/AssistantTest.php tests/Feature/Settings`
Expected: PASS

- [x] **Step 5: Commit**

```bash
git add -A app routes tests resources/js
git commit -m "feat: assistant page backend replaces the settings ai connection page"
```

---

### Task 6: หน้า "ผู้ช่วย AI" (frontend)

**ต้องโหลด skill `design-taste-frontend` (โหมด app screen) และ `inertia-react-development` ก่อนเริ่ม task นี้**

**Files:**
- Create: `resources/js/pages/assistant/index.tsx`, `resources/js/components/copyable-url.tsx` (ย้าย `CopyableUrl` มาจากหน้าเดิมโดยไม่เปลี่ยนพฤติกรรม), `resources/js/components/assistant/example-prompts.tsx`
- Modify: `resources/js/lib/navigation.ts`, `resources/js/components/app-bottom-nav.tsx`
- Test: ใช้ `tests/Feature/AssistantTest.php` (props) ร่วมกับการตรวจในเบราว์เซอร์

**Interfaces:**
- Consumes: props ของ `assistant/index` และ route `assistant.index` / `assistant.connections.destroy` (Task 5)

- [x] **Step 1: เพิ่มเมนู** ใน `mainNavItems` เพิ่ม `{ title: 'ผู้ช่วย AI', href: assistantIndex(), icon: Sparkles, matches: ['/assistant'] }` ต่อจาก "ค่าใช้จ่าย" ใน `app-bottom-nav.tsx` เปลี่ยน `grid-cols-3` เป็น `grid-cols-4`

- [x] **Step 2: เขียนหน้า** ตามลำดับใน spec ส่วนที่ 2 (ข้อ 1–8) และตาม mockup `ai-page.html` บนจอกว้างใช้สองคอลัมน์ได้แบบหน้าเดิม (`lg:grid-cols-2`)
  - สถานะ: ถ้าเชื่อมแล้วเขียน "Claude ของคุณเชื่อมอยู่ · เชื่อมเมื่อ {วันที่}" ถ้ายังไม่เชื่อม (และไม่ใช่เจ้าของ) เขียน "ยังไม่ได้เชื่อม Claude · ขอให้เจ้าของสวนช่วยตั้งค่าให้"
  - ตัวอย่างประโยค: แต่ละประโยคเป็นปุ่มที่กดแล้วคัดลอก มี feedback "คัดลอกแล้ว" และ fallback แบบเดียวกับ `CopyableUrl` ประโยคให้ใช้ตามรายการด้านล่าง (ทุกข้อทำได้จริงด้วยเครื่องมือที่มี):
    - **ถามข้อมูล**: "วันนี้ต้นไหนต้องดูซ้ำบ้าง" · "ต้น A-012 ปีนี้เก็บได้กี่กิโล" · "เดือนนี้จ่ายค่าปุ๋ยไปเท่าไหร่"
    - **บันทึก**: "วันนี้ใส่ปุ๋ย 15-15-15 ต้น A-001 ถึง A-010 ต้นละ 1 กิโล" · "ต้น A-005 ใบไหม้ ไม่หนัก" · "ขายทุเรียนให้เจ๊หมวย 300 กิโล ได้ 45,000 บาท"
    - **ดูซ้ำ**: "ต้น A-005 ที่ใบไหม้ ไปดูแล้วหายแล้ว" · "ต้น B-003 ยังไม่หาย นัดดูอีกทีอาทิตย์หน้า"
    - **ขอคำแนะนำ**: "ต้นที่เป็นราสีชมพู เคยใช้ยาอะไรแล้วหายเร็วที่สุด"
    - **จดความรู้**: "จำไว้ว่า ช่วงดอกบานห้ามฉีดยาฆ่าแมลง"
    - **แก้ ลบ กู้คืน**: "เมื่อกี้บันทึกผิด แก้เป็น 25 กิโล" · "ลบบันทึกขายเมื่อวานออก" · "เอาบันทึกที่ลบไปเมื่อวานกลับมา"
  - "Claude จะ…" และ "ยังต้องทำในแอป" ใช้ข้อความตาม spec ข้อ 4–5
  - บันทึกล่าสุดจาก AI: ใช้รูปแบบรายการจากหน้าเดิม บรรทัดรองเป็น `{kind} · {subject} · {by}`
  - ส่วนเจ้าของ (render เมื่อ `members !== null`): รายการ "การเชื่อมต่อของทุกคน" ที่ยกเลิกได้ผ่าน `useConfirm` (คำอธิบายแบบหน้าเดิม แต่ใส่ชื่อคน) และบัญชีที่ไม่มีการเชื่อมต่อแสดง "ยังไม่เชื่อม" ต่อด้วยส่วน "ตั้งค่าให้คนในครอบครัว" ที่มี `CopyableUrl`, Alert เมื่อ `!setup.isPubliclyReachable` (ข้อความเดิม) และขั้นตอน 4 ข้อจากหน้าเดิม โดยข้อ 1 เปลี่ยนเป็น "ล็อกอิน claude.ai ด้วยบัญชี Claude ของคนนั้น" และข้อ 3 เน้นว่า "ล็อกอินแอปนี้ด้วยบัญชีของคนนั้น แล้วกดอนุญาต"
  - ถ้าสมาชิกยกเลิกการเชื่อมต่อของตัวเองได้ (ตาม Task 5) ให้มีปุ่มยกเลิกในสถานะของตัวเองด้วย

- [x] **Step 3: ตรวจ frontend**

Run: `npx tsc --noEmit && npx eslint resources/js && npm run build && php artisan test --compact`
Expected: ไม่มี error และ test ทั้งหมดผ่าน

- [x] **Step 4: ตรวจในเบราว์เซอร์** (skill `run`) ล็อกอินเป็นเจ้าของและเป็นสมาชิก ดูหน้า `/assistant` ที่ความกว้างมือถือ 375px และเดสก์ท็อป ลองกดคัดลอก ลองยกเลิกการเชื่อมต่อ และเช็กว่าแถบล่างมี 4 เมนูโดยไม่ล้น ใช้ `browser-logs` ดูว่าไม่มี error ใน console

- [x] **Step 5: Commit**

```bash
git add resources/js
git commit -m "feat: assistant page that teaches what to ask claude"
```
