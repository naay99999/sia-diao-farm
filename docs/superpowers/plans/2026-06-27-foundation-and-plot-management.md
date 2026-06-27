# Foundation + Module 1 (Plot Management) Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Build the role foundation and Module 1 (Farm Plot Management) — fruit type/variety master data, plots, and crop cycles — with full CRUD and the crop-cycle harvest-date forecast.

**Architecture:** Laravel 13 + Inertia v3 (React 19). Eloquent models with attribute-based `#[Fillable]`, FormRequest validation, resourceful controllers returning `Inertia::render`, Wayfinder-generated route/action helpers consumed by `<Form>`. Crop cycle is the central entity; `expected_harvest_date` is computed from `flowering_date + fruit_variety.days_to_harvest`. Pest feature tests with `RefreshDatabase` cover every endpoint.

**Tech Stack:** PHP 8.5, Laravel 13, Inertia v3, React 19, TypeScript, Tailwind v4, Wayfinder, Pest 4.

**Scope note:** This is plan 1 of 4 (one per module from the data-model spec `docs/superpowers/specs/2026-06-27-farm-erp-data-model-design.md`). Tables in scope: `users.role`, `fruit_types`, `fruit_varieties`, `plots`, `crop_cycles`. The other master tables (`grades`, `activity_types`, `expense_categories`) and transactional tables ship with their owning modules (plans 2–4).

**Conventions to follow (study before starting):**
- Models use `#[Fillable([...])]` and `#[Hidden([...])]` attributes + `@property` docblocks (see `app/Models/User.php`).
- Feature tests: `php artisan make:test --pest <Name>`, run with `php artisan test --compact --filter=<Name>`.
- All user-facing strings (UI + validation messages) are **Thai**. Code identifiers stay English.
- After any PHP change, run `vendor/bin/pint --dirty --format agent`.
- After adding/changing routes or controllers, run `php artisan wayfinder:generate` so `@/actions/**` and `@/routes/**` imports resolve.
- Flash success toasts with `Inertia::flash('toast', ['type' => 'success', 'message' => '...'])`.

---

## File Structure

**Created:**
- `app/Enums/UserRole.php` — admin/user role enum
- `app/Enums/CropCycleStage.php` — bำรุงดิน→เก็บเกี่ยวแล้ว stages
- `app/Enums/CropCycleStatus.php` — active/closed
- `app/Models/FruitType.php`, `app/Models/FruitVariety.php`, `app/Models/Plot.php`, `app/Models/CropCycle.php`
- `database/factories/FruitTypeFactory.php`, `FruitVarietyFactory.php`, `PlotFactory.php`, `CropCycleFactory.php`
- `database/migrations/*_add_role_to_users_table.php`
- `database/migrations/*_create_fruit_types_table.php`
- `database/migrations/*_create_fruit_varieties_table.php`
- `database/migrations/*_create_plots_table.php`
- `database/migrations/*_create_crop_cycles_table.php`
- `app/Http/Controllers/Farm/FruitTypeController.php`, `FruitVarietyController.php`, `PlotController.php`, `CropCycleController.php`
- `app/Http/Requests/Farm/StoreFruitTypeRequest.php`, `UpdateFruitTypeRequest.php`, `StoreFruitVarietyRequest.php`, `UpdateFruitVarietyRequest.php`, `StorePlotRequest.php`, `UpdatePlotRequest.php`, `StoreCropCycleRequest.php`, `UpdateCropCycleRequest.php`
- `resources/js/pages/farm/fruit-types/index.tsx`
- `resources/js/pages/farm/fruit-varieties/index.tsx`
- `resources/js/pages/farm/plots/index.tsx`, `create.tsx`, `edit.tsx`, `show.tsx`
- `resources/js/types/farm.ts` — shared TS types for farm entities
- Test files under `tests/Feature/Farm/`

**Modified:**
- `app/Models/User.php` — add `role` cast + `isAdmin()`
- `database/factories/UserFactory.php` — default role + `admin()` state
- `routes/web.php` — register farm resource routes
- `resources/js/components/app-sidebar.tsx` — add farm nav items

---

## Task 1: User role foundation

**Files:**
- Create: `app/Enums/UserRole.php`
- Create: `database/migrations/2026_06_27_100000_add_role_to_users_table.php`
- Modify: `app/Models/User.php`
- Modify: `database/factories/UserFactory.php`
- Test: `tests/Feature/Farm/UserRoleTest.php`

- [ ] **Step 1: Create the enum**

```php
<?php

namespace App\Enums;

enum UserRole: string
{
    case Admin = 'admin';
    case User = 'user';
}
```

- [ ] **Step 2: Create the migration**

Create `database/migrations/2026_06_27_100000_add_role_to_users_table.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('role')->default('user')->after('email');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('role');
        });
    }
};
```

- [ ] **Step 3: Update the User model**

In `app/Models/User.php`: add `use App\Enums\UserRole;`, add `@property UserRole $role` to the docblock, add the cast and helper method.

Add to the `casts()` array:

```php
'role' => UserRole::class,
```

Add this method to the class:

```php
public function isAdmin(): bool
{
    return $this->role === UserRole::Admin;
}
```

- [ ] **Step 4: Update the UserFactory**

In `database/factories/UserFactory.php` add `use App\Enums\UserRole;`, add `'role' => UserRole::User,` to the `definition()` array, and add this state method:

```php
public function admin(): static
{
    return $this->state(fn (array $attributes) => [
        'role' => UserRole::Admin,
    ]);
}
```

- [ ] **Step 5: Write the test**

Create with `php artisan make:test --pest Farm/UserRoleTest`, then replace contents:

```php
<?php

use App\Enums\UserRole;
use App\Models\User;

test('users default to the user role', function () {
    $user = User::factory()->create();

    expect($user->role)->toBe(UserRole::User);
    expect($user->isAdmin())->toBeFalse();
});

test('admin factory state creates an admin', function () {
    $admin = User::factory()->admin()->create();

    expect($admin->role)->toBe(UserRole::Admin);
    expect($admin->isAdmin())->toBeTrue();
});
```

- [ ] **Step 6: Run tests**

Run: `php artisan test --compact --filter=UserRoleTest`
Expected: PASS (2 tests)

- [ ] **Step 7: Format and commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Enums/UserRole.php database/migrations app/Models/User.php database/factories/UserFactory.php tests/Feature/Farm/UserRoleTest.php
git commit -m "feat: add user role foundation (admin/user)"
```

---

## Task 2: FruitType model + migration + factory

**Files:**
- Create: `database/migrations/2026_06_27_100100_create_fruit_types_table.php`
- Create: `app/Models/FruitType.php`
- Create: `database/factories/FruitTypeFactory.php`
- Test: `tests/Feature/Farm/FruitTypeModelTest.php`

- [ ] **Step 1: Write the failing test**

Create with `php artisan make:test --pest Farm/FruitTypeModelTest`, then:

```php
<?php

use App\Models\FruitType;
use App\Models\FruitVariety;

test('a fruit type has many varieties', function () {
    $type = FruitType::factory()->create();
    FruitVariety::factory()->count(2)->for($type)->create();

    expect($type->varieties)->toHaveCount(2);
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --compact --filter=FruitTypeModelTest`
Expected: FAIL ("Class FruitType not found")

- [ ] **Step 3: Create the migration**

`database/migrations/2026_06_27_100100_create_fruit_types_table.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fruit_types', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fruit_types');
    }
};
```

- [ ] **Step 4: Create the model**

`app/Models/FruitType.php`:

```php
<?php

namespace App\Models;

use Database\Factories\FruitTypeFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $name
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['name'])]
class FruitType extends Model
{
    /** @use HasFactory<FruitTypeFactory> */
    use HasFactory;

    /**
     * @return HasMany<FruitVariety, $this>
     */
    public function varieties(): HasMany
    {
        return $this->hasMany(FruitVariety::class);
    }
}
```

- [ ] **Step 5: Create the factory**

`database/factories/FruitTypeFactory.php`:

```php
<?php

namespace Database\Factories;

use App\Models\FruitType;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<FruitType>
 */
class FruitTypeFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => fake()->randomElement(['ทุเรียน', 'มะม่วง', 'มังคุด', 'ลำไย', 'เงาะ']),
        ];
    }
}
```

- [ ] **Step 6: Run test to verify it passes**

Run: `php artisan test --compact --filter=FruitTypeModelTest`
Expected: PASS (depends on FruitVariety from Task 3; if run before Task 3, expect FAIL on FruitVariety — run after Task 3, or temporarily stub. To keep tasks independent, run this test together with Task 3.)

- [ ] **Step 7: Format and commit**

```bash
vendor/bin/pint --dirty --format agent
git add database/migrations app/Models/FruitType.php database/factories/FruitTypeFactory.php tests/Feature/Farm/FruitTypeModelTest.php
git commit -m "feat: add FruitType model"
```

---

## Task 3: FruitVariety model + migration + factory

**Files:**
- Create: `database/migrations/2026_06_27_100200_create_fruit_varieties_table.php`
- Create: `app/Models/FruitVariety.php`
- Create: `database/factories/FruitVarietyFactory.php`
- Test: `tests/Feature/Farm/FruitVarietyModelTest.php`

- [ ] **Step 1: Write the failing test**

Create with `php artisan make:test --pest Farm/FruitVarietyModelTest`, then:

```php
<?php

