# Passkey Login Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Members log back in with a fingerprint/face passkey instead of typing a password; passwords remain as fallback.

**Architecture:** Turn on Fortify's built-in passkeys (backed by `laravel/passkeys` v0.2.1, already in `vendor/`). Backend adds the `passkeys` table, the `PasskeyUser` contract on `User`, a deactivated-account guard, an Inertia flash `suggestPasskey` after password login, and passkey props on the profile/members pages. Frontend uses `@laravel/passkeys/react` hooks for the login button, profile management, and a one-time "add a passkey" dialog.

**Tech Stack:** Laravel 13, Fortify 1.37.2, laravel/passkeys 0.2.1, Inertia v3 + React 19, Tailwind v4, Pest 4, Wayfinder.

**Spec:** `docs/superpowers/specs/2026-10-24-passkey-login-design.md`

## Global Constraints

- No new Composer packages. One new npm package: `@laravel/passkeys@0.2.0` (exact pin, matches PHP `laravel/passkeys` v0.2.x). The user approved the approach; confirm with them before running `npm install`.
- All user-facing text and `aria-label`s in Thai. Code identifiers in English.
- Passkeys must not require password re-confirmation: `Features::passkeys(['confirmPassword' => false])`.
- Passkey login always remembers the session (`remember: true`).
- Deactivated accounts get exactly `บัญชีนี้ถูกปิดใช้งานแล้ว` on passkey login, same as password login.
- After `/passkeys/login` succeeds, navigate with `window.location.href = response.redirect` (full page load), never an Inertia visit, so the Claude OAuth `intended` URL still works.
- No changes to the Blade OAuth consent page (`mcp.authorize`).
- Every PHP change has a Pest test; run `vendor/bin/pint --dirty --format agent` before each commit that touches PHP. Frontend tasks finish with `npm run types:check && npm run lint:check && npm run build`.
- Passkeys need HTTPS or `localhost`; `APP_URL` host is the relying party ID (Fortify default — do not override).

## Review Focus