use App\Models\FruitType;
use App\Models\FruitVariety;

test('a variety belongs to a fruit type', function () {
    $type = FruitType::factory()->create(['name' => 'ทุเรียน']);
    $variety = FruitVariety::factory()->for($type)->create([
        'name' => 'หมอนทอง',
        'days_to_harvest' => 135,
    ]);

    expect($variety->fruitType->name)->toBe('ทุเรียน');
    expect($variety->days_to_harvest)->toBe(135);
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --compact --filter=FruitVarietyModelTest`
Expected: FAIL ("Class FruitVariety not found")

- [ ] **Step 3: Create the migration**

`database/migrations/2026_06_27_100200_create_fruit_varieties_table.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fruit_varieties', function (Blueprint $table) {
            $table->id();
            $table->foreignId('fruit_type_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->unsignedInteger('days_to_harvest');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fruit_varieties');
    }
};
```

- [ ] **Step 4: Create the model**

`app/Models/FruitVariety.php`:

```php
<?php

namespace App\Models;

use Database\Factories\FruitVarietyFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $fruit_type_id
 * @property string $name
 * @property int $days_to_harvest
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['fruit_type_id', 'name', 'days_to_harvest'])]
class FruitVariety extends Model
{
    /** @use HasFactory<FruitVarietyFactory> */
    use HasFactory;

    /**
     * @return BelongsTo<FruitType, $this>
     */
    public function fruitType(): BelongsTo
    {
        return $this->belongsTo(FruitType::class);
    }

    /**
     * @return HasMany<Plot, $this>
     */
    public function plots(): HasMany
    {
        return $this->hasMany(Plot::class);
    }
}
```

- [ ] **Step 5: Create the factory**

`database/factories/FruitVarietyFactory.php`:

```php
<?php

namespace Database\Factories;

use App\Models\FruitType;
use App\Models\FruitVariety;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<FruitVariety>
 */
class FruitVarietyFactory extends Factory
{
    public function definition(): array
    {
        return [
            'fruit_type_id' => FruitType::factory(),
            'name' => fake()->randomElement(['หมอนทอง', 'ชะนี', 'น้ำดอกไม้', 'อกร่อง', 'พวงทอง']),
            'days_to_harvest' => fake()->numberBetween(90, 150),
        ];
    }
}
```

- [ ] **Step 6: Run tests**

Run: `php artisan test --compact --filter="FruitVarietyModelTest|FruitTypeModelTest"`
Expected: PASS (both Task 2 and Task 3 model tests)

- [ ] **Step 7: Format and commit**

```bash
vendor/bin/pint --dirty --format agent
git add database/migrations app/Models/FruitVariety.php database/factories/FruitVarietyFactory.php tests/Feature/Farm/FruitVarietyModelTest.php
git commit -m "feat: add FruitVariety model"
```

---

## Task 4: Plot model + migration + factory

**Files:**
- Create: `database/migrations/2026_06_27_100300_create_plots_table.php`
- Create: `app/Models/Plot.php`
- Create: `database/factories/PlotFactory.php`
- Test: `tests/Feature/Farm/PlotModelTest.php`

- [ ] **Step 1: Write the failing test**

Create with `php artisan make:test --pest Farm/PlotModelTest`, then:

```php
<?php

use App\Models\FruitVariety;
use App\Models\Plot;

test('a plot belongs to a fruit variety', function () {
    $variety = FruitVariety::factory()->create();
    $plot = Plot::factory()->for($variety)->create();

    expect($plot->fruitVariety->id)->toBe($variety->id);
});

test('tree age in years is computed from planted_at', function () {
    $plot = Plot::factory()->create([
        'planted_at' => now()->subYears(5)->startOfDay(),
    ]);

    expect($plot->tree_age_years)->toBe(5);
});

test('tree age is null when planted_at is missing', function () {
    $plot = Plot::factory()->create(['planted_at' => null]);

    expect($plot->tree_age_years)->toBeNull();
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --compact --filter=PlotModelTest`
Expected: FAIL ("Class Plot not found")

- [ ] **Step 3: Create the migration**

`database/migrations/2026_06_27_100300_create_plots_table.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('plots', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->foreignId('fruit_variety_id')->constrained();
            $table->unsignedInteger('tree_count');
            $table->date('planted_at')->nullable();
            $table->decimal('area_rai', 8, 2)->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('plots');
    }
};
```

- [ ] **Step 4: Create the model**

`app/Models/Plot.php`:

```php
<?php

namespace App\Models;

use Database\Factories\PlotFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $name
 * @property int $fruit_variety_id
 * @property int $tree_count
 * @property Carbon|null $planted_at
 * @property string|null $area_rai
 * @property string|null $notes
 * @property int|null $tree_age_years
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['name', 'fruit_variety_id', 'tree_count', 'planted_at', 'area_rai', 'notes'])]
class Plot extends Model
{
    /** @use HasFactory<PlotFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'planted_at' => 'date',
        ];
    }

    /**
     * @return BelongsTo<FruitVariety, $this>
     */
    public function fruitVariety(): BelongsTo
    {
        return $this->belongsTo(FruitVariety::class);
    }

    /**
     * @return HasMany<CropCycle, $this>
     */
    public function cropCycles(): HasMany
    {
        return $this->hasMany(CropCycle::class);
    }

    /**
     * @return HasOne<CropCycle, $this>
     */
    public function activeCropCycle(): HasOne
    {
        return $this->hasOne(CropCycle::class)
            ->where('status', \App\Enums\CropCycleStatus::Active)
            ->latestOfMany();
    }

    /**
     * @return Attribute<int|null, never>
     */
    protected function treeAgeYears(): Attribute
    {
        return Attribute::get(
            fn (): ?int => $this->planted_at?->diffInYears(now()) !== null
                ? (int) $this->planted_at->diffInYears(now())
                : null
        );
    }
}
```

- [ ] **Step 5: Create the factory**

`database/factories/PlotFactory.php`:

```php
<?php

namespace Database\Factories;

use App\Models\FruitVariety;
use App\Models\Plot;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Plot>
 */
class PlotFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => 'แปลง'.fake()->randomElement(['ทุเรียนทิศเหนือ', 'มะม่วงหน้าบ้าน', 'ทิศใต้', 'ริมคลอง']),
            'fruit_variety_id' => FruitVariety::factory(),
            'tree_count' => fake()->numberBetween(20, 200),
            'planted_at' => fake()->dateTimeBetween('-10 years', '-1 year'),
            'area_rai' => fake()->randomFloat(2, 1, 20),
            'notes' => null,
        ];
    }
}
```

- [ ] **Step 6: Run test to verify it passes**

Run: `php artisan test --compact --filter=PlotModelTest`
Expected: PASS (3 tests). The `activeCropCycle` relation references `CropCycle` (Task 5) but is not exercised by these tests; it will resolve once Task 5 lands. If autoload errors occur, complete Task 5 then re-run.

- [ ] **Step 7: Format and commit**

```bash
vendor/bin/pint --dirty --format agent
git add database/migrations app/Models/Plot.php database/factories/PlotFactory.php tests/Feature/Farm/PlotModelTest.php
git commit -m "feat: add Plot model with tree-age accessor"
```

---

## Task 5: CropCycle model + enums + migration + factory + forecast

**Files:**
- Create: `app/Enums/CropCycleStage.php`, `app/Enums/CropCycleStatus.php`
- Create: `database/migrations/2026_06_27_100400_create_crop_cycles_table.php`
- Create: `app/Models/CropCycle.php`
- Create: `database/factories/CropCycleFactory.php`
- Test: `tests/Feature/Farm/CropCycleModelTest.php`

- [ ] **Step 1: Write the failing test**

Create with `php artisan make:test --pest Farm/CropCycleModelTest`, then:

```php
<?php

use App\Enums\CropCycleStage;
use App\Enums\CropCycleStatus;
use App\Models\CropCycle;
use App\Models\FruitVariety;
use App\Models\Plot;
use Illuminate\Support\Carbon;

test('recording flowering computes the expected harvest date', function () {
    $variety = FruitVariety::factory()->create(['days_to_harvest' => 120]);
    $plot = Plot::factory()->for($variety)->create();
    $cycle = CropCycle::factory()->for($plot)->for($variety)->create([
        'flowering_date' => null,
        'expected_harvest_date' => null,
        'stage' => CropCycleStage::Fruiting,
    ]);

    $cycle->recordFlowering(Carbon::parse('2026-01-01'));

    expect($cycle->flowering_date->toDateString())->toBe('2026-01-01');
    expect($cycle->expected_harvest_date->toDateString())->toBe('2026-05-01');
    expect($cycle->stage)->toBe(CropCycleStage::Flowering);
});

test('a cycle belongs to a plot and is castable', function () {
    $cycle = CropCycle::factory()->create([
        'status' => CropCycleStatus::Active,
    ]);

    expect($cycle->status)->toBe(CropCycleStatus::Active);
    expect($cycle->plot)->not->toBeNull();
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --compact --filter=CropCycleModelTest`
Expected: FAIL ("Class CropCycleStage not found")

- [ ] **Step 3: Create the enums**

`app/Enums/CropCycleStage.php`:

```php
<?php

namespace App\Enums;

enum CropCycleStage: string
{
    case SoilPrep = 'soil_prep';
    case Flowering = 'flowering';
    case Fruiting = 'fruiting';
    case ReadyToHarvest = 'ready_to_harvest';
    case Harvested = 'harvested';

    public function label(): string
    {
        return match ($this) {
            self::SoilPrep => 'บำรุงดิน',
            self::Flowering => 'ออกดอก',
            self::Fruiting => 'ติดผล',
            self::ReadyToHarvest => 'พร้อมเก็บเกี่ยว',
            self::Harvested => 'เก็บเกี่ยวแล้ว',
        };
    }
}
```

`app/Enums/CropCycleStatus.php`:

```php
<?php

namespace App\Enums;

enum CropCycleStatus: string
{
    case Active = 'active';
    case Closed = 'closed';

    public function label(): string
    {
        return match ($this) {
            self::Active => 'กำลังดำเนินการ',
            self::Closed => 'ปิดรอบแล้ว',
        };
    }
}
```

- [ ] **Step 4: Create the migration**

`database/migrations/2026_06_27_100400_create_crop_cycles_table.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('crop_cycles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('plot_id')->constrained()->cascadeOnDelete();
            $table->foreignId('fruit_variety_id')->constrained();
            $table->string('label');
            $table->string('stage')->default('soil_prep');
            $table->string('status')->default('active');
            $table->date('flowering_date')->nullable();
            $table->date('expected_harvest_date')->nullable();
            $table->date('started_at');
            $table->date('closed_at')->nullable();
            $table->foreignId('recorded_by')->constrained('users');
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('crop_cycles');
    }
};
```

- [ ] **Step 5: Create the model**

`app/Models/CropCycle.php`:

```php
<?php

namespace App\Models;

use App\Enums\CropCycleStage;
use App\Enums\CropCycleStatus;
use Database\Factories\CropCycleFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $plot_id
 * @property int $fruit_variety_id
 * @property string $label
 * @property CropCycleStage $stage
 * @property CropCycleStatus $status
 * @property Carbon|null $flowering_date
 * @property Carbon|null $expected_harvest_date
 * @property Carbon $started_at
 * @property Carbon|null $closed_at
 * @property int $recorded_by
 * @property string|null $notes
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['plot_id', 'fruit_variety_id', 'label', 'stage', 'status', 'flowering_date', 'expected_harvest_date', 'started_at', 'closed_at', 'recorded_by', 'notes'])]
class CropCycle extends Model
{
    /** @use HasFactory<CropCycleFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'stage' => CropCycleStage::class,
            'status' => CropCycleStatus::class,
            'flowering_date' => 'date',
            'expected_harvest_date' => 'date',
            'started_at' => 'date',
            'closed_at' => 'date',
        ];
    }

    /**
     * @return BelongsTo<Plot, $this>
     */
    public function plot(): BelongsTo
    {
        return $this->belongsTo(Plot::class);
    }

    /**
     * @return BelongsTo<FruitVariety, $this>
     */
    public function fruitVariety(): BelongsTo
    {
        return $this->belongsTo(FruitVariety::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function recordedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }

    /**
     * Record the flowering date and forecast the harvest date.
     */
    public function recordFlowering(Carbon $floweringDate): void
    {
        $this->flowering_date = $floweringDate;
        $this->expected_harvest_date = $floweringDate->copy()
            ->addDays($this->fruitVariety->days_to_harvest);
        $this->stage = CropCycleStage::Flowering;
        $this->save();
    }
}
```

- [ ] **Step 6: Create the factory**

`database/factories/CropCycleFactory.php`:

```php
<?php

namespace Database\Factories;

use App\Enums\CropCycleStage;
use App\Enums\CropCycleStatus;
use App\Models\CropCycle;
use App\Models\FruitVariety;
use App\Models\Plot;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CropCycle>
 */
class CropCycleFactory extends Factory
{
    public function definition(): array
    {
        $variety = FruitVariety::factory();

        return [
            'plot_id' => Plot::factory(),
            'fruit_variety_id' => $variety,
            'label' => 'รอบ '.fake()->numberBetween(2566, 2570),
            'stage' => CropCycleStage::SoilPrep,
            'status' => CropCycleStatus::Active,
            'flowering_date' => null,
            'expected_harvest_date' => null,
            'started_at' => now()->subMonths(2),
            'closed_at' => null,
            'recorded_by' => User::factory(),
            'notes' => null,
        ];
    }
}
```

- [ ] **Step 7: Run tests**

Run: `php artisan test --compact --filter="CropCycleModelTest|PlotModelTest"`
Expected: PASS (all model tests including Plot's `activeCropCycle` now resolvable)

- [ ] **Step 8: Format and commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Enums database/migrations app/Models/CropCycle.php database/factories/CropCycleFactory.php tests/Feature/Farm/CropCycleModelTest.php
git commit -m "feat: add CropCycle model with harvest-date forecast"
```

---

## Task 6: Fruit type & variety management (backend + UI)

**Files:**
- Create: `app/Http/Requests/Farm/StoreFruitTypeRequest.php`, `UpdateFruitTypeRequest.php`, `StoreFruitVarietyRequest.php`, `UpdateFruitVarietyRequest.php`
- Create: `app/Http/Controllers/Farm/FruitTypeController.php`, `FruitVarietyController.php`
- Modify: `routes/web.php`
- Create: `resources/js/types/farm.ts`
- Create: `resources/js/pages/farm/fruit-types/index.tsx`, `resources/js/pages/farm/fruit-varieties/index.tsx`
- Test: `tests/Feature/Farm/FruitTypeControllerTest.php`, `tests/Feature/Farm/FruitVarietyControllerTest.php`

- [ ] **Step 1: Write the failing controller tests**

Create with `php artisan make:test --pest Farm/FruitTypeControllerTest`, then:

```php
<?php

use App\Models\FruitType;
use App\Models\User;

test('guests cannot view fruit types', function () {
    $this->get(route('fruit-types.index'))->assertRedirect(route('login'));
});

test('a user can view the fruit types page', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('fruit-types.index'))
        ->assertOk();
});

test('a user can create a fruit type', function () {
    $this->actingAs(User::factory()->create())
        ->post(route('fruit-types.store'), ['name' => 'ทุเรียน'])
        ->assertRedirect(route('fruit-types.index'));

    expect(FruitType::where('name', 'ทุเรียน')->exists())->toBeTrue();
});

test('a fruit type name is required', function () {
    $this->actingAs(User::factory()->create())
        ->from(route('fruit-types.index'))
        ->post(route('fruit-types.store'), ['name' => ''])
        ->assertSessionHasErrors('name');
});

test('a user can update a fruit type', function () {
    $type = FruitType::factory()->create(['name' => 'มะม่วง']);

    $this->actingAs(User::factory()->create())
        ->put(route('fruit-types.update', $type), ['name' => 'มะม่วงเขียวเสวย'])
        ->assertRedirect(route('fruit-types.index'));

    expect($type->refresh()->name)->toBe('มะม่วงเขียวเสวย');
});

test('a user can delete a fruit type', function () {
    $type = FruitType::factory()->create();

    $this->actingAs(User::factory()->create())
        ->delete(route('fruit-types.destroy', $type))
        ->assertRedirect(route('fruit-types.index'));

    expect(FruitType::find($type->id))->toBeNull();
});
```

Create with `php artisan make:test --pest Farm/FruitVarietyControllerTest`, then:

```php
<?php

use App\Models\FruitType;
use App\Models\FruitVariety;
use App\Models\User;

test('a user can view the varieties page', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('fruit-varieties.index'))
        ->assertOk();
});

test('a user can create a variety', function () {
    $type = FruitType::factory()->create();

    $this->actingAs(User::factory()->create())
        ->post(route('fruit-varieties.store'), [
            'fruit_type_id' => $type->id,
            'name' => 'หมอนทอง',
            'days_to_harvest' => 135,
        ])
        ->assertRedirect(route('fruit-varieties.index'));

    expect(FruitVariety::where('name', 'หมอนทอง')->exists())->toBeTrue();
});

test('variety validation requires type, name and positive days', function () {
    $this->actingAs(User::factory()->create())
        ->from(route('fruit-varieties.index'))
        ->post(route('fruit-varieties.store'), [
            'fruit_type_id' => 999,
            'name' => '',
            'days_to_harvest' => 0,
        ])
        ->assertSessionHasErrors(['fruit_type_id', 'name', 'days_to_harvest']);
});

test('a user can update a variety', function () {
    $variety = FruitVariety::factory()->create(['days_to_harvest' => 100]);

    $this->actingAs(User::factory()->create())
        ->put(route('fruit-varieties.update', $variety), [
            'fruit_type_id' => $variety->fruit_type_id,
            'name' => $variety->name,
            'days_to_harvest' => 120,
        ])
        ->assertRedirect(route('fruit-varieties.index'));

    expect($variety->refresh()->days_to_harvest)->toBe(120);
});

test('a user can delete a variety', function () {
    $variety = FruitVariety::factory()->create();

    $this->actingAs(User::factory()->create())
        ->delete(route('fruit-varieties.destroy', $variety))
        ->assertRedirect(route('fruit-varieties.index'));

    expect(FruitVariety::find($variety->id))->toBeNull();
});
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `php artisan test --compact --filter="FruitTypeControllerTest|FruitVarietyControllerTest"`
Expected: FAIL ("Route [fruit-types.index] not defined")

- [ ] **Step 3: Create the FormRequests**

`app/Http/Requests/Farm/StoreFruitTypeRequest.php`:

```php
<?php

namespace App\Http\Requests\Farm;

use Illuminate\Foundation\Http\FormRequest;

class StoreFruitTypeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
        ];
    }
}
```

`app/Http/Requests/Farm/UpdateFruitTypeRequest.php` — identical body but class name `UpdateFruitTypeRequest`:

```php
<?php