1. **Deactivated member with a passkey** — login refused with the Thai message, not the library's English "Unable to sign in with this account." → Task 1 test `deactivated users cannot log in with a passkey`.
2. **Member who already has a passkey logs in with password** — no prompt. → Task 2 test `no passkey suggestion when the user already has one`.
3. **Failed password login** — must not set `suggestPasskey`. → Task 2 test `failed login does not suggest a passkey`.
4. **Profile page leaks another user's passkeys** — only own passkeys listed. → Task 3 test `profile lists only the signed-in user's passkeys`.
5. **User cancels the browser passkey sheet** — stays on page with no red error (`NotAllowedError`/abort is silent). → Task 4 and Task 5 Step "cancel is silent"; verified manually in the browser since there is no JS test runner.

---

### Task 1: Enable passkeys and block deactivated accounts

**Files:**
- Modify: `config/fortify.php` (`features` array)
- Create: `database/migrations/*_create_passkeys_table.php` (published, not hand-written)
- Modify: `app/Models/User.php`
- Modify: `app/Providers/FortifyServiceProvider.php`
- Create: `tests/Feature/Auth/PasskeyTest.php`

**Interfaces:**
- Produces: `User::passkeys(): HasMany` (from trait), routes `passkey.login-options`, `passkey.login`, `passkey.registration-options`, `passkey.store`, `passkey.destroy`. Test helper (top of `PasskeyTest.php`, reused by Tasks 2–3 by copying or moving to `tests/Pest.php`):
  `function createPasskey(User $user, string $name = 'iPhone'): Passkey` → `$user->passkeys()->create(['name' => $name, 'credential_id' => Str::random(32), 'credential' => []])`.

- [ ] **Step 1: Write failing tests in `tests/Feature/Auth/PasskeyTest.php`**

```php
test('passkey routes are registered', function () {
    foreach (['passkey.login-options', 'passkey.login', 'passkey.registration-options', 'passkey.store', 'passkey.destroy'] as $name) {
        expect(Route::has($name))->toBeTrue();
    }
});

test('users can request passkey registration options without confirming their password', function () {
    $this->actingAs(User::factory()->create())
        ->getJson(route('passkey.registration-options'))
        ->assertOk()
        ->assertJsonStructure(['options']);
});

test('users cannot delete another user\'s passkey', function () {
    $passkey = createPasskey(User::factory()->create());

    $this->actingAs(User::factory()->create())
        ->deleteJson(route('passkey.destroy', $passkey))
        ->assertForbidden();
});

test('users can delete their own passkey', function () {
    $user = User::factory()->create();
    $passkey = createPasskey($user);

    $this->actingAs($user)->deleteJson(route('passkey.destroy', $passkey))->assertOk();

    expect($user->passkeys()->count())->toBe(0);
});

test('active users are allowed to log in with a passkey', function () {
    $passkey = createPasskey(User::factory()->create());

    expect(Passkeys::allowsLogin(request(), $passkey))->toBeTrue();
});

test('deactivated users cannot log in with a passkey', function () {
    $passkey = createPasskey(User::factory()->deactivated()->create());

    expect(fn () => Passkeys::allowsLogin(request(), $passkey))
        ->toThrow(ValidationException::class, 'บัญชีนี้ถูกปิดใช้งานแล้ว');
});
```

(`Passkeys` = `Laravel\Passkeys\Passkeys`, `Passkey` = `Laravel\Passkeys\Passkey`.)

- [ ] **Step 2: Run** `php artisan test --compact tests/Feature/Auth/PasskeyTest.php` — Expected: FAIL (routes missing / no `passkeys` relation).

- [ ] **Step 3: Implement**
  - `config/fortify.php`: `'features' => [Features::passkeys(['confirmPassword' => false])]` (add `use Laravel\Fortify\Features;`).
  - `php artisan vendor:publish --tag=fortify-migrations --no-interaction`, keep only the passkeys migration (delete any other newly published Fortify migration files, e.g. two-factor columns), then `php artisan migrate --no-interaction`.
  - `User`: `implements OAuthenticatable, PasskeyUser` (`Laravel\Fortify\Contracts\PasskeyUser`) and `use PasskeyAuthenticatable` (`Laravel\Fortify\PasskeyAuthenticatable`).
  - `FortifyServiceProvider::configureAuthentication()`: add `Passkeys::authorizeLoginUsing(function (Request $request, User $user): bool { ... })` that throws `ValidationException::withMessages(['passkey' => 'บัญชีนี้ถูกปิดใช้งานแล้ว'])` when `! $user->isActive()`, else returns `true`. Extract the message into a private const shared with the password path so both use the same string.

- [ ] **Step 4: Run** the same test file plus `tests/Feature/Auth/AuthenticationTest.php tests/Feature/Settings/UserStatusTest.php` — Expected: PASS.

- [ ] **Step 5: Pint and commit** — `git commit -m "feat: enable fortify passkeys and block deactivated accounts"`

---

### Task 2: Suggest a passkey after password login

**Files:**
- Modify: `app/Providers/FortifyServiceProvider.php` (`authenticateUsing` closure)
- Modify: `tests/Feature/Auth/PasskeyTest.php`

**Interfaces:**
- Produces: Inertia flash key `suggestPasskey` (`true`), set only on successful password login when the user has zero passkeys. Frontend (Task 6) reads it from the `flash` router event.

- [ ] **Step 1: Write failing tests**

```php
test('password login suggests a passkey when the user has none', function () {
    $user = User::factory()->create();

    $this->post(route('login.store'), ['email' => $user->email, 'password' => 'password'])
        ->assertSessionHas('inertia.flash_data.suggestPasskey', true);
});

test('no passkey suggestion when the user already has one', function () {
    $user = User::factory()->create();
    createPasskey($user);

    $this->post(route('login.store'), ['email' => $user->email, 'password' => 'password'])
        ->assertSessionMissing('inertia.flash_data.suggestPasskey');
});

test('failed login does not suggest a passkey', function () {
    $user = User::factory()->create();

    $this->post(route('login.store'), ['email' => $user->email, 'password' => 'wrong'])
        ->assertSessionMissing('inertia.flash_data.suggestPasskey');
});
```

- [ ] **Step 2: Run** — Expected: first test FAILS.

- [ ] **Step 3: Implement** — in the `authenticateUsing` closure, just before `return $user;`: `if ($user->passkeys()->doesntExist()) { Inertia::flash('suggestPasskey', true); }`. Being a flash, it survives exactly one redirect, which gives "once per login" for free.

- [ ] **Step 4: Run** `php artisan test --compact tests/Feature/Auth` — Expected: PASS.

- [ ] **Step 5: Pint and commit** — `git commit -m "feat: flag password logins without a passkey"`

---

### Task 3: Passkey props on profile and members pages

**Files:**
- Modify: `app/Http/Controllers/Settings/ProfileController.php` (`edit`)
- Modify: `app/Http/Controllers/Settings/MemberController.php` (`index`)
- Modify: `tests/Feature/Settings/ProfileUpdateTest.php`, `tests/Feature/Settings/MemberManagementTest.php`

**Interfaces:**
- Produces Inertia props:
  - `settings/profile` → `passkeys: Array<{ id: number; name: string; authenticator: string | null; createdAt: string; lastUsedAt: string | null }>` (ISO 8601 strings, newest first).
  - `settings/members` → each member gains `hasPasskey: boolean`.

- [ ] **Step 1: Write failing tests**

```php
// ProfileUpdateTest.php
test('profile lists only the signed-in user\'s passkeys', function () {
    $user = User::factory()->create();
    createPasskey($user, 'iPhone · Safari');
    createPasskey(User::factory()->create(), 'อื่น');

    $this->actingAs($user)->get(route('profile.edit'))
        ->assertInertia(fn (Assert $page) => $page
            ->has('passkeys', 1)
            ->where('passkeys.0.name', 'iPhone · Safari')
            ->has('passkeys.0.createdAt')
            ->where('passkeys.0.lastUsedAt', null));
});

// MemberManagementTest.php
test('members page shows who has a passkey', function () {
    $owner = User::factory()->owner()->create(['name' => 'ก']);
    $member = User::factory()->create(['name' => 'ข']);
    createPasskey($member);

    $this->actingAs($owner)->get(route('members.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('members.0.hasPasskey', false)
            ->where('members.1.hasPasskey', true));
});
```

Move `createPasskey()` into `tests/Pest.php` in this task so all three test files share it.

- [ ] **Step 2: Run** both test files — Expected: new tests FAIL.

- [ ] **Step 3: Implement** — profile: `$request->user()->passkeys()->latest()->get()->map(...)` to the shape above (build the array explicitly; do not rely on `toArray()`). Members: `->withExists('passkeys')` on the query and `'hasPasskey' => $user->passkeys_exists`.

- [ ] **Step 4: Run** `php artisan test --compact tests/Feature/Settings` — Expected: PASS.

- [ ] **Step 5: Pint and commit** — `git commit -m "feat: expose passkeys on profile and members pages"`

---

### Task 4: Passkey button on the login page

**Files:**
- Modify: `package.json`, `package-lock.json` (`@laravel/passkeys@0.2.0`)
- Modify: `resources/js/pages/auth/login.tsx`

**Interfaces:**
- Consumes: Wayfinder routes `@/routes/passkey` (`loginOptions`, `login`) generated from Task 1's route names — run `php artisan wayfinder:generate` if they are missing. Pass them to the hook as `routes: { options: loginOptions.url(), submit: login.url() }` so paths never drift from Fortify.

- [ ] **Step 1: Install** — after the user confirms: `npm install @laravel/passkeys@0.2.0 --save-exact`.

- [ ] **Step 2: Implement the login page**
  - `usePasskeyVerify({ remember: true, routes, onSuccess: (r) => { window.location.href = r.redirect } })` from `@laravel/passkeys/react`. Check the installed package's hook signature in `node_modules/@laravel/passkeys` and adapt if `remember` goes to `verify()` instead.
  - Above the existing form: full-width `<Button>` "เข้าสู่ระบบด้วย passkey" (fingerprint icon from `lucide-react`), rendered only when `isSupported`. Spinner while `isLoading`.
  - Below it: a separator labelled "หรือใช้รหัสผ่าน", then the existing email/password/remember form unchanged.
  - Error under the button via `InputError`: if the server returned the deactivated message show it as-is. Any other failure shows "เข้าสู่ระบบด้วย passkey ไม่สำเร็จ ลองอีกครั้ง". **Cancel is silent:** a user-cancelled sheet (`NotAllowedError` / abort) shows nothing.
  - `Login.layout.description` → "สแกนนิ้วหรือใบหน้าด้วย passkey หรือกรอกอีเมลและรหัสผ่าน".

- [ ] **Step 3: Verify** `npm run types:check && npm run lint:check && npm run build` — Expected: no errors. `php artisan test --compact tests/Feature/Auth` still PASS.

- [ ] **Step 4: Commit** — `git commit -m "feat: passkey sign-in button on the login page"`

---

### Task 5: Manage passkeys on the profile page, badge on members page

**Files:**
- Create: `resources/js/lib/device-name.ts`
- Create: `resources/js/components/passkey-list.tsx`
- Modify: `resources/js/pages/settings/profile.tsx`
- Modify: `resources/js/pages/settings/members.tsx`

**Interfaces:**
- Consumes: Task 3 props (`passkeys`, `hasPasskey`), Wayfinder `@/routes/passkey` (`registrationOptions`, `store`, `destroy`).
- Produces: `deviceName(userAgent: string): string` (e.g. `"iPhone · Safari"`, `"Android · Chrome"`, `"Mac · Safari"`, fallback `"อุปกรณ์นี้"`), and `<AddPasskeyButton onAdded?: () => void />` exported from `passkey-list.tsx` (reused by Task 6).

- [ ] **Step 1: Implement `deviceName`** — platform from UA (iPhone, iPad, Android, Mac, Windows), browser (Safari, Chrome, Edge, Firefox, Samsung Internet). Joined with " · ".

- [ ] **Step 2: Implement `passkey-list.tsx`**
  - `AddPasskeyButton`: `usePasskeyRegister({ routes })`, calls `register(deviceName(navigator.userAgent))`. On success: `toast.success('เพิ่ม passkey แล้ว')`, `router.reload({ only: ['passkeys'] })`, `onAdded?.()`. Hidden when unsupported. Cancel is silent; other errors → `toast.error('เพิ่ม passkey ไม่สำเร็จ')`.
  - `PasskeyList`: each row shows name, `authenticator` if present, "เพิ่มเมื่อ …", "ใช้ล่าสุด …" (or "ยังไม่เคยใช้"), dates via `resources/js/lib/thai-date.ts`. Delete button (`aria-label="ลบ passkey {name}"`) opens the existing `confirm-dialog.tsx`, then `router.delete(destroy.url(id), { preserveScroll: true, onSuccess: () => toast.success('ลบ passkey แล้ว') })`.
  - Empty state: "ยังไม่มี passkey เพิ่มไว้เพื่อเข้าสู่ระบบด้วยการสแกนนิ้วหรือใบหน้า".

- [ ] **Step 3: Profile page** — new section under profile info with `Heading` "Passkey" / description "เข้าสู่ระบบด้วยการสแกนนิ้วหรือใบหน้าแทนการพิมพ์รหัสผ่าน", then `PasskeyList` + `AddPasskeyButton`. Same section styling as its siblings.

- [ ] **Step 4: Members page** — next to the existing Claude connection badge: `<Badge variant="secondary">มี passkey</Badge>` or `<Badge variant="outline">ยังไม่มี passkey</Badge>`. View only.

- [ ] **Step 5: Verify** `npm run types:check && npm run lint:check && npm run build` — Expected: no errors.

- [ ] **Step 6: Commit** — `git commit -m "feat: manage passkeys on the profile page"`

---

### Task 6: One-time "add a passkey" dialog

**Files:**
- Create: `resources/js/components/passkey-prompt.tsx`
- Modify: `resources/js/app.tsx` (mount next to `<Toaster />`)

**Interfaces:**
- Consumes: flash `suggestPasskey` (Task 2), `AddPasskeyButton` (Task 5).

- [ ] **Step 1: Implement `PasskeyPrompt`** — subscribe with `router.on('flash', …)` exactly like `resources/js/hooks/use-flash-toast.ts`; open when `flash.suggestPasskey === true` and passkeys are supported. `Dialog` (`components/ui/dialog.tsx`):
  - title "เข้าสู่ระบบครั้งหน้าง่ายขึ้น"
  - description "เพิ่ม passkey เพื่อเข้าสู่ระบบครั้งหน้าด้วยการสแกนนิ้วหรือใบหน้า"
  - actions `AddPasskeyButton` (label "เพิ่มเลย", `onAdded` closes) and ghost button "ไว้ทีหลัง" (closes).
  No persistence: the flash only fires once per login.

- [ ] **Step 2: Verify** `npm run types:check && npm run lint:check && npm run build` — Expected: no errors.

- [ ] **Step 3: Manual check in the browser** (`localhost`, real device or Chrome's WebAuthn virtual authenticator in DevTools): password login without a passkey → dialog shows once → "เพิ่มเลย" adds it → log out → "เข้าสู่ระบบด้วย passkey" logs in and lands on `/orchards` → reload a page, still logged in → cancel the sheet shows no error → deactivate the member, passkey login shows "บัญชีนี้ถูกปิดใช้งานแล้ว".

- [ ] **Step 4: Commit** — `git commit -m "feat: suggest adding a passkey after password login"`

---

### Final verification

- [ ] `php artisan test --compact` — all PASS.
- [ ] `vendor/bin/pint --dirty --format agent` — clean.
- [ ] `npm run types:check && npm run lint:check && npm run build` — clean.