namespace App\Http\Requests\Farm;

use Illuminate\Foundation\Http\FormRequest;

class UpdateFruitTypeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
        ];
    }
}
```

`app/Http/Requests/Farm/StoreFruitVarietyRequest.php`:

```php
<?php

namespace App\Http\Requests\Farm;

use Illuminate\Foundation\Http\FormRequest;

class StoreFruitVarietyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'fruit_type_id' => ['required', 'exists:fruit_types,id'],
            'name' => ['required', 'string', 'max:255'],
            'days_to_harvest' => ['required', 'integer', 'min:1'],
        ];
    }
}
```

`app/Http/Requests/Farm/UpdateFruitVarietyRequest.php` — same rules, class name `UpdateFruitVarietyRequest`:

```php
<?php

namespace App\Http\Requests\Farm;

use Illuminate\Foundation\Http\FormRequest;

class UpdateFruitVarietyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'fruit_type_id' => ['required', 'exists:fruit_types,id'],
            'name' => ['required', 'string', 'max:255'],
            'days_to_harvest' => ['required', 'integer', 'min:1'],
        ];
    }
}
```

- [ ] **Step 4: Create the controllers**

`app/Http/Controllers/Farm/FruitTypeController.php`:

```php
<?php

namespace App\Http\Controllers\Farm;

use App\Http\Controllers\Controller;
use App\Http\Requests\Farm\StoreFruitTypeRequest;
use App\Http\Requests\Farm\UpdateFruitTypeRequest;
use App\Models\FruitType;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class FruitTypeController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('farm/fruit-types/index', [
            'fruitTypes' => FruitType::withCount('varieties')->orderBy('name')->get(),
        ]);
    }

    public function store(StoreFruitTypeRequest $request): RedirectResponse
    {
        FruitType::create($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => 'เพิ่มชนิดผลไม้แล้ว']);

        return to_route('fruit-types.index');
    }

    public function update(UpdateFruitTypeRequest $request, FruitType $fruitType): RedirectResponse
    {
        $fruitType->update($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => 'อัปเดตชนิดผลไม้แล้ว']);

        return to_route('fruit-types.index');
    }

    public function destroy(FruitType $fruitType): RedirectResponse
    {
        $fruitType->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => 'ลบชนิดผลไม้แล้ว']);

        return to_route('fruit-types.index');
    }
}
```

`app/Http/Controllers/Farm/FruitVarietyController.php`:

```php
<?php

namespace App\Http\Controllers\Farm;

use App\Http\Controllers\Controller;
use App\Http\Requests\Farm\StoreFruitVarietyRequest;
use App\Http\Requests\Farm\UpdateFruitVarietyRequest;
use App\Models\FruitType;
use App\Models\FruitVariety;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class FruitVarietyController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('farm/fruit-varieties/index', [
            'fruitVarieties' => FruitVariety::with('fruitType')->orderBy('name')->get(),
            'fruitTypes' => FruitType::orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function store(StoreFruitVarietyRequest $request): RedirectResponse
    {
        FruitVariety::create($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => 'เพิ่มพันธุ์แล้ว']);

        return to_route('fruit-varieties.index');
    }

    public function update(UpdateFruitVarietyRequest $request, FruitVariety $fruitVariety): RedirectResponse
    {
        $fruitVariety->update($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => 'อัปเดตพันธุ์แล้ว']);

        return to_route('fruit-varieties.index');
    }

    public function destroy(FruitVariety $fruitVariety): RedirectResponse
    {
        $fruitVariety->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => 'ลบพันธุ์แล้ว']);

        return to_route('fruit-varieties.index');
    }
}
```

- [ ] **Step 5: Register routes**

In `routes/web.php`, add inside the existing `auth`+`verified` middleware group (after the dashboard route):

```php
use App\Http\Controllers\Farm\FruitTypeController;
use App\Http\Controllers\Farm\FruitVarietyController;

Route::resource('fruit-types', FruitTypeController::class)
    ->only(['index', 'store', 'update', 'destroy']);
Route::resource('fruit-varieties', FruitVarietyController::class)
    ->only(['index', 'store', 'update', 'destroy']);
```

- [ ] **Step 6: Generate Wayfinder helpers**

Run: `php artisan wayfinder:generate`
Expected: regenerates `resources/js/actions/**` and `resources/js/routes/**`

- [ ] **Step 7: Run backend tests to verify they pass**

Run: `php artisan test --compact --filter="FruitTypeControllerTest|FruitVarietyControllerTest"`
Expected: PASS (11 tests)

- [ ] **Step 8: Create shared TS types**

`resources/js/types/farm.ts`:

```ts
export type FruitType = {
    id: number;
    name: string;
    varieties_count?: number;
    created_at: string | null;
    updated_at: string | null;
};

export type FruitVariety = {
    id: number;
    fruit_type_id: number;
    name: string;
    days_to_harvest: number;
    fruit_type?: FruitType;
};

export type CropCycleStage =
    | 'soil_prep'
    | 'flowering'
    | 'fruiting'
    | 'ready_to_harvest'
    | 'harvested';

export type CropCycleStatus = 'active' | 'closed';

export type CropCycle = {
    id: number;
    plot_id: number;
    fruit_variety_id: number;
    label: string;
    stage: CropCycleStage;
    status: CropCycleStatus;
    flowering_date: string | null;
    expected_harvest_date: string | null;
    started_at: string;
    closed_at: string | null;
    notes: string | null;
};

export type Plot = {
    id: number;
    name: string;
    fruit_variety_id: number;
    tree_count: number;
    planted_at: string | null;
    area_rai: string | null;
    notes: string | null;
    tree_age_years: number | null;
    fruit_variety?: FruitVariety;
    active_crop_cycle?: CropCycle | null;
    crop_cycles?: CropCycle[];
};

export const cropCycleStageLabels: Record<CropCycleStage, string> = {
    soil_prep: 'บำรุงดิน',
    flowering: 'ออกดอก',
    fruiting: 'ติดผล',
    ready_to_harvest: 'พร้อมเก็บเกี่ยว',
    harvested: 'เก็บเกี่ยวแล้ว',
};
```

- [ ] **Step 9: Create the fruit types page**

`resources/js/pages/farm/fruit-types/index.tsx`:

```tsx
import { Form, Head, router } from '@inertiajs/react';
import FruitTypeController from '@/actions/App/Http/Controllers/Farm/FruitTypeController';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { index } from '@/routes/fruit-types';
import type { FruitType } from '@/types/farm';

type PageProps = {
    fruitTypes: FruitType[];
};

export default function FruitTypesIndex({ fruitTypes }: { fruitTypes: FruitType[] }) {
    return (
        <div className="flex h-full flex-1 flex-col gap-6 p-4">
            <Head title="ชนิดผลไม้" />
            <Heading title="ชนิดผลไม้" description="จัดการชนิดผลไม้ในฟาร์ม" />

            <Card className="p-4">
                <Form {...FruitTypeController.store.form()} options={{ preserveScroll: true }} resetOnSuccess className="flex items-end gap-3">
                    {({ processing, errors }) => (
                        <>
                            <div className="grid flex-1 gap-2">
                                <Label htmlFor="name">ชื่อชนิดผลไม้</Label>
                                <Input id="name" name="name" required placeholder="เช่น ทุเรียน" />
                                <InputError message={errors.name} />
                            </div>
                            <Button disabled={processing}>เพิ่ม</Button>
                        </>
                    )}
                </Form>
            </Card>

            <div className="grid gap-3">
                {fruitTypes.length === 0 && (
                    <p className="text-muted-foreground text-sm">ยังไม่มีชนิดผลไม้</p>
                )}
                {fruitTypes.map((type) => (
                    <Card key={type.id} className="flex items-center justify-between p-4">
                        <div>
                            <p className="font-medium">{type.name}</p>
                            <p className="text-muted-foreground text-sm">{type.varieties_count ?? 0} พันธุ์</p>
                        </div>
                        <Button
                            variant="destructive"
                            onClick={() => {
                                if (confirm('ลบชนิดผลไม้นี้?')) {
                                    router.delete(FruitTypeController.destroy.url(type.id));
                                }
                            }}
                        >
                            ลบ
                        </Button>
                    </Card>
                ))}
            </div>
        </div>
    );
}

FruitTypesIndex.layout = {
    breadcrumbs: [{ title: 'ชนิดผลไม้', href: index() }],
};

export type { PageProps };
```

- [ ] **Step 10: Create the fruit varieties page**

`resources/js/pages/farm/fruit-varieties/index.tsx`:

```tsx
import { Form, Head, router } from '@inertiajs/react';
import FruitVarietyController from '@/actions/App/Http/Controllers/Farm/FruitVarietyController';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { index } from '@/routes/fruit-varieties';
import type { FruitType, FruitVariety } from '@/types/farm';

export default function FruitVarietiesIndex({
    fruitVarieties,
    fruitTypes,
}: {
    fruitVarieties: FruitVariety[];
    fruitTypes: Pick<FruitType, 'id' | 'name'>[];
}) {
    return (
        <div className="flex h-full flex-1 flex-col gap-6 p-4">
            <Head title="พันธุ์ผลไม้" />
            <Heading title="พันธุ์ผลไม้" description="กำหนดพันธุ์และจำนวนวันมาตรฐานจากดอกบานถึงเก็บเกี่ยว" />

            <Card className="p-4">
                <Form {...FruitVarietyController.store.form()} options={{ preserveScroll: true }} resetOnSuccess className="grid gap-3 sm:grid-cols-4 sm:items-end">
                    {({ processing, errors }) => (
                        <>
                            <div className="grid gap-2">
                                <Label htmlFor="fruit_type_id">ชนิด</Label>
                                <Select name="fruit_type_id">
                                    <SelectTrigger id="fruit_type_id">
                                        <SelectValue placeholder="เลือกชนิด" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {fruitTypes.map((type) => (
                                            <SelectItem key={type.id} value={String(type.id)}>
                                                {type.name}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                                <InputError message={errors.fruit_type_id} />
                            </div>
                            <div className="grid gap-2">
                                <Label htmlFor="name">ชื่อพันธุ์</Label>
                                <Input id="name" name="name" required placeholder="เช่น หมอนทอง" />
                                <InputError message={errors.name} />
                            </div>
                            <div className="grid gap-2">
                                <Label htmlFor="days_to_harvest">วันถึงเก็บเกี่ยว</Label>
                                <Input id="days_to_harvest" name="days_to_harvest" type="number" min={1} required placeholder="135" />
                                <InputError message={errors.days_to_harvest} />
                            </div>
                            <Button disabled={processing}>เพิ่ม</Button>
                        </>
                    )}
                </Form>
            </Card>

            <div className="grid gap-3">
                {fruitVarieties.length === 0 && (
                    <p className="text-muted-foreground text-sm">ยังไม่มีพันธุ์</p>
                )}
                {fruitVarieties.map((variety) => (
                    <Card key={variety.id} className="flex items-center justify-between p-4">
                        <div>
                            <p className="font-medium">
                                {variety.name}
                                <span className="text-muted-foreground ml-2 text-sm">
                                    ({variety.fruit_type?.name})
                                </span>
                            </p>
                            <p className="text-muted-foreground text-sm">
                                {variety.days_to_harvest} วันจากดอกบานถึงเก็บเกี่ยว
                            </p>
                        </div>
                        <Button
                            variant="destructive"
                            onClick={() => {
                                if (confirm('ลบพันธุ์นี้?')) {
                                    router.delete(FruitVarietyController.destroy.url(variety.id));
                                }
                            }}
                        >
                            ลบ
                        </Button>
                    </Card>
                ))}
            </div>
        </div>
    );
}

FruitVarietiesIndex.layout = {
    breadcrumbs: [{ title: 'พันธุ์ผลไม้', href: index() }],
};
```

- [ ] **Step 11: Build the frontend**

Run: `npm run build`
Expected: build succeeds with no TypeScript errors

- [ ] **Step 12: Format and commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Http/Requests/Farm app/Http/Controllers/Farm routes/web.php resources/js/types/farm.ts resources/js/pages/farm/fruit-types resources/js/pages/farm/fruit-varieties resources/js/actions resources/js/routes tests/Feature/Farm/FruitTypeControllerTest.php tests/Feature/Farm/FruitVarietyControllerTest.php
git commit -m "feat: add fruit type and variety management"
```

---

## Task 7: Plot management (backend + UI)

**Files:**
- Create: `app/Http/Requests/Farm/StorePlotRequest.php`, `UpdatePlotRequest.php`
- Create: `app/Http/Controllers/Farm/PlotController.php`
- Modify: `routes/web.php`
- Create: `resources/js/pages/farm/plots/index.tsx`, `create.tsx`, `edit.tsx`, `show.tsx`
- Test: `tests/Feature/Farm/PlotControllerTest.php`

- [ ] **Step 1: Write the failing controller test**

Create with `php artisan make:test --pest Farm/PlotControllerTest`, then:

```php
<?php

use App\Models\FruitVariety;
use App\Models\Plot;
use App\Models\User;

test('a user can view the plots list', function () {
    Plot::factory()->count(2)->create();

    $this->actingAs(User::factory()->create())
        ->get(route('plots.index'))
        ->assertOk();
});

test('a user can view the create plot page', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('plots.create'))
        ->assertOk();
});

test('a user can create a plot', function () {
    $variety = FruitVariety::factory()->create();

    $this->actingAs(User::factory()->create())
        ->post(route('plots.store'), [
            'name' => 'แปลงทุเรียนทิศเหนือ',
            'fruit_variety_id' => $variety->id,
            'tree_count' => 50,
            'planted_at' => '2020-05-01',
            'area_rai' => 5.5,
            'notes' => null,
        ])
        ->assertRedirect(route('plots.index'));

    expect(Plot::where('name', 'แปลงทุเรียนทิศเหนือ')->exists())->toBeTrue();
});

test('plot validation requires name, variety and tree count', function () {
    $this->actingAs(User::factory()->create())
        ->from(route('plots.create'))
        ->post(route('plots.store'), [
            'name' => '',
            'fruit_variety_id' => 999,
            'tree_count' => 0,
        ])
        ->assertSessionHasErrors(['name', 'fruit_variety_id', 'tree_count']);
});

test('a user can view a single plot', function () {
    $plot = Plot::factory()->create();

    $this->actingAs(User::factory()->create())
        ->get(route('plots.show', $plot))
        ->assertOk();
});

test('a user can update a plot', function () {
    $plot = Plot::factory()->create(['tree_count' => 10]);

    $this->actingAs(User::factory()->create())
        ->put(route('plots.update', $plot), [
            'name' => $plot->name,
            'fruit_variety_id' => $plot->fruit_variety_id,
            'tree_count' => 80,
            'planted_at' => null,
            'area_rai' => null,
            'notes' => null,
        ])
        ->assertRedirect(route('plots.show', $plot));

    expect($plot->refresh()->tree_count)->toBe(80);
});

test('a user can delete a plot', function () {
    $plot = Plot::factory()->create();

    $this->actingAs(User::factory()->create())
        ->delete(route('plots.destroy', $plot))
        ->assertRedirect(route('plots.index'));

    expect(Plot::find($plot->id))->toBeNull();
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --compact --filter=PlotControllerTest`
Expected: FAIL ("Route [plots.index] not defined")

- [ ] **Step 3: Create the FormRequests**

`app/Http/Requests/Farm/StorePlotRequest.php`:

```php
<?php

namespace App\Http\Requests\Farm;

use Illuminate\Foundation\Http\FormRequest;

class StorePlotRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'fruit_variety_id' => ['required', 'exists:fruit_varieties,id'],
            'tree_count' => ['required', 'integer', 'min:1'],
            'planted_at' => ['nullable', 'date'],
            'area_rai' => ['nullable', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string'],
        ];
    }
}
```

`app/Http/Requests/Farm/UpdatePlotRequest.php` — same rules, class name `UpdatePlotRequest`:

```php
<?php

namespace App\Http\Requests\Farm;

use Illuminate\Foundation\Http\FormRequest;

class UpdatePlotRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'fruit_variety_id' => ['required', 'exists:fruit_varieties,id'],
            'tree_count' => ['required', 'integer', 'min:1'],
            'planted_at' => ['nullable', 'date'],
            'area_rai' => ['nullable', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string'],
        ];
    }
}
```

- [ ] **Step 4: Create the controller**

`app/Http/Controllers/Farm/PlotController.php`:

```php
<?php

namespace App\Http\Controllers\Farm;

use App\Http\Controllers\Controller;
use App\Http\Requests\Farm\StorePlotRequest;
use App\Http\Requests\Farm\UpdatePlotRequest;
use App\Models\FruitVariety;
use App\Models\Plot;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class PlotController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('farm/plots/index', [
            'plots' => Plot::with(['fruitVariety.fruitType', 'activeCropCycle'])
                ->orderBy('name')
                ->get()
                ->append('tree_age_years'),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('farm/plots/create', [
            'fruitVarieties' => FruitVariety::with('fruitType')->orderBy('name')->get(),
        ]);
    }

    public function store(StorePlotRequest $request): RedirectResponse
    {
        Plot::create($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => 'เพิ่มแปลงแล้ว']);

        return to_route('plots.index');
    }

    public function show(Plot $plot): Response
    {
        $plot->load(['fruitVariety.fruitType', 'cropCycles' => fn ($q) => $q->latest('started_at')]);
        $plot->append('tree_age_years');

        return Inertia::render('farm/plots/show', [
            'plot' => $plot,
        ]);
    }

    public function edit(Plot $plot): Response
    {
        return Inertia::render('farm/plots/edit', [
            'plot' => $plot,
            'fruitVarieties' => FruitVariety::with('fruitType')->orderBy('name')->get(),
        ]);
    }

    public function update(UpdatePlotRequest $request, Plot $plot): RedirectResponse
    {
        $plot->update($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => 'อัปเดตแปลงแล้ว']);

        return to_route('plots.show', $plot);
    }

    public function destroy(Plot $plot): RedirectResponse
    {
        $plot->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => 'ลบแปลงแล้ว']);

        return to_route('plots.index');
    }
}
```

- [ ] **Step 5: Register routes**

In `routes/web.php`, add inside the `auth`+`verified` group:

```php
use App\Http\Controllers\Farm\PlotController;

Route::resource('plots', PlotController::class);
```

- [ ] **Step 6: Generate Wayfinder helpers**

Run: `php artisan wayfinder:generate`
Expected: regenerates plot route/action helpers

- [ ] **Step 7: Run backend tests to verify they pass**

Run: `php artisan test --compact --filter=PlotControllerTest`
Expected: PASS (8 tests)

- [ ] **Step 8: Create the plots index page**

`resources/js/pages/farm/plots/index.tsx`:

```tsx
import { Head, Link } from '@inertiajs/react';
import Heading from '@/components/heading';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { create, index, show } from '@/routes/plots';
import { cropCycleStageLabels, type Plot } from '@/types/farm';

export default function PlotsIndex({ plots }: { plots: Plot[] }) {
    return (
        <div className="flex h-full flex-1 flex-col gap-6 p-4">
            <Head title="แปลงผลไม้" />
            <div className="flex items-center justify-between">
                <Heading title="แปลงผลไม้" description="จัดการแปลงในฟาร์ม" />
                <Button asChild>
                    <Link href={create()}>เพิ่มแปลง</Link>
                </Button>
            </div>

            <div className="grid gap-3 md:grid-cols-2">
                {plots.length === 0 && (
                    <p className="text-muted-foreground text-sm">ยังไม่มีแปลง — กดเพิ่มแปลงเพื่อเริ่มต้น</p>
                )}
                {plots.map((plot) => (
                    <Link key={plot.id} href={show(plot.id)}>
                        <Card className="p-4 transition hover:border-foreground/20">
                            <div className="flex items-start justify-between">
                                <div>
                                    <p className="font-medium">{plot.name}</p>
                                    <p className="text-muted-foreground text-sm">
                                        {plot.fruit_variety?.fruit_type?.name} · {plot.fruit_variety?.name}
                                    </p>
                                </div>
                                {plot.active_crop_cycle && (
                                    <Badge variant="secondary">
                                        {cropCycleStageLabels[plot.active_crop_cycle.stage]}
                                    </Badge>
                                )}
                            </div>
                            <div className="text-muted-foreground mt-3 flex gap-4 text-sm">
                                <span>{plot.tree_count} ต้น</span>
                                {plot.tree_age_years !== null && <span>อายุ {plot.tree_age_years} ปี</span>}
                                {plot.area_rai && <span>{plot.area_rai} ไร่</span>}
                            </div>
                        </Card>
                    </Link>
                ))}
            </div>
        </div>
    );
}

PlotsIndex.layout = {
    breadcrumbs: [{ title: 'แปลงผลไม้', href: index() }],
};
```

- [ ] **Step 9: Create a reusable plot form, then the create page**

`resources/js/pages/farm/plots/create.tsx`:

```tsx
import { Form, Head } from '@inertiajs/react';
import PlotController from '@/actions/App/Http/Controllers/Farm/PlotController';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { index } from '@/routes/plots';
import type { FruitVariety } from '@/types/farm';

export default function PlotCreate({ fruitVarieties }: { fruitVarieties: FruitVariety[] }) {
    return (
        <div className="flex h-full flex-1 flex-col gap-6 p-4">
            <Head title="เพิ่มแปลง" />
            <Heading title="เพิ่มแปลง" description="บันทึกข้อมูลแปลงผลไม้" />

            <Card className="max-w-2xl p-4">
                <Form {...PlotController.store.form()} className="space-y-6">
                    {({ processing, errors }) => (
                        <>
                            <div className="grid gap-2">
                                <Label htmlFor="name">ชื่อแปลง</Label>
                                <Input id="name" name="name" required placeholder="เช่น แปลงทุเรียนทิศเหนือ" />
                                <InputError message={errors.name} />
                            </div>
                            <div className="grid gap-2">
                                <Label htmlFor="fruit_variety_id">พันธุ์</Label>
                                <Select name="fruit_variety_id">
                                    <SelectTrigger id="fruit_variety_id">
                                        <SelectValue placeholder="เลือกพันธุ์" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {fruitVarieties.map((variety) => (
                                            <SelectItem key={variety.id} value={String(variety.id)}>
                                                {variety.fruit_type?.name} · {variety.name}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                                <InputError message={errors.fruit_variety_id} />
                            </div>
                            <div className="grid gap-2 sm:grid-cols-3">
                                <div className="grid gap-2">
                                    <Label htmlFor="tree_count">จำนวนต้น</Label>
                                    <Input id="tree_count" name="tree_count" type="number" min={1} required />
                                    <InputError message={errors.tree_count} />
                                </div>
                                <div className="grid gap-2">
                                    <Label htmlFor="planted_at">วันที่ปลูก</Label>
                                    <Input id="planted_at" name="planted_at" type="date" />
                                    <InputError message={errors.planted_at} />
                                </div>
                                <div className="grid gap-2">
                                    <Label htmlFor="area_rai">พื้นที่ (ไร่)</Label>
                                    <Input id="area_rai" name="area_rai" type="number" step="0.01" min={0} />
                                    <InputError message={errors.area_rai} />
                                </div>
                            </div>
                            <Button disabled={processing}>บันทึก</Button>
                        </>
                    )}
                </Form>
            </Card>
        </div>
    );
}

PlotCreate.layout = {
    breadcrumbs: [
        { title: 'แปลงผลไม้', href: index() },
        { title: 'เพิ่มแปลง', href: index() },
    ],
};
```

- [ ] **Step 10: Create the edit page**

`resources/js/pages/farm/plots/edit.tsx`:

```tsx
import { Form, Head } from '@inertiajs/react';
import PlotController from '@/actions/App/Http/Controllers/Farm/PlotController';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { index, show } from '@/routes/plots';
import type { FruitVariety, Plot } from '@/types/farm';

export default function PlotEdit({ plot, fruitVarieties }: { plot: Plot; fruitVarieties: FruitVariety[] }) {
    return (
        <div className="flex h-full flex-1 flex-col gap-6 p-4">
            <Head title="แก้ไขแปลง" />
            <Heading title="แก้ไขแปลง" description={plot.name} />

            <Card className="max-w-2xl p-4">
                <Form {...PlotController.update.form(plot.id)} className="space-y-6">
                    {({ processing, errors }) => (
                        <>
                            <div className="grid gap-2">
                                <Label htmlFor="name">ชื่อแปลง</Label>
                                <Input id="name" name="name" required defaultValue={plot.name} />
                                <InputError message={errors.name} />
                            </div>
                            <div className="grid gap-2">
                                <Label htmlFor="fruit_variety_id">พันธุ์</Label>
                                <Select name="fruit_variety_id" defaultValue={String(plot.fruit_variety_id)}>
                                    <SelectTrigger id="fruit_variety_id">
                                        <SelectValue placeholder="เลือกพันธุ์" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {fruitVarieties.map((variety) => (
                                            <SelectItem key={variety.id} value={String(variety.id)}>
                                                {variety.fruit_type?.name} · {variety.name}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                                <InputError message={errors.fruit_variety_id} />
                            </div>
                            <div className="grid gap-2 sm:grid-cols-3">
                                <div className="grid gap-2">
                                    <Label htmlFor="tree_count">จำนวนต้น</Label>
                                    <Input id="tree_count" name="tree_count" type="number" min={1} required defaultValue={plot.tree_count} />
                                    <InputError message={errors.tree_count} />
                                </div>
                                <div className="grid gap-2">
                                    <Label htmlFor="planted_at">วันที่ปลูก</Label>
                                    <Input id="planted_at" name="planted_at" type="date" defaultValue={plot.planted_at ?? ''} />
                                    <InputError message={errors.planted_at} />
                                </div>
                                <div className="grid gap-2">
                                    <Label htmlFor="area_rai">พื้นที่ (ไร่)</Label>
                                    <Input id="area_rai" name="area_rai" type="number" step="0.01" min={0} defaultValue={plot.area_rai ?? ''} />
                                    <InputError message={errors.area_rai} />
                                </div>
                            </div>
                            <Button disabled={processing}>บันทึก</Button>
                        </>
                    )}
                </Form>
            </Card>
        </div>
    );
}

PlotEdit.layout = {
    breadcrumbs: [
        { title: 'แปลงผลไม้', href: index() },
        { title: 'แก้ไขแปลง', href: index() },
    ],
};
```

- [ ] **Step 11: Create the show page (with crop-cycle list placeholder for Task 8)**

`resources/js/pages/farm/plots/show.tsx`:

```tsx
import { Head, Link, router } from '@inertiajs/react';
import PlotController from '@/actions/App/Http/Controllers/Farm/PlotController';
import Heading from '@/components/heading';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { edit, index } from '@/routes/plots';
import { cropCycleStageLabels, type Plot } from '@/types/farm';

export default function PlotShow({ plot }: { plot: Plot }) {
    return (
        <div className="flex h-full flex-1 flex-col gap-6 p-4">
            <Head title={plot.name} />
            <div className="flex items-center justify-between">
                <Heading
                    title={plot.name}
                    description={`${plot.fruit_variety?.fruit_type?.name} · ${plot.fruit_variety?.name}`}
                />
                <div className="flex gap-2">
                    <Button asChild variant="outline">
                        <Link href={edit(plot.id)}>แก้ไข</Link>
                    </Button>
                    <Button
                        variant="destructive"
                        onClick={() => {
                            if (confirm('ลบแปลงนี้?')) {
                                router.delete(PlotController.destroy.url(plot.id));
                            }
                        }}
                    >
                        ลบ
                    </Button>
                </div>
            </div>

            <div className="grid gap-3 sm:grid-cols-3">
                <Card className="p-4">
                    <p className="text-muted-foreground text-sm">จำนวนต้น</p>
                    <p className="text-2xl font-semibold">{plot.tree_count}</p>
                </Card>
                <Card className="p-4">
                    <p className="text-muted-foreground text-sm">อายุต้นไม้</p>
                    <p className="text-2xl font-semibold">
                        {plot.tree_age_years !== null ? `${plot.tree_age_years} ปี` : '—'}
                    </p>
                </Card>
                <Card className="p-4">
                    <p className="text-muted-foreground text-sm">พื้นที่</p>
                    <p className="text-2xl font-semibold">{plot.area_rai ? `${plot.area_rai} ไร่` : '—'}</p>
                </Card>
            </div>

            <Card className="p-4">
                <p className="mb-3 font-medium">รอบการผลิต</p>
                {(plot.crop_cycles?.length ?? 0) === 0 ? (
                    <p className="text-muted-foreground text-sm">ยังไม่มีรอบการผลิต</p>
                ) : (
                    <div className="grid gap-2">
                        {plot.crop_cycles?.map((cycle) => (
                            <div key={cycle.id} className="flex items-center justify-between rounded-md border p-3">
                                <div>
                                    <p className="font-medium">{cycle.label}</p>
                                    <p className="text-muted-foreground text-sm">
                                        {cycle.expected_harvest_date
                                            ? `คาดเก็บเกี่ยว ${cycle.expected_harvest_date}`
                                            : 'ยังไม่บันทึกวันดอกบาน'}
                                    </p>
                                </div>
                                <Badge variant="secondary">{cropCycleStageLabels[cycle.stage]}</Badge>
                            </div>
                        ))}
                    </div>
                )}
            </Card>
        </div>
    );
}

PlotShow.layout = {
    breadcrumbs: [
        { title: 'แปลงผลไม้', href: index() },
    ],
};
```

- [ ] **Step 12: Build the frontend**

Run: `npm run build`
Expected: build succeeds with no TypeScript errors

- [ ] **Step 13: Format and commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Http/Requests/Farm app/Http/Controllers/Farm/PlotController.php routes/web.php resources/js/pages/farm/plots resources/js/actions resources/js/routes tests/Feature/Farm/PlotControllerTest.php
git commit -m "feat: add plot management CRUD"
```

---

## Task 8: Crop cycle management (create + record flowering + stage)

**Files:**
- Create: `app/Http/Requests/Farm/StoreCropCycleRequest.php`, `UpdateCropCycleRequest.php`
- Create: `app/Http/Controllers/Farm/CropCycleController.php`
- Modify: `routes/web.php`
- Modify: `resources/js/pages/farm/plots/show.tsx` (add create-cycle + record-flowering forms)
- Test: `tests/Feature/Farm/CropCycleControllerTest.php`

- [ ] **Step 1: Write the failing controller test**

Create with `php artisan make:test --pest Farm/CropCycleControllerTest`, then:

```php
<?php

use App\Enums\CropCycleStage;
use App\Models\CropCycle;
use App\Models\FruitVariety;
use App\Models\Plot;
use App\Models\User;

test('a user can create a crop cycle for a plot', function () {
    $variety = FruitVariety::factory()->create();
    $plot = Plot::factory()->for($variety)->create();
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('plots.crop-cycles.store', $plot), [
            'label' => 'รอบ 2569',
            'started_at' => '2026-01-01',
        ])
        ->assertRedirect(route('plots.show', $plot));

    $cycle = CropCycle::first();
    expect($cycle->plot_id)->toBe($plot->id);
    expect($cycle->fruit_variety_id)->toBe($variety->id);
    expect($cycle->recorded_by)->toBe($user->id);
});

test('creating a cycle requires a label and start date', function () {
    $plot = Plot::factory()->create();

    $this->actingAs(User::factory()->create())
        ->from(route('plots.show', $plot))
        ->post(route('plots.crop-cycles.store', $plot), [
            'label' => '',
            'started_at' => '',
        ])
        ->assertSessionHasErrors(['label', 'started_at']);
});

test('recording flowering forecasts the harvest date', function () {
    $variety = FruitVariety::factory()->create(['days_to_harvest' => 120]);
    $plot = Plot::factory()->for($variety)->create();
    $cycle = CropCycle::factory()->for($plot)->for($variety)->create();

    $this->actingAs(User::factory()->create())
        ->patch(route('crop-cycles.update', $cycle), [
            'flowering_date' => '2026-01-01',
        ])
        ->assertRedirect(route('plots.show', $plot));

    $cycle->refresh();
    expect($cycle->flowering_date->toDateString())->toBe('2026-01-01');
    expect($cycle->expected_harvest_date->toDateString())->toBe('2026-05-01');
    expect($cycle->stage)->toBe(CropCycleStage::Flowering);
});

test('a user can update the stage of a cycle', function () {
    $cycle = CropCycle::factory()->create(['stage' => CropCycleStage::SoilPrep]);

    $this->actingAs(User::factory()->create())
        ->patch(route('crop-cycles.update', $cycle), [
            'stage' => CropCycleStage::Fruiting->value,
        ])
        ->assertRedirect(route('plots.show', $cycle->plot_id));

    expect($cycle->refresh()->stage)->toBe(CropCycleStage::Fruiting);
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --compact --filter=CropCycleControllerTest`
Expected: FAIL ("Route [plots.crop-cycles.store] not defined")

- [ ] **Step 3: Create the FormRequests**

`app/Http/Requests/Farm/StoreCropCycleRequest.php`:

```php
<?php

namespace App\Http\Requests\Farm;

use Illuminate\Foundation\Http\FormRequest;

class StoreCropCycleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'label' => ['required', 'string', 'max:255'],
            'started_at' => ['required', 'date'],
            'notes' => ['nullable', 'string'],
        ];
    }
}
```

`app/Http/Requests/Farm/UpdateCropCycleRequest.php`:

```php
<?php

namespace App\Http\Requests\Farm;

use App\Enums\CropCycleStage;
use App\Enums\CropCycleStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;

class UpdateCropCycleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'label' => ['sometimes', 'required', 'string', 'max:255'],
            'stage' => ['sometimes', new Enum(CropCycleStage::class)],
            'status' => ['sometimes', new Enum(CropCycleStatus::class)],
            'flowering_date' => ['sometimes', 'nullable', 'date'],
            'notes' => ['sometimes', 'nullable', 'string'],
        ];
    }
}
```

- [ ] **Step 4: Create the controller**

`app/Http/Controllers/Farm/CropCycleController.php`:

```php
<?php

namespace App\Http\Controllers\Farm;

use App\Http\Controllers\Controller;
use App\Http\Requests\Farm\StoreCropCycleRequest;
use App\Http\Requests\Farm\UpdateCropCycleRequest;
use App\Models\CropCycle;
use App\Models\Plot;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Carbon;
use Inertia\Inertia;

class CropCycleController extends Controller
{
    public function store(StoreCropCycleRequest $request, Plot $plot): RedirectResponse
    {
        $plot->cropCycles()->create([
            ...$request->validated(),
            'fruit_variety_id' => $plot->fruit_variety_id,
            'recorded_by' => $request->user()->id,
        ]);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'เพิ่มรอบการผลิตแล้ว']);

        return to_route('plots.show', $plot);
    }

    public function update(UpdateCropCycleRequest $request, CropCycle $cropCycle): RedirectResponse
    {
        $validated = $request->validated();

        if (array_key_exists('flowering_date', $validated) && $validated['flowering_date'] !== null) {
            $cropCycle->loadMissing('fruitVariety');
            $cropCycle->recordFlowering(Carbon::parse($validated['flowering_date']));
            unset($validated['flowering_date']);
        }

        if ($validated !== []) {
            $cropCycle->update($validated);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => 'อัปเดตรอบการผลิตแล้ว']);

        return to_route('plots.show', $cropCycle->plot_id);
    }
}
```

- [ ] **Step 5: Register routes**

In `routes/web.php`, add inside the `auth`+`verified` group:

```php
use App\Http\Controllers\Farm\CropCycleController;

Route::post('plots/{plot}/crop-cycles', [CropCycleController::class, 'store'])
    ->name('plots.crop-cycles.store');
Route::patch('crop-cycles/{cropCycle}', [CropCycleController::class, 'update'])
    ->name('crop-cycles.update');
```

- [ ] **Step 6: Generate Wayfinder helpers**

Run: `php artisan wayfinder:generate`
Expected: regenerates crop cycle route/action helpers

- [ ] **Step 7: Run backend tests to verify they pass**

Run: `php artisan test --compact --filter=CropCycleControllerTest`
Expected: PASS (4 tests)

- [ ] **Step 8: Add cycle forms to the plot show page**

In `resources/js/pages/farm/plots/show.tsx`, add these imports at the top:

```tsx
import { Form } from '@inertiajs/react';
import CropCycleController from '@/actions/App/Http/Controllers/Farm/CropCycleController';
import InputError from '@/components/input-error';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
```

Replace the "ยังไม่มีรอบการผลิต" empty-state branch's surrounding `<Card>` content by appending a create-cycle form below the cycle list (inside the same `<Card className="p-4">`, after the cycles list block):

```tsx
                <div className="mt-4 border-t pt-4">
                    <p className="mb-3 text-sm font-medium">เพิ่มรอบการผลิตใหม่</p>
                    <Form
                        action={CropCycleController.store.url(plot.id)}
                        method="post"
                        options={{ preserveScroll: true }}
                        resetOnSuccess
                        className="grid gap-3 sm:grid-cols-3 sm:items-end"
                    >
                        {({ processing, errors }) => (
                            <>
                                <div className="grid gap-2">
                                    <Label htmlFor="label">ชื่อรอบ</Label>
                                    <Input id="label" name="label" required placeholder="เช่น รอบ 2569" />
                                    <InputError message={errors.label} />
                                </div>
                                <div className="grid gap-2">
                                    <Label htmlFor="started_at">วันเริ่มรอบ</Label>
                                    <Input id="started_at" name="started_at" type="date" required />
                                    <InputError message={errors.started_at} />
                                </div>
                                <Button disabled={processing}>เพิ่มรอบ</Button>
                            </>
                        )}
                    </Form>
                </div>
```

Then, inside the `crop_cycles?.map(...)` block, add a record-flowering form under each cycle row by wrapping each cycle in a column and appending this below the existing flex row (use `CropCycleController.update.form(cycle.id)` with method PATCH):

```tsx
                                <Form
                                    {...CropCycleController.update.form(cycle.id)}
                                    options={{ preserveScroll: true }}
                                    className="mt-2 flex items-end gap-2"
                                >
                                    {({ processing, errors }) => (
                                        <>
                                            <div className="grid gap-1">
                                                <Label htmlFor={`flowering_date_${cycle.id}`} className="text-xs">
                                                    วันดอกบาน
                                                </Label>
                                                <Input
                                                    id={`flowering_date_${cycle.id}`}
                                                    name="flowering_date"
                                                    type="date"
                                                    defaultValue={cycle.flowering_date ?? ''}
                                                />
                                                <InputError message={errors.flowering_date} />
                                            </div>
                                            <Button size="sm" variant="outline" disabled={processing}>
                                                บันทึก & คำนวณวันเก็บเกี่ยว
                                            </Button>
                                        </>
                                    )}
                                </Form>
```

Note: change the per-cycle wrapper `<div className="flex items-center justify-between rounded-md border p-3">` to `<div className="rounded-md border p-3">` and put the existing label/badge row inside an inner `<div className="flex items-center justify-between">` so the flowering form stacks beneath it.

- [ ] **Step 9: Build the frontend**

Run: `npm run build`
Expected: build succeeds with no TypeScript errors

- [ ] **Step 10: Format and commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Http/Requests/Farm app/Http/Controllers/Farm/CropCycleController.php routes/web.php resources/js/pages/farm/plots/show.tsx resources/js/actions resources/js/routes tests/Feature/Farm/CropCycleControllerTest.php
git commit -m "feat: add crop cycle creation and flowering forecast"
```

---

## Task 9: Sidebar navigation

**Files:**
- Modify: `resources/js/components/app-sidebar.tsx`

- [ ] **Step 1: Add farm nav items**

In `resources/js/components/app-sidebar.tsx`, update the imports and `mainNavItems`:

Add to the lucide import line: `Sprout, Trees, Leaf` (keep existing `LayoutGrid`, `BookOpen`, `FolderGit2`).

Add route imports:

```tsx
import { index as plotsIndex } from '@/routes/plots';
import { index as fruitTypesIndex } from '@/routes/fruit-types';
import { index as fruitVarietiesIndex } from '@/routes/fruit-varieties';
```

Replace `mainNavItems` with:

```tsx
const mainNavItems: NavItem[] = [
    {
        title: 'แดชบอร์ด',
        href: dashboard(),
        icon: LayoutGrid,
    },
    {
        title: 'แปลงผลไม้',
        href: plotsIndex(),
        icon: Trees,
    },
    {
        title: 'ชนิดผลไม้',
        href: fruitTypesIndex(),
        icon: Sprout,
    },
    {
        title: 'พันธุ์ผลไม้',
        href: fruitVarietiesIndex(),
        icon: Leaf,
    },
];
```

- [ ] **Step 2: Build the frontend**

Run: `npm run build`
Expected: build succeeds with no TypeScript errors

- [ ] **Step 3: Run the full farm test suite**

Run: `php artisan test --compact --filter=Farm`
Expected: PASS (all farm model + controller tests)

- [ ] **Step 4: Format and commit**

```bash
vendor/bin/pint --dirty --format agent
git add resources/js/components/app-sidebar.tsx resources/js/actions resources/js/routes
git commit -m "feat: add farm navigation to sidebar"
```

---

## Final Verification

- [ ] Run the full test suite: `php artisan test --compact`
- [ ] Run linting: `vendor/bin/pint --test --format agent` and `npm run lint`
- [ ] Run types: `npm run types` (if defined) / `npm run build`
- [ ] Manually verify: create fruit type → variety → plot → open plot → add cycle → record flowering → confirm forecasted harvest date displays

---

## Notes for the implementer

- **Wayfinder imports:** `@/actions/App/Http/Controllers/Farm/<Controller>` exposes `.form()`, `.url()`, `.store`, `.update`, `.destroy`. `@/routes/<resource>` exposes named-route helpers like `index()`, `show(id)`, `create()`, `edit(id)`. Always run `php artisan wayfinder:generate` after route changes before building the frontend.
- **`<Form>` with PATCH/PUT:** `Controller.update.form(id)` spreads the correct method + action (Inertia handles method spoofing). For the nested cycle store, use explicit `action={...store.url(plot.id)} method="post"`.
- **Thai everywhere:** all visible strings and toasts are Thai; keep identifiers English.
- **`tree_age_years`** is an appended accessor — controllers call `->append('tree_age_years')` so it serializes to Inertia props.
