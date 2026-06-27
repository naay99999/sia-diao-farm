# Module 3 (Yield Tracking & Forecasting) Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Build Module 3 — record harvests (yield by grade) and sales (revenue by grade) per crop cycle, and surface per-cycle analytics (total yield, revenue, direct cost, cost/kg, profit). This closes the cost→revenue loop and feeds the Module 4 dashboard.

**Architecture:** Laravel 13 + Inertia v3 (React 19). New master table `grades` (per fruit type) gets an inline-CRUD management page like fruit-varieties. Transactional `harvests` and `sales` each belong to a `crop_cycle` and own line items (`harvest_items`, `sale_items`) keyed by `grade`. Harvest weight and sale weight are independent (eaten/given/spoiled). `sale_items.subtotal` is computed server-side (`weight_kg × price_per_kg`) and persisted to avoid rounding drift. Recording forms live on the existing crop-cycle detail page (`crop-cycles/show.tsx`) using dynamic line-item rows. Per-cycle analytics are computed live in the controller. Pest feature tests with `RefreshDatabase` cover every endpoint.

**Tech Stack:** PHP 8.5, Laravel 13, Inertia v3, React 19, TypeScript, Tailwind v4, Wayfinder, Pest 4.

**Scope note:** This is plan 3 of 4 (data-model spec `docs/superpowers/specs/2026-06-27-farm-erp-data-model-design.md`). Tables in scope: `grades`, `harvests`, `harvest_items`, `sales`, `sale_items`. Modules 1 (plots/cycles) and 2 (activities/expenses) are complete. Module 4 (analytics dashboard) follows — it adds NO new tables, only aggregate queries. The `flowering_date`/`expected_harvest_date` forecast already exists on `crop_cycles` from Module 1.

**Conventions to follow (study before starting):**
- Models use `#[Fillable([...])]` attribute + `@property` docblocks. Study `app/Models/Expense.php`, `app/Models/Activity.php`, `app/Models/CropCycle.php`.
- Controllers: `Inertia::render` for pages, `to_route(...)` for redirects, `Inertia::flash('toast', ['type' => 'success', 'message' => '...'])`. See `app/Http/Controllers/Farm/ActivityController.php`, `ExpenseController.php`.
- FormRequests: `authorize(): bool` → `true`, `rules(): array` typed `array<string, mixed>`. See `app/Http/Requests/Farm/StoreActivityRequest.php`.
- Feature tests: `php artisan make:test --pest Farm/<Name>`. Every controller test starts with a `guests cannot ...` redirect test. Run `php artisan test --compact --filter=<Name>`.
- Master inline-CRUD page: mirror `resources/js/pages/farm/fruit-varieties/index.tsx` (per-fruit-type select, `useState` editingId, `Controller.store.form()` / `update.form(id)` / `router.delete(...destroy.url(id))`, static `.layout = { breadcrumbs }`).
- Embedded create-form on cycle page: mirror `resources/js/pages/farm/crop-cycles/show.tsx` (`<Form action={x.url(id)} method="post" options={{ preserveScroll: true }} resetOnSuccess>` render-prop).
- Dynamic line-item rows use `useState` over row-id arrays with the array **position** as the field index (`items[${idx}][...]`) so indices stay contiguous after a removal. The shadcn `<Select name="...">` renders a hidden input, so it serializes inside `<Form>`.
- `InputError` is a DEFAULT export: `import InputError from '@/components/input-error';`.
- `decimal:2` casts serialize as STRINGS in JSON — frontend uses `Number(x)` for math, `.toLocaleString('th-TH', { minimumFractionDigits: 2 })` for display.
- After route/controller changes run `php artisan wayfinder:generate`. `resources/js/actions` and `resources/js/routes` are GITIGNORED — never `git add` them.
- After PHP changes run `vendor/bin/pint --dirty --format agent`.
- All user-facing strings (UI + validation + toasts) are **Thai**. Code identifiers stay English.

---

## File Structure

**Created:**
- `app/Models/Grade.php`, `Harvest.php`, `HarvestItem.php`, `Sale.php`, `SaleItem.php`
- `database/factories/GradeFactory.php`, `HarvestFactory.php`, `HarvestItemFactory.php`, `SaleFactory.php`, `SaleItemFactory.php`
- `database/migrations/*_create_grades_table.php`
- `database/migrations/*_create_harvests_table.php`
- `database/migrations/*_create_harvest_items_table.php`
- `database/migrations/*_create_sales_table.php`
- `database/migrations/*_create_sale_items_table.php`
- `app/Http/Controllers/Farm/GradeController.php`, `HarvestController.php`, `SaleController.php`
- `app/Http/Requests/Farm/StoreGradeRequest.php`, `UpdateGradeRequest.php`, `StoreHarvestRequest.php`, `StoreSaleRequest.php`
- `resources/js/pages/farm/grades/index.tsx`
- Test files under `tests/Feature/Farm/`

**Modified:**
- `app/Models/FruitType.php` — add `grades()` HasMany
- `app/Models/CropCycle.php` — add `harvests()`, `sales()` HasMany
- `app/Http/Controllers/Farm/CropCycleController.php` — extend `show()` with yield/revenue/cost-per-kg/profit + grades + eager-loaded harvests/sales
- `routes/web.php` — register module-3 routes
- `resources/js/types/farm.ts` — add Grade, Harvest, HarvestItem, Sale, SaleItem types + extend CropCycle
- `resources/js/pages/farm/crop-cycles/show.tsx` — add analytics summary card, harvest section (list + form), sale section (list + form)
- `resources/js/components/app-sidebar.tsx` — add เกรดผลไม้ nav item

---

## Task 1: Grade model + migration + factory

**Files:**
- Create: `database/migrations/2026_06_27_120000_create_grades_table.php`
- Create: `app/Models/Grade.php`
- Create: `database/factories/GradeFactory.php`
- Modify: `app/Models/FruitType.php`
- Test: `tests/Feature/Farm/GradeModelTest.php`

- [ ] **Step 1: Write the failing test** (`php artisan make:test --pest Farm/GradeModelTest`)

```php
<?php

use App\Models\FruitType;
use App\Models\Grade;

test('a grade belongs to a fruit type', function () {
    $type = FruitType::factory()->create(['name' => 'ทุเรียน']);
    $grade = Grade::factory()->for($type)->create(['name' => 'AB', 'sort_order' => 1]);

    expect($grade->fruitType->name)->toBe('ทุเรียน');
    expect($grade->sort_order)->toBe(1);
});

test('a fruit type has many grades', function () {
    $type = FruitType::factory()->create();
    Grade::factory()->count(3)->for($type)->create();

    expect($type->grades)->toHaveCount(3);
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --compact --filter=GradeModelTest`
Expected: FAIL ("Class Grade not found")

- [ ] **Step 3: Create the migration** `database/migrations/2026_06_27_120000_create_grades_table.php`

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('grades', function (Blueprint $table) {
            $table->id();
            $table->foreignId('fruit_type_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('grades');
    }
};
```

- [ ] **Step 4: Create the model** `app/Models/Grade.php`

```php
<?php

namespace App\Models;

use Database\Factories\GradeFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $fruit_type_id
 * @property string $name
 * @property int $sort_order
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['fruit_type_id', 'name', 'sort_order'])]
class Grade extends Model
{
    /** @use HasFactory<GradeFactory> */
    use HasFactory;

    /**
     * @return BelongsTo<FruitType, $this>
     */
    public function fruitType(): BelongsTo
    {
        return $this->belongsTo(FruitType::class);
    }
}
```

- [ ] **Step 5: Add `grades()` to `app/Models/FruitType.php`**

Add this method after `varieties()` (the `HasMany` import already exists):

```php
    /**
     * @return HasMany<Grade, $this>
     */
    public function grades(): HasMany
    {
        return $this->hasMany(Grade::class)->orderBy('sort_order');
    }
```

- [ ] **Step 6: Create the factory** `database/factories/GradeFactory.php`

```php
<?php

namespace Database\Factories;

use App\Models\FruitType;
use App\Models\Grade;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Grade>
 */
class GradeFactory extends Factory
{
    public function definition(): array
    {
        return [
            'fruit_type_id' => FruitType::factory(),
            'name' => fake()->randomElement(['AB', 'C', 'ตกไซซ์', 'คละ']),
            'sort_order' => fake()->numberBetween(0, 10),
        ];
    }
}
```

- [ ] **Step 7: Run test**

Run: `php artisan test --compact --filter=GradeModelTest`
Expected: PASS (2 tests)

- [ ] **Step 8: Format and commit**

```bash
vendor/bin/pint --dirty --format agent
git add database/migrations/2026_06_27_120000_create_grades_table.php app/Models/Grade.php app/Models/FruitType.php database/factories/GradeFactory.php tests/Feature/Farm/GradeModelTest.php
git commit -m "feat: add Grade model"
```

---

## Task 2: Harvest model + migration + factory

**Files:**
- Create: `database/migrations/2026_06_27_120100_create_harvests_table.php`
- Create: `app/Models/Harvest.php`
- Create: `database/factories/HarvestFactory.php`
- Test: `tests/Feature/Farm/HarvestModelTest.php`

- [ ] **Step 1: Write the failing test**

```php
<?php

use App\Models\CropCycle;
use App\Models\Harvest;
use App\Models\HarvestItem;

test('a harvest belongs to a crop cycle and has many items', function () {
    $cycle = CropCycle::factory()->create();
    $harvest = Harvest::factory()->for($cycle)->create();
    HarvestItem::factory()->count(2)->for($harvest)->create();

    expect($harvest->cropCycle->id)->toBe($cycle->id);
    expect($harvest->items)->toHaveCount(2);
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --compact --filter=HarvestModelTest`
Expected: FAIL ("Class Harvest not found")

- [ ] **Step 3: Create the migration** `database/migrations/2026_06_27_120100_create_harvests_table.php`

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('harvests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('crop_cycle_id')->constrained()->cascadeOnDelete();
            $table->date('harvested_on');
            $table->text('notes')->nullable();
            $table->foreignId('recorded_by')->constrained('users');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('harvests');
    }
};
```

- [ ] **Step 4: Create the model** `app/Models/Harvest.php`

```php
<?php

namespace App\Models;

use Database\Factories\HarvestFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $crop_cycle_id
 * @property Carbon $harvested_on
 * @property string|null $notes
 * @property int $recorded_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['crop_cycle_id', 'harvested_on', 'notes', 'recorded_by'])]
class Harvest extends Model
{
    /** @use HasFactory<HarvestFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'harvested_on' => 'date',
        ];
    }

    /**
     * @return BelongsTo<CropCycle, $this>
     */
    public function cropCycle(): BelongsTo
    {
        return $this->belongsTo(CropCycle::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function recordedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }

    /**
     * @return HasMany<HarvestItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(HarvestItem::class);
    }
}
```

- [ ] **Step 5: Create the factory** `database/factories/HarvestFactory.php`

```php
<?php

namespace Database\Factories;

use App\Models\CropCycle;
use App\Models\Harvest;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Harvest>
 */
class HarvestFactory extends Factory
{
    public function definition(): array
    {
        return [
            'crop_cycle_id' => CropCycle::factory(),
            'harvested_on' => fake()->dateTimeBetween('-2 months', 'now'),
            'notes' => fake()->optional()->sentence(),
            'recorded_by' => User::factory(),
        ];
    }
}
```

- [ ] **Step 6: Run test**

Run: `php artisan test --compact --filter=HarvestModelTest`
Expected: PASS once Task 3 (`HarvestItem`) lands (the `items()` relation + factory reference it). Run together with Task 3.

- [ ] **Step 7: Format and commit**

```bash
vendor/bin/pint --dirty --format agent
git add database/migrations/2026_06_27_120100_create_harvests_table.php app/Models/Harvest.php database/factories/HarvestFactory.php tests/Feature/Farm/HarvestModelTest.php
git commit -m "feat: add Harvest model"
```

---

## Task 3: HarvestItem model + migration + factory

**Files:**
- Create: `database/migrations/2026_06_27_120200_create_harvest_items_table.php`
- Create: `app/Models/HarvestItem.php`
- Create: `database/factories/HarvestItemFactory.php`
- Test: `tests/Feature/Farm/HarvestItemModelTest.php`

- [ ] **Step 1: Write the failing test**

```php
<?php

use App\Models\Grade;
use App\Models\Harvest;
use App\Models\HarvestItem;

test('a harvest item belongs to a harvest and a grade with a weight', function () {
    $harvest = Harvest::factory()->create();
    $grade = Grade::factory()->create(['name' => 'AB']);

    $item = HarvestItem::factory()->for($harvest)->for($grade)->create(['weight_kg' => 120.5]);

    expect($item->harvest->id)->toBe($harvest->id);
    expect($item->grade->name)->toBe('AB');
    expect($item->weight_kg)->toBe('120.50');
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --compact --filter=HarvestItemModelTest`
Expected: FAIL ("Class HarvestItem not found")

- [ ] **Step 3: Create the migration** `database/migrations/2026_06_27_120200_create_harvest_items_table.php`

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('harvest_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('harvest_id')->constrained()->cascadeOnDelete();
            $table->foreignId('grade_id')->constrained();
            $table->decimal('weight_kg', 10, 2);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('harvest_items');
    }
};
```

- [ ] **Step 4: Create the model** `app/Models/HarvestItem.php`

```php
<?php

namespace App\Models;

use Database\Factories\HarvestItemFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $harvest_id
 * @property int $grade_id
 * @property string $weight_kg
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['harvest_id', 'grade_id', 'weight_kg'])]
class HarvestItem extends Model
{
    /** @use HasFactory<HarvestItemFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'weight_kg' => 'decimal:2',
        ];
    }

    /**
     * @return BelongsTo<Harvest, $this>
     */
    public function harvest(): BelongsTo
    {
        return $this->belongsTo(Harvest::class);
    }

    /**
     * @return BelongsTo<Grade, $this>
     */
    public function grade(): BelongsTo
    {
        return $this->belongsTo(Grade::class);
    }
}
```

- [ ] **Step 5: Create the factory** `database/factories/HarvestItemFactory.php`

```php
<?php

namespace Database\Factories;

use App\Models\Grade;
use App\Models\Harvest;
use App\Models\HarvestItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<HarvestItem>
 */
class HarvestItemFactory extends Factory
{
    public function definition(): array
    {
        return [
            'harvest_id' => Harvest::factory(),
            'grade_id' => Grade::factory(),
            'weight_kg' => fake()->randomFloat(2, 10, 500),
        ];
    }
}
```

- [ ] **Step 6: Run tests**

Run: `php artisan test --compact --filter="HarvestModelTest|HarvestItemModelTest"`
Expected: PASS (both)

- [ ] **Step 7: Format and commit**

```bash
vendor/bin/pint --dirty --format agent
git add database/migrations/2026_06_27_120200_create_harvest_items_table.php app/Models/HarvestItem.php database/factories/HarvestItemFactory.php tests/Feature/Farm/HarvestItemModelTest.php
git commit -m "feat: add HarvestItem model"
```

---

## Task 4: Sale model + migration + factory

**Files:**
- Create: `database/migrations/2026_06_27_120300_create_sales_table.php`
- Create: `app/Models/Sale.php`
- Create: `database/factories/SaleFactory.php`
- Test: `tests/Feature/Farm/SaleModelTest.php`

- [ ] **Step 1: Write the failing test**

```php
<?php

use App\Models\CropCycle;
use App\Models\Sale;
use App\Models\SaleItem;

test('a sale belongs to a crop cycle and has many items', function () {
    $cycle = CropCycle::factory()->create();
    $sale = Sale::factory()->for($cycle)->create(['buyer_name' => 'ล้งเจ๊แดง']);
    SaleItem::factory()->count(2)->for($sale)->create();

    expect($sale->cropCycle->id)->toBe($cycle->id);
    expect($sale->buyer_name)->toBe('ล้งเจ๊แดง');
    expect($sale->items)->toHaveCount(2);
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --compact --filter=SaleModelTest`
Expected: FAIL ("Class Sale not found")

- [ ] **Step 3: Create the migration** `database/migrations/2026_06_27_120300_create_sales_table.php`

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sales', function (Blueprint $table) {
            $table->id();
            $table->foreignId('crop_cycle_id')->constrained()->cascadeOnDelete();
            $table->string('buyer_name');
            $table->date('sold_on');
            $table->text('notes')->nullable();
            $table->foreignId('recorded_by')->constrained('users');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sales');
    }
};
```

- [ ] **Step 4: Create the model** `app/Models/Sale.php`

```php
<?php

namespace App\Models;

use Database\Factories\SaleFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $crop_cycle_id
 * @property string $buyer_name
 * @property Carbon $sold_on
 * @property string|null $notes
 * @property int $recorded_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['crop_cycle_id', 'buyer_name', 'sold_on', 'notes', 'recorded_by'])]
class Sale extends Model
{
    /** @use HasFactory<SaleFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'sold_on' => 'date',
        ];
    }

    /**
     * @return BelongsTo<CropCycle, $this>
     */
    public function cropCycle(): BelongsTo
    {
        return $this->belongsTo(CropCycle::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function recordedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }

    /**
     * @return HasMany<SaleItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(SaleItem::class);
    }
}
```

- [ ] **Step 5: Create the factory** `database/factories/SaleFactory.php`

```php
<?php

namespace Database\Factories;

use App\Models\CropCycle;
use App\Models\Sale;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Sale>
 */
class SaleFactory extends Factory
{
    public function definition(): array
    {
        return [
            'crop_cycle_id' => CropCycle::factory(),
            'buyer_name' => fake()->randomElement(['ล้งเจ๊แดง', 'พ่อค้าคนกลาง', 'ตลาดไท', 'ล้งส่งออก']),
            'sold_on' => fake()->dateTimeBetween('-2 months', 'now'),
            'notes' => fake()->optional()->sentence(),
            'recorded_by' => User::factory(),
        ];
    }
}
```

- [ ] **Step 6: Run test**

Run: `php artisan test --compact --filter=SaleModelTest`
Expected: PASS once Task 5 (`SaleItem`) lands. Run together with Task 5.

- [ ] **Step 7: Format and commit**

```bash
vendor/bin/pint --dirty --format agent
git add database/migrations/2026_06_27_120300_create_sales_table.php app/Models/Sale.php database/factories/SaleFactory.php tests/Feature/Farm/SaleModelTest.php
git commit -m "feat: add Sale model"
```

---

## Task 5: SaleItem model + migration + factory

**Files:**
- Create: `database/migrations/2026_06_27_120400_create_sale_items_table.php`
- Create: `app/Models/SaleItem.php`
- Create: `database/factories/SaleItemFactory.php`
- Test: `tests/Feature/Farm/SaleItemModelTest.php`

- [ ] **Step 1: Write the failing test**

```php
<?php

use App\Models\Grade;
use App\Models\Sale;
use App\Models\SaleItem;

test('a sale item belongs to a sale and a grade and stores a subtotal', function () {
    $sale = Sale::factory()->create();
    $grade = Grade::factory()->create(['name' => 'AB']);

    $item = SaleItem::factory()->for($sale)->for($grade)->create([
        'weight_kg' => 100,
        'price_per_kg' => 85.50,
        'subtotal' => 8550,
    ]);

    expect($item->sale->id)->toBe($sale->id);
    expect($item->grade->name)->toBe('AB');
    expect($item->weight_kg)->toBe('100.00');
    expect($item->price_per_kg)->toBe('85.50');
    expect($item->subtotal)->toBe('8550.00');
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --compact --filter=SaleItemModelTest`
Expected: FAIL ("Class SaleItem not found")

- [ ] **Step 3: Create the migration** `database/migrations/2026_06_27_120400_create_sale_items_table.php`

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sale_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sale_id')->constrained()->cascadeOnDelete();
            $table->foreignId('grade_id')->constrained();
            $table->decimal('weight_kg', 10, 2);
            $table->decimal('price_per_kg', 10, 2);
            $table->decimal('subtotal', 12, 2);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sale_items');
    }
};
```

- [ ] **Step 4: Create the model** `app/Models/SaleItem.php`

```php
<?php

namespace App\Models;

use Database\Factories\SaleItemFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $sale_id
 * @property int $grade_id
 * @property string $weight_kg
 * @property string $price_per_kg
 * @property string $subtotal
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['sale_id', 'grade_id', 'weight_kg', 'price_per_kg', 'subtotal'])]
class SaleItem extends Model
{
    /** @use HasFactory<SaleItemFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'weight_kg' => 'decimal:2',
            'price_per_kg' => 'decimal:2',
            'subtotal' => 'decimal:2',
        ];
    }

    /**
     * @return BelongsTo<Sale, $this>
     */
    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class);
    }

    /**
     * @return BelongsTo<Grade, $this>
     */
    public function grade(): BelongsTo
    {
        return $this->belongsTo(Grade::class);
    }
}
```

- [ ] **Step 5: Create the factory** `database/factories/SaleItemFactory.php`

```php
<?php

namespace Database\Factories;

use App\Models\Grade;
use App\Models\Sale;
use App\Models\SaleItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SaleItem>
 */
class SaleItemFactory extends Factory
{
    public function definition(): array
    {
        $weight = fake()->randomFloat(2, 10, 500);
        $price = fake()->randomFloat(2, 20, 150);

        return [
            'sale_id' => Sale::factory(),
            'grade_id' => Grade::factory(),
            'weight_kg' => $weight,
            'price_per_kg' => $price,
            'subtotal' => round($weight * $price, 2),
        ];
    }
}
```

- [ ] **Step 6: Run tests**

Run: `php artisan test --compact --filter="SaleModelTest|SaleItemModelTest"`
Expected: PASS (both)

- [ ] **Step 7: Format and commit**

```bash
vendor/bin/pint --dirty --format agent
git add database/migrations/2026_06_27_120400_create_sale_items_table.php app/Models/SaleItem.php database/factories/SaleItemFactory.php tests/Feature/Farm/SaleItemModelTest.php
git commit -m "feat: add SaleItem model"
```

---

## Task 6: Grade management (backend + UI)

**Files:**
- Create: `app/Http/Requests/Farm/StoreGradeRequest.php`, `UpdateGradeRequest.php`
- Create: `app/Http/Controllers/Farm/GradeController.php`
- Modify: `routes/web.php`
- Modify: `resources/js/types/farm.ts`
- Create: `resources/js/pages/farm/grades/index.tsx`
- Test: `tests/Feature/Farm/GradeControllerTest.php`

- [ ] **Step 1: Write the failing controller test** (`php artisan make:test --pest Farm/GradeControllerTest`)

```php
<?php

use App\Models\FruitType;
use App\Models\Grade;
use App\Models\User;

test('guests cannot view grades', function () {
    $this->get(route('grades.index'))->assertRedirect(route('login'));
});

test('a user can view the grades page', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('grades.index'))
        ->assertOk();
});

test('a user can create a grade', function () {
    $type = FruitType::factory()->create();

    $this->actingAs(User::factory()->create())
        ->post(route('grades.store'), [
            'fruit_type_id' => $type->id,
            'name' => 'AB',
            'sort_order' => 1,
        ])
        ->assertRedirect(route('grades.index'));

    expect(Grade::where('name', 'AB')->exists())->toBeTrue();
});

test('grade validation requires fruit type and name', function () {
    $this->actingAs(User::factory()->create())
        ->from(route('grades.index'))
        ->post(route('grades.store'), [
            'fruit_type_id' => 999,
            'name' => '',
        ])
        ->assertSessionHasErrors(['fruit_type_id', 'name']);
});

test('a user can update a grade', function () {
    $grade = Grade::factory()->create(['name' => 'C']);

    $this->actingAs(User::factory()->create())
        ->put(route('grades.update', $grade), [
            'fruit_type_id' => $grade->fruit_type_id,
            'name' => 'ตกไซซ์',
            'sort_order' => 5,
        ])
        ->assertRedirect(route('grades.index'));

    $grade->refresh();
    expect($grade->name)->toBe('ตกไซซ์');
    expect($grade->sort_order)->toBe(5);
});

test('a user can delete a grade', function () {
    $grade = Grade::factory()->create();

    $this->actingAs(User::factory()->create())
        ->delete(route('grades.destroy', $grade))
        ->assertRedirect(route('grades.index'));

    expect(Grade::find($grade->id))->toBeNull();
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --compact --filter=GradeControllerTest`
Expected: FAIL ("Route [grades.index] not defined")

- [ ] **Step 3: Create the FormRequests**

`app/Http/Requests/Farm/StoreGradeRequest.php`:

```php
<?php

namespace App\Http\Requests\Farm;

use Illuminate\Foundation\Http\FormRequest;

class StoreGradeRequest extends FormRequest
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
            'sort_order' => ['nullable', 'integer', 'min:0'],
        ];
    }
}
```

`app/Http/Requests/Farm/UpdateGradeRequest.php` — identical body, class name `UpdateGradeRequest`:

```php
<?php

namespace App\Http\Requests\Farm;

use Illuminate\Foundation\Http\FormRequest;

class UpdateGradeRequest extends FormRequest
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
            'sort_order' => ['nullable', 'integer', 'min:0'],
        ];
    }
}
```

- [ ] **Step 4: Create the controller** `app/Http/Controllers/Farm/GradeController.php`

```php
<?php

namespace App\Http\Controllers\Farm;

use App\Http\Controllers\Controller;
use App\Http\Requests\Farm\StoreGradeRequest;
use App\Http\Requests\Farm\UpdateGradeRequest;
use App\Models\FruitType;
use App\Models\Grade;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class GradeController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('farm/grades/index', [
            'grades' => Grade::with('fruitType')
                ->orderBy('fruit_type_id')
                ->orderBy('sort_order')
                ->get(),
            'fruitTypes' => FruitType::orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function store(StoreGradeRequest $request): RedirectResponse
    {
        Grade::create([
            ...$request->validated(),
            'sort_order' => $request->validated()['sort_order'] ?? 0,
        ]);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'เพิ่มเกรดแล้ว']);

        return to_route('grades.index');
    }

    public function update(UpdateGradeRequest $request, Grade $grade): RedirectResponse
    {
        $grade->update([
            ...$request->validated(),
            'sort_order' => $request->validated()['sort_order'] ?? 0,
        ]);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'อัปเดตเกรดแล้ว']);

        return to_route('grades.index');
    }

    public function destroy(Grade $grade): RedirectResponse
    {
        $grade->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => 'ลบเกรดแล้ว']);

        return to_route('grades.index');
    }
}
```

- [ ] **Step 5: Register routes**

In `routes/web.php`, add the import:

```php
use App\Http\Controllers\Farm\GradeController;
```

Inside the `auth`+`verified` group, with the other master-data resources:

```php
Route::resource('grades', GradeController::class)
    ->only(['index', 'store', 'update', 'destroy']);
```

- [ ] **Step 6: Generate Wayfinder helpers**

Run: `php artisan wayfinder:generate`

- [ ] **Step 7: Run backend tests**

Run: `php artisan test --compact --filter=GradeControllerTest`
Expected: PASS (6 tests)

- [ ] **Step 8: Add the Grade TS type**

In `resources/js/types/farm.ts`, add after `FruitVariety`:

```ts
export type Grade = {
    id: number;
    fruit_type_id: number;
    name: string;
    sort_order: number;
    fruit_type?: FruitType;
};
```

- [ ] **Step 9: Create the page** `resources/js/pages/farm/grades/index.tsx`

Mirror `resources/js/pages/farm/fruit-varieties/index.tsx` exactly (per-fruit-type select + inline edit). Content:

```tsx
import { Form, Head, router } from '@inertiajs/react';
import { useState } from 'react';
import GradeController from '@/actions/App/Http/Controllers/Farm/GradeController';
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
import { index } from '@/routes/grades';
import type { FruitType, Grade } from '@/types/farm';

export default function GradesIndex({
    grades,
    fruitTypes,
}: {
    grades: Grade[];
    fruitTypes: Pick<FruitType, 'id' | 'name'>[];
}) {
    const [editingId, setEditingId] = useState<number | null>(null);

    return (
        <div className="flex h-full flex-1 flex-col gap-6 p-4">
            <Head title="เกรดผลไม้" />
            <Heading title="เกรดผลไม้" description="กำหนดเกรดของผลไม้แต่ละชนิด เช่น AB ตกไซซ์ คละ" />

            <Card className="p-4">
                <Form {...GradeController.store.form()} options={{ preserveScroll: true }} resetOnSuccess className="grid gap-3 sm:grid-cols-4 sm:items-end">
                    {({ processing, errors }) => (
                        <>
                            <div className="grid gap-2">
                                <Label htmlFor="fruit_type_id">ชนิดผลไม้</Label>
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
                                <Label htmlFor="name">ชื่อเกรด</Label>
                                <Input id="name" name="name" required placeholder="เช่น AB" />
                                <InputError message={errors.name} />
                            </div>
                            <div className="grid gap-2">
                                <Label htmlFor="sort_order">ลำดับ</Label>
                                <Input id="sort_order" name="sort_order" type="number" min={0} defaultValue={0} />
                                <InputError message={errors.sort_order} />
                            </div>
                            <Button disabled={processing}>เพิ่ม</Button>
                        </>
                    )}
                </Form>
            </Card>

            <div className="grid gap-3">
                {grades.length === 0 && <p className="text-muted-foreground text-sm">ยังไม่มีเกรด</p>}
                {grades.map((grade) => (
                    <Card key={grade.id} className="p-4">
                        {editingId === grade.id ? (
                            <Form {...GradeController.update.form(grade.id)} options={{ preserveScroll: true }} onSuccess={() => setEditingId(null)} className="grid gap-3 sm:grid-cols-4 sm:items-end">
                                {({ processing, errors }) => (
                                    <>
                                        <div className="grid gap-2">
                                            <Label htmlFor={`fruit_type_id-${grade.id}`}>ชนิดผลไม้</Label>
                                            <Select name="fruit_type_id" defaultValue={String(grade.fruit_type_id)}>
                                                <SelectTrigger id={`fruit_type_id-${grade.id}`}>
                                                    <SelectValue />
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
                                            <Label htmlFor={`name-${grade.id}`}>ชื่อเกรด</Label>
                                            <Input id={`name-${grade.id}`} name="name" required defaultValue={grade.name} />
                                            <InputError message={errors.name} />
                                        </div>
                                        <div className="grid gap-2">
                                            <Label htmlFor={`sort_order-${grade.id}`}>ลำดับ</Label>
                                            <Input id={`sort_order-${grade.id}`} name="sort_order" type="number" min={0} defaultValue={grade.sort_order} />
                                            <InputError message={errors.sort_order} />
                                        </div>
                                        <div className="flex gap-2">
                                            <Button disabled={processing}>บันทึก</Button>
                                            <Button type="button" variant="outline" onClick={() => setEditingId(null)}>ยกเลิก</Button>
                                        </div>
                                    </>
                                )}
                            </Form>
                        ) : (
                            <div className="flex items-center justify-between">
                                <div>
                                    <p className="font-medium">
                                        {grade.name}
                                        <span className="text-muted-foreground ml-2 text-sm">({grade.fruit_type?.name})</span>
                                    </p>
                                    <p className="text-muted-foreground text-sm">ลำดับ {grade.sort_order}</p>
                                </div>
                                <div className="flex gap-2">
                                    <Button variant="outline" onClick={() => setEditingId(grade.id)}>แก้ไข</Button>
                                    <Button
                                        variant="destructive"
                                        onClick={() => {
                                            if (confirm('ลบเกรดนี้?')) {
                                                router.delete(GradeController.destroy.url(grade.id));
                                            }
                                        }}
                                    >
                                        ลบ
                                    </Button>
                                </div>
                            </div>
                        )}
                    </Card>
                ))}
            </div>
        </div>
    );
}

GradesIndex.layout = {
    breadcrumbs: [{ title: 'เกรดผลไม้', href: index() }],
};
```

- [ ] **Step 10: Build the frontend**

Run: `npm run build`
Expected: build succeeds with no TypeScript errors

- [ ] **Step 11: Format and commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Http/Requests/Farm/StoreGradeRequest.php app/Http/Requests/Farm/UpdateGradeRequest.php app/Http/Controllers/Farm/GradeController.php routes/web.php resources/js/types/farm.ts resources/js/pages/farm/grades tests/Feature/Farm/GradeControllerTest.php
git commit -m "feat: add grade management"
```

---

## Task 7: Crop cycle yield/revenue analytics (extend show + summary card + read-only lists)

**Files:**
- Modify: `app/Models/CropCycle.php` (add `harvests()`, `sales()`)
- Modify: `app/Http/Controllers/Farm/CropCycleController.php` (extend `show()`)
- Modify: `resources/js/types/farm.ts` (add Harvest, HarvestItem, Sale, SaleItem types; extend CropCycle)
- Modify: `resources/js/pages/farm/crop-cycles/show.tsx` (analytics summary + harvest/sale read-only lists)
- Test: `tests/Feature/Farm/CropCycleControllerTest.php` (add analytics test)

- [ ] **Step 1: Add the failing analytics test**

In the existing `tests/Feature/Farm/CropCycleControllerTest.php`, ensure these are imported at the top: `App\Models\Grade`, `App\Models\Harvest`, `App\Models\HarvestItem`, `App\Models\Sale`, `App\Models\SaleItem`, `App\Models\Expense`, `App\Models\ExpenseCategory` (add any missing `use` lines). Append:

```php
test('the crop cycle page exposes yield, revenue, cost-per-kg and profit', function () {
    $cycle = CropCycle::factory()->create();

    $harvest = Harvest::factory()->for($cycle)->create();
    $grade = Grade::factory()->create();
    HarvestItem::factory()->for($harvest)->for($grade)->create(['weight_kg' => 200]);

    $sale = Sale::factory()->for($cycle)->create();
    SaleItem::factory()->for($sale)->for($grade)->create([
        'weight_kg' => 200,
        'price_per_kg' => 50,
        'subtotal' => 10000,
    ]);

    Expense::factory()->for($cycle)->create([
        'expense_category_id' => ExpenseCategory::factory(),
        'amount' => 4000,
    ]);

    $this->actingAs(User::factory()->create())
        ->get(route('crop-cycles.show', $cycle))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('totalYield', 200.0)
            ->where('revenue', 10000.0)
            ->where('totalDirectCost', 4000.0)
            ->where('costPerKg', 20.0)
            ->where('profit', 6000.0)
        );
});
```

Note: if the test file does not already `use Inertia\Testing\AssertableInertia;`, the `assertInertia(fn ($page) => ...)` closure style works without an explicit import (the closure receives an `AssertableInertia`). Match whatever assertion style sibling tests use; if unsure, this closure form is correct for Inertia v3 + Pest.

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --compact --filter=CropCycleControllerTest`
Expected: FAIL (`totalYield` prop missing / undefined)

- [ ] **Step 3: Add `harvests()` and `sales()` to `app/Models/CropCycle.php`**

The `HasMany` import already exists (added in Module 2). Add:

```php
    /**
     * @return HasMany<Harvest, $this>
     */
    public function harvests(): HasMany
    {
        return $this->hasMany(Harvest::class);
    }

    /**
     * @return HasMany<Sale, $this>
     */
    public function sales(): HasMany
    {
        return $this->hasMany(Sale::class);
    }
```

- [ ] **Step 4: Extend `show()` in `CropCycleController`**

Add imports: `use App\Models\Grade;`. Replace the existing `show()` method body with:

```php
public function show(CropCycle $cropCycle): Response
{
    $cropCycle->load([
        'plot',
        'fruitVariety.fruitType',
        'activities' => fn ($q) => $q->with('activityType')->latest('performed_on'),
        'expenses' => fn ($q) => $q->with('expenseCategory')->latest('spent_on'),
        'harvests' => fn ($q) => $q->with('items.grade')->latest('harvested_on'),
        'sales' => fn ($q) => $q->with('items.grade')->latest('sold_on'),
    ]);

    $totalDirectCost = (float) $cropCycle->expenses->sum('amount');
    $totalYield = (float) $cropCycle->harvests->sum(fn ($harvest) => $harvest->items->sum('weight_kg'));
    $revenue = (float) $cropCycle->sales->sum(fn ($sale) => $sale->items->sum('subtotal'));
    $costPerKg = $totalYield > 0 ? round($totalDirectCost / $totalYield, 2) : null;
    $profit = $revenue - $totalDirectCost;

    return Inertia::render('farm/crop-cycles/show', [
        'cropCycle' => $cropCycle,
        'totalDirectCost' => $totalDirectCost,
        'totalYield' => $totalYield,
        'revenue' => $revenue,
        'costPerKg' => $costPerKg,
        'profit' => $profit,
        'activityTypes' => ActivityType::orderBy('name')->get(['id', 'name']),
        'expenseCategories' => ExpenseCategory::orderBy('name')->get(),
        'grades' => Grade::where('fruit_type_id', $cropCycle->fruitVariety->fruit_type_id)
            ->orderBy('sort_order')
            ->get(['id', 'name']),
    ]);
}
```

- [ ] **Step 5: Run backend tests**

Run: `php artisan test --compact --filter=CropCycleControllerTest`
Expected: PASS (9 tests — the 8 from Module 2 plus the new analytics test)

- [ ] **Step 6: Add Harvest/Sale TS types and extend CropCycle**

In `resources/js/types/farm.ts`, add after `Grade`:

```ts
export type HarvestItem = {
    id: number;
    harvest_id: number;
    grade_id: number;
    weight_kg: string;
    grade?: Grade;
};

export type Harvest = {
    id: number;
    crop_cycle_id: number;
    harvested_on: string;
    notes: string | null;
    items?: HarvestItem[];
};

export type SaleItem = {
    id: number;
    sale_id: number;
    grade_id: number;
    weight_kg: string;
    price_per_kg: string;
    subtotal: string;
    grade?: Grade;
};

export type Sale = {
    id: number;
    crop_cycle_id: number;
    buyer_name: string;
    sold_on: string;
    notes: string | null;
    items?: SaleItem[];
};
```

Extend the `CropCycle` type by adding before its closing brace:

```ts
    harvests?: Harvest[];
    sales?: Sale[];
```

- [ ] **Step 7: Add the analytics summary + read-only harvest/sale lists to `crop-cycles/show.tsx`**

In `resources/js/pages/farm/crop-cycles/show.tsx`:

(a) Update the `PageProps` type to add the new props:

```tsx
type PageProps = {
    cropCycle: CropCycle;
    totalDirectCost: number;
    totalYield: number;
    revenue: number;
    costPerKg: number | null;
    profit: number;
    activityTypes: { id: number; name: string }[];
    expenseCategories: { id: number; name: string }[];
    grades: { id: number; name: string }[];
};
```

(b) Update the imports from `@/types/farm` to also import `Harvest` and `Sale`:

```tsx
import { cropCycleStageLabels, type Activity, type CropCycle, type Expense, type Harvest, type Sale } from '@/types/farm';
```

(c) Update the component signature to destructure the new props:

```tsx
export default function CropCycleShow({ cropCycle, totalDirectCost, totalYield, revenue, costPerKg, profit, activityTypes, expenseCategories, grades }: PageProps) {
```

(d) Add local arrays near the existing `const activities`/`const expenses`:

```tsx
    const harvests: Harvest[] = cropCycle.harvests ?? [];
    const sales: Sale[] = cropCycle.sales ?? [];
    const formatBaht = (value: number) => value.toLocaleString('th-TH', { minimumFractionDigits: 2 });
```

(e) REPLACE the existing single "ต้นทุนตรงรวมของรอบนี้" `<Card>` (the one showing `totalDirectCost`) with this richer summary grid:

```tsx
            <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-5">
                <Card className="p-4">
                    <p className="text-muted-foreground text-sm">ผลผลิตรวม</p>
                    <p className="text-2xl font-semibold">{totalYield.toLocaleString('th-TH')} กก.</p>
                </Card>
                <Card className="p-4">
                    <p className="text-muted-foreground text-sm">รายรับ</p>
                    <p className="text-2xl font-semibold">{formatBaht(revenue)} บาท</p>
                </Card>
                <Card className="p-4">
                    <p className="text-muted-foreground text-sm">ต้นทุนตรง</p>
                    <p className="text-2xl font-semibold">{formatBaht(totalDirectCost)} บาท</p>
                </Card>
                <Card className="p-4">
                    <p className="text-muted-foreground text-sm">ต้นทุน/กก.</p>
                    <p className="text-2xl font-semibold">{costPerKg !== null ? `${formatBaht(costPerKg)} บาท` : '—'}</p>
                </Card>
                <Card className="p-4">
                    <p className="text-muted-foreground text-sm">กำไร</p>
                    <p className={`text-2xl font-semibold ${profit >= 0 ? 'text-emerald-600' : 'text-red-600'}`}>{formatBaht(profit)} บาท</p>
                </Card>
            </div>
```

(f) Add a harvests read-only `<Card>` and a sales read-only `<Card>` AFTER the expenses `<Card>` (the record forms come in Tasks 8 and 9):

```tsx
            <Card className="p-4">
                <p className="mb-3 font-medium">การเก็บเกี่ยว</p>
                {harvests.length === 0 ? (
                    <p className="text-muted-foreground text-sm">ยังไม่มีการเก็บเกี่ยว</p>
                ) : (
                    <div className="grid gap-2">
                        {harvests.map((harvest) => (
                            <div key={harvest.id} className="rounded-md border p-3">
                                <p className="font-medium">{harvest.harvested_on}</p>
                                <div className="text-muted-foreground mt-1 grid gap-1 text-sm">
                                    {harvest.items?.map((item) => (
                                        <span key={item.id}>
                                            {item.grade?.name}: {Number(item.weight_kg).toLocaleString('th-TH')} กก.
                                        </span>
                                    ))}
                                </div>
                                {harvest.notes ? <p className="text-muted-foreground mt-1 text-sm">{harvest.notes}</p> : null}
                            </div>
                        ))}
                    </div>
                )}
            </Card>

            <Card className="p-4">
                <p className="mb-3 font-medium">การขาย</p>
                {sales.length === 0 ? (
                    <p className="text-muted-foreground text-sm">ยังไม่มีการขาย</p>
                ) : (
                    <div className="grid gap-2">
                        {sales.map((sale) => (
                            <div key={sale.id} className="rounded-md border p-3">
                                <div className="flex items-center justify-between">
                                    <p className="font-medium">{sale.buyer_name}</p>
                                    <p className="text-muted-foreground text-sm">{sale.sold_on}</p>
                                </div>
                                <div className="text-muted-foreground mt-1 grid gap-1 text-sm">
                                    {sale.items?.map((item) => (
                                        <span key={item.id}>
                                            {item.grade?.name}: {Number(item.weight_kg).toLocaleString('th-TH')} กก. ×{' '}
                                            {formatBaht(Number(item.price_per_kg))} = {formatBaht(Number(item.subtotal))} บาท
                                        </span>
                                    ))}
                                </div>
                                {sale.notes ? <p className="text-muted-foreground mt-1 text-sm">{sale.notes}</p> : null}
                            </div>
                        ))}
                    </div>
                )}
            </Card>
```

- [ ] **Step 8: Build the frontend**

Run: `npm run build`
Expected: build succeeds with no TypeScript errors

- [ ] **Step 9: Format and commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Models/CropCycle.php app/Http/Controllers/Farm/CropCycleController.php resources/js/types/farm.ts resources/js/pages/farm/crop-cycles/show.tsx tests/Feature/Farm/CropCycleControllerTest.php
git commit -m "feat: add crop cycle yield and revenue analytics"
```

---

## Task 8: Harvest recording (backend + dynamic-row UI on cycle page)

**Files:**
- Create: `app/Http/Requests/Farm/StoreHarvestRequest.php`
- Create: `app/Http/Controllers/Farm/HarvestController.php`
- Modify: `routes/web.php`
- Modify: `resources/js/pages/farm/crop-cycles/show.tsx` (harvest record form + delete)
- Test: `tests/Feature/Farm/HarvestControllerTest.php`

- [ ] **Step 1: Write the failing controller test** (`php artisan make:test --pest Farm/HarvestControllerTest`)

```php
<?php

use App\Models\CropCycle;
use App\Models\Grade;
use App\Models\Harvest;
use App\Models\User;

test('guests cannot record a harvest', function () {
    $cycle = CropCycle::factory()->create();

    $this->post(route('crop-cycles.harvests.store', $cycle), [])
        ->assertRedirect(route('login'));
});

test('a user can record a harvest with graded items', function () {
    $cycle = CropCycle::factory()->create();
    $gradeA = Grade::factory()->create();
    $gradeB = Grade::factory()->create();
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('crop-cycles.harvests.store', $cycle), [
            'harvested_on' => '2026-05-01',
            'notes' => 'เก็บรอบแรก',
            'items' => [
                ['grade_id' => $gradeA->id, 'weight_kg' => 120.5],
                ['grade_id' => $gradeB->id, 'weight_kg' => 80],
            ],
        ])
        ->assertRedirect(route('crop-cycles.show', $cycle));

    $harvest = Harvest::first();
    expect($harvest->crop_cycle_id)->toBe($cycle->id);
    expect($harvest->recorded_by)->toBe($user->id);
    expect($harvest->items)->toHaveCount(2);
    expect($harvest->items->pluck('weight_kg')->all())->toEqual(['120.50', '80.00']);
});

test('a harvest requires a date and at least one item', function () {
    $cycle = CropCycle::factory()->create();

    $this->actingAs(User::factory()->create())
        ->from(route('crop-cycles.show', $cycle))
        ->post(route('crop-cycles.harvests.store', $cycle), [
            'harvested_on' => '',
            'items' => [],
        ])
        ->assertSessionHasErrors(['harvested_on', 'items']);
});

test('harvest items require a valid grade and a positive weight', function () {
    $cycle = CropCycle::factory()->create();

    $this->actingAs(User::factory()->create())
        ->from(route('crop-cycles.show', $cycle))
        ->post(route('crop-cycles.harvests.store', $cycle), [
            'harvested_on' => '2026-05-01',
            'items' => [
                ['grade_id' => 999, 'weight_kg' => 0],
            ],
        ])
        ->assertSessionHasErrors(['items.0.grade_id', 'items.0.weight_kg']);
});

test('a user can delete a harvest', function () {
    $harvest = Harvest::factory()->create();

    $this->actingAs(User::factory()->create())
        ->delete(route('harvests.destroy', $harvest))
        ->assertRedirect();

    expect(Harvest::find($harvest->id))->toBeNull();
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --compact --filter=HarvestControllerTest`
Expected: FAIL ("Route [crop-cycles.harvests.store] not defined")

- [ ] **Step 3: Create the FormRequest** `app/Http/Requests/Farm/StoreHarvestRequest.php`

```php
<?php

namespace App\Http\Requests\Farm;

use Illuminate\Foundation\Http\FormRequest;

class StoreHarvestRequest extends FormRequest
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
            'harvested_on' => ['required', 'date'],
            'notes' => ['nullable', 'string'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.grade_id' => ['required', 'exists:grades,id'],
            'items.*.weight_kg' => ['required', 'numeric', 'min:0.01'],
        ];
    }
}
```

- [ ] **Step 4: Create the controller** `app/Http/Controllers/Farm/HarvestController.php`

```php
<?php

namespace App\Http\Controllers\Farm;

use App\Http\Controllers\Controller;
use App\Http\Requests\Farm\StoreHarvestRequest;
use App\Models\CropCycle;
use App\Models\Harvest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class HarvestController extends Controller
{
    public function store(StoreHarvestRequest $request, CropCycle $cropCycle): RedirectResponse
    {
        $validated = $request->validated();

        DB::transaction(function () use ($validated, $cropCycle, $request) {
            $harvest = $cropCycle->harvests()->create([
                'harvested_on' => $validated['harvested_on'],
                'notes' => $validated['notes'] ?? null,
                'recorded_by' => $request->user()->id,
            ]);

            foreach ($validated['items'] as $item) {
                $harvest->items()->create([
                    'grade_id' => $item['grade_id'],
                    'weight_kg' => $item['weight_kg'],
                ]);
            }
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => 'บันทึกการเก็บเกี่ยวแล้ว']);

        return to_route('crop-cycles.show', $cropCycle);
    }

    public function destroy(Harvest $harvest): RedirectResponse
    {
        $cropCycleId = $harvest->crop_cycle_id;
        $harvest->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => 'ลบการเก็บเกี่ยวแล้ว']);

        return to_route('crop-cycles.show', $cropCycleId);
    }
}
```

- [ ] **Step 5: Register routes**

In `routes/web.php`, add the import:

```php
use App\Http\Controllers\Farm\HarvestController;
```

Inside the `auth`+`verified` group:

```php
Route::post('crop-cycles/{cropCycle}/harvests', [HarvestController::class, 'store'])
    ->name('crop-cycles.harvests.store');
Route::delete('harvests/{harvest}', [HarvestController::class, 'destroy'])
    ->name('harvests.destroy');
```

- [ ] **Step 6: Generate Wayfinder helpers**

Run: `php artisan wayfinder:generate`

- [ ] **Step 7: Run backend tests**

Run: `php artisan test --compact --filter=HarvestControllerTest`
Expected: PASS (5 tests)

- [ ] **Step 8: Add the harvest record form + delete to `crop-cycles/show.tsx`**

In `resources/js/pages/farm/crop-cycles/show.tsx`:

(a) Ensure these are imported (add what's missing): `useState` and `useRef` from `react`; `HarvestController, { store as harvestStore }` from the harvest controller actions; `Form`, `router` from `@inertiajs/react` (already present); `Button`, `Input`, `Label`, `Select`/`SelectContent`/`SelectItem`/`SelectTrigger`/`SelectValue` (already present from Task 8 of Module 2). Add:

```tsx
import { useRef, useState } from 'react';
import HarvestController, { store as harvestStore } from '@/actions/App/Http/Controllers/Farm/HarvestController';
```

(b) Inside the component body (near the top, after the `const` arrays), add harvest-row state:

```tsx
    const [harvestRowIds, setHarvestRowIds] = useState<number[]>([0]);
    const nextHarvestRowId = useRef(1);
```

(c) Add a delete button to each harvest row in the harvests `<Card>`. Change the harvest row header to a flex row containing the date and a delete button:

```tsx
                                <div className="flex items-center justify-between">
                                    <p className="font-medium">{harvest.harvested_on}</p>
                                    <Button
                                        size="sm"
                                        variant="destructive"
                                        onClick={() => {
                                            if (confirm('ลบการเก็บเกี่ยวนี้?')) {
                                                router.delete(HarvestController.destroy.url(harvest.id));
                                            }
                                        }}
                                    >
                                        ลบ
                                    </Button>
                                </div>
```

(d) At the END of the harvests `<Card>` (after the list conditional), add the dynamic-row record form:

```tsx
                <div className="mt-4 border-t pt-4">
                    <p className="mb-3 text-sm font-medium">บันทึกการเก็บเกี่ยวใหม่</p>
                    <Form
                        action={harvestStore.url(cropCycle.id)}
                        method="post"
                        options={{ preserveScroll: true }}
                        resetOnSuccess
                        onSuccess={() => {
                            setHarvestRowIds([0]);
                            nextHarvestRowId.current = 1;
                        }}
                        className="grid gap-3"
                    >
                        {({ processing, errors }) => (
                            <>
                                <div className="grid gap-3 sm:grid-cols-2 sm:items-end">
                                    <div className="grid gap-2">
                                        <Label htmlFor="harvested_on">วันที่เก็บเกี่ยว</Label>
                                        <Input id="harvested_on" name="harvested_on" type="date" required />
                                        <InputError message={errors.harvested_on} />
                                    </div>
                                    <div className="grid gap-2">
                                        <Label htmlFor="harvest_notes">หมายเหตุ</Label>
                                        <Input id="harvest_notes" name="notes" placeholder="เช่น เก็บรอบแรก" />
                                    </div>
                                </div>
                                <div className="grid gap-2">
                                    <Label>ผลผลิตตามเกรด</Label>
                                    {harvestRowIds.map((rowId, idx) => (
                                        <div key={rowId} className="flex items-end gap-2">
                                            <div className="grid flex-1 gap-1">
                                                <Select name={`items[${idx}][grade_id]`}>
                                                    <SelectTrigger>
                                                        <SelectValue placeholder="เลือกเกรด" />
                                                    </SelectTrigger>
                                                    <SelectContent>
                                                        {grades.map((grade) => (
                                                            <SelectItem key={grade.id} value={String(grade.id)}>
                                                                {grade.name}
                                                            </SelectItem>
                                                        ))}
                                                    </SelectContent>
                                                </Select>
                                                <InputError message={errors[`items.${idx}.grade_id`]} />
                                            </div>
                                            <div className="grid flex-1 gap-1">
                                                <Input
                                                    name={`items[${idx}][weight_kg]`}
                                                    type="number"
                                                    step="0.01"
                                                    min={0.01}
                                                    placeholder="น้ำหนัก (กก.)"
                                                    required
                                                />
                                                <InputError message={errors[`items.${idx}.weight_kg`]} />
                                            </div>
                                            {harvestRowIds.length > 1 && (
                                                <Button
                                                    type="button"
                                                    size="sm"
                                                    variant="outline"
                                                    onClick={() => setHarvestRowIds((ids) => ids.filter((id) => id !== rowId))}
                                                >
                                                    ลบ
                                                </Button>
                                            )}
                                        </div>
                                    ))}
                                    <InputError message={errors.items} />
                                    <Button
                                        type="button"
                                        variant="outline"
                                        className="w-fit"
                                        onClick={() => setHarvestRowIds((ids) => [...ids, nextHarvestRowId.current++])}
                                    >
                                        + เพิ่มเกรด
                                    </Button>
                                </div>
                                <div>
                                    <Button disabled={processing}>บันทึกการเก็บเกี่ยว</Button>
                                </div>
                            </>
                        )}
                    </Form>
                </div>
```

- [ ] **Step 9: Build the frontend**

Run: `npm run build`
Expected: build succeeds with no TypeScript errors

- [ ] **Step 10: Format and commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Http/Requests/Farm/StoreHarvestRequest.php app/Http/Controllers/Farm/HarvestController.php routes/web.php resources/js/pages/farm/crop-cycles/show.tsx tests/Feature/Farm/HarvestControllerTest.php
git commit -m "feat: add harvest recording with graded line items"
```

---

## Task 9: Sale recording (backend + dynamic-row UI on cycle page)

**Files:**
- Create: `app/Http/Requests/Farm/StoreSaleRequest.php`
- Create: `app/Http/Controllers/Farm/SaleController.php`
- Modify: `routes/web.php`
- Modify: `resources/js/pages/farm/crop-cycles/show.tsx` (sale record form + delete)
- Test: `tests/Feature/Farm/SaleControllerTest.php`

- [ ] **Step 1: Write the failing controller test** (`php artisan make:test --pest Farm/SaleControllerTest`)

```php
<?php

use App\Models\CropCycle;
use App\Models\Grade;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\User;

test('guests cannot record a sale', function () {
    $cycle = CropCycle::factory()->create();

    $this->post(route('crop-cycles.sales.store', $cycle), [])
        ->assertRedirect(route('login'));
});

test('a user can record a sale and the subtotal is computed server-side', function () {
    $cycle = CropCycle::factory()->create();
    $grade = Grade::factory()->create();
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('crop-cycles.sales.store', $cycle), [
            'buyer_name' => 'ล้งเจ๊แดง',
            'sold_on' => '2026-05-10',
            'items' => [
                ['grade_id' => $grade->id, 'weight_kg' => 100, 'price_per_kg' => 85.5],
            ],
        ])
        ->assertRedirect(route('crop-cycles.show', $cycle));

    $sale = Sale::first();
    expect($sale->crop_cycle_id)->toBe($cycle->id);
    expect($sale->recorded_by)->toBe($user->id);

    $item = SaleItem::first();
    expect($item->subtotal)->toBe('8550.00');
});

test('a sale requires a buyer, date and at least one item', function () {
    $cycle = CropCycle::factory()->create();

    $this->actingAs(User::factory()->create())
        ->from(route('crop-cycles.show', $cycle))
        ->post(route('crop-cycles.sales.store', $cycle), [
            'buyer_name' => '',
            'sold_on' => '',
            'items' => [],
        ])
        ->assertSessionHasErrors(['buyer_name', 'sold_on', 'items']);
});

test('sale items require grade, weight and price', function () {
    $cycle = CropCycle::factory()->create();

    $this->actingAs(User::factory()->create())
        ->from(route('crop-cycles.show', $cycle))
        ->post(route('crop-cycles.sales.store', $cycle), [
            'buyer_name' => 'ล้ง',
            'sold_on' => '2026-05-10',
            'items' => [
                ['grade_id' => 999, 'weight_kg' => 0, 'price_per_kg' => 0],
            ],
        ])
        ->assertSessionHasErrors(['items.0.grade_id', 'items.0.weight_kg', 'items.0.price_per_kg']);
});

test('a user can delete a sale', function () {
    $sale = Sale::factory()->create();

    $this->actingAs(User::factory()->create())
        ->delete(route('sales.destroy', $sale))
        ->assertRedirect();

    expect(Sale::find($sale->id))->toBeNull();
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --compact --filter=SaleControllerTest`
Expected: FAIL ("Route [crop-cycles.sales.store] not defined")

- [ ] **Step 3: Create the FormRequest** `app/Http/Requests/Farm/StoreSaleRequest.php`

```php
<?php

namespace App\Http\Requests\Farm;

use Illuminate\Foundation\Http\FormRequest;

class StoreSaleRequest extends FormRequest
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
            'buyer_name' => ['required', 'string', 'max:255'],
            'sold_on' => ['required', 'date'],
            'notes' => ['nullable', 'string'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.grade_id' => ['required', 'exists:grades,id'],
            'items.*.weight_kg' => ['required', 'numeric', 'min:0.01'],
            'items.*.price_per_kg' => ['required', 'numeric', 'min:0.01'],
        ];
    }
}
```

- [ ] **Step 4: Create the controller** `app/Http/Controllers/Farm/SaleController.php`

```php
<?php

namespace App\Http\Controllers\Farm;

use App\Http\Controllers\Controller;
use App\Http\Requests\Farm\StoreSaleRequest;
use App\Models\CropCycle;
use App\Models\Sale;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class SaleController extends Controller
{
    public function store(StoreSaleRequest $request, CropCycle $cropCycle): RedirectResponse
    {
        $validated = $request->validated();

        DB::transaction(function () use ($validated, $cropCycle, $request) {
            $sale = $cropCycle->sales()->create([
                'buyer_name' => $validated['buyer_name'],
                'sold_on' => $validated['sold_on'],
                'notes' => $validated['notes'] ?? null,
                'recorded_by' => $request->user()->id,
            ]);

            foreach ($validated['items'] as $item) {
                $sale->items()->create([
                    'grade_id' => $item['grade_id'],
                    'weight_kg' => $item['weight_kg'],
                    'price_per_kg' => $item['price_per_kg'],
                    'subtotal' => round($item['weight_kg'] * $item['price_per_kg'], 2),
                ]);
            }
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => 'บันทึกการขายแล้ว']);

        return to_route('crop-cycles.show', $cropCycle);
    }

    public function destroy(Sale $sale): RedirectResponse
    {
        $cropCycleId = $sale->crop_cycle_id;
        $sale->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => 'ลบการขายแล้ว']);

        return to_route('crop-cycles.show', $cropCycleId);
    }
}
```

- [ ] **Step 5: Register routes**

In `routes/web.php`, add the import:

```php
use App\Http\Controllers\Farm\SaleController;
```

Inside the `auth`+`verified` group:

```php
Route::post('crop-cycles/{cropCycle}/sales', [SaleController::class, 'store'])
    ->name('crop-cycles.sales.store');
Route::delete('sales/{sale}', [SaleController::class, 'destroy'])
    ->name('sales.destroy');
```

- [ ] **Step 6: Generate Wayfinder helpers**

Run: `php artisan wayfinder:generate`

- [ ] **Step 7: Run backend tests**

Run: `php artisan test --compact --filter=SaleControllerTest`
Expected: PASS (5 tests)

- [ ] **Step 8: Add the sale record form + delete to `crop-cycles/show.tsx`**

In `resources/js/pages/farm/crop-cycles/show.tsx`:

(a) Add the import:

```tsx
import SaleController, { store as saleStore } from '@/actions/App/Http/Controllers/Farm/SaleController';
```

(b) Add sale-row state near the harvest-row state:

```tsx
    const [saleRowIds, setSaleRowIds] = useState<number[]>([0]);
    const nextSaleRowId = useRef(1);
```

(c) Add a delete button to each sale row in the sales `<Card>`. Change the sale row header flex container to include a delete button next to the date:

```tsx
                                <div className="flex items-center justify-between">
                                    <p className="font-medium">{sale.buyer_name}</p>
                                    <div className="flex items-center gap-3">
                                        <p className="text-muted-foreground text-sm">{sale.sold_on}</p>
                                        <Button
                                            size="sm"
                                            variant="destructive"
                                            onClick={() => {
                                                if (confirm('ลบการขายนี้?')) {
                                                    router.delete(SaleController.destroy.url(sale.id));
                                                }
                                            }}
                                        >
                                            ลบ
                                        </Button>
                                    </div>
                                </div>
```

(d) At the END of the sales `<Card>` (after the list conditional), add the dynamic-row record form:

```tsx
                <div className="mt-4 border-t pt-4">
                    <p className="mb-3 text-sm font-medium">บันทึกการขายใหม่</p>
                    <Form
                        action={saleStore.url(cropCycle.id)}
                        method="post"
                        options={{ preserveScroll: true }}
                        resetOnSuccess
                        onSuccess={() => {
                            setSaleRowIds([0]);
                            nextSaleRowId.current = 1;
                        }}
                        className="grid gap-3"
                    >
                        {({ processing, errors }) => (
                            <>
                                <div className="grid gap-3 sm:grid-cols-2 sm:items-end">
                                    <div className="grid gap-2">
                                        <Label htmlFor="buyer_name">ผู้ซื้อ / ล้ง</Label>
                                        <Input id="buyer_name" name="buyer_name" required placeholder="เช่น ล้งเจ๊แดง" />
                                        <InputError message={errors.buyer_name} />
                                    </div>
                                    <div className="grid gap-2">
                                        <Label htmlFor="sold_on">วันที่ขาย</Label>
                                        <Input id="sold_on" name="sold_on" type="date" required />
                                        <InputError message={errors.sold_on} />
                                    </div>
                                    <div className="grid gap-2 sm:col-span-2">
                                        <Label htmlFor="sale_notes">หมายเหตุ</Label>
                                        <Input id="sale_notes" name="notes" placeholder="หมายเหตุเพิ่มเติม (ถ้ามี)" />
                                    </div>
                                </div>
                                <div className="grid gap-2">
                                    <Label>รายการขายตามเกรด</Label>
                                    {saleRowIds.map((rowId, idx) => (
                                        <div key={rowId} className="flex items-end gap-2">
                                            <div className="grid flex-1 gap-1">
                                                <Select name={`items[${idx}][grade_id]`}>
                                                    <SelectTrigger>
                                                        <SelectValue placeholder="เกรด" />
                                                    </SelectTrigger>
                                                    <SelectContent>
                                                        {grades.map((grade) => (
                                                            <SelectItem key={grade.id} value={String(grade.id)}>
                                                                {grade.name}
                                                            </SelectItem>
                                                        ))}
                                                    </SelectContent>
                                                </Select>
                                                <InputError message={errors[`items.${idx}.grade_id`]} />
                                            </div>
                                            <div className="grid flex-1 gap-1">
                                                <Input
                                                    name={`items[${idx}][weight_kg]`}
                                                    type="number"
                                                    step="0.01"
                                                    min={0.01}
                                                    placeholder="น้ำหนัก (กก.)"
                                                    required
                                                />
                                                <InputError message={errors[`items.${idx}.weight_kg`]} />
                                            </div>
                                            <div className="grid flex-1 gap-1">
                                                <Input
                                                    name={`items[${idx}][price_per_kg]`}
                                                    type="number"
                                                    step="0.01"
                                                    min={0.01}
                                                    placeholder="ราคา/กก."
                                                    required
                                                />
                                                <InputError message={errors[`items.${idx}.price_per_kg`]} />
                                            </div>
                                            {saleRowIds.length > 1 && (
                                                <Button
                                                    type="button"
                                                    size="sm"
                                                    variant="outline"
                                                    onClick={() => setSaleRowIds((ids) => ids.filter((id) => id !== rowId))}
                                                >
                                                    ลบ
                                                </Button>
                                            )}
                                        </div>
                                    ))}
                                    <InputError message={errors.items} />
                                    <Button
                                        type="button"
                                        variant="outline"
                                        className="w-fit"
                                        onClick={() => setSaleRowIds((ids) => [...ids, nextSaleRowId.current++])}
                                    >
                                        + เพิ่มเกรด
                                    </Button>
                                </div>
                                <div>
                                    <Button disabled={processing}>บันทึกการขาย</Button>
                                </div>
                            </>
                        )}
                    </Form>
                </div>
```

- [ ] **Step 9: Build the frontend**

Run: `npm run build`
Expected: build succeeds with no TypeScript errors

- [ ] **Step 10: Format and commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Http/Requests/Farm/StoreSaleRequest.php app/Http/Controllers/Farm/SaleController.php routes/web.php resources/js/pages/farm/crop-cycles/show.tsx tests/Feature/Farm/SaleControllerTest.php
git commit -m "feat: add sale recording with computed subtotals"
```

---

## Task 10: Sidebar navigation (grades)

**Files:**
- Modify: `resources/js/components/app-sidebar.tsx`

- [ ] **Step 1: Add the grades nav item**

In `resources/js/components/app-sidebar.tsx`:

Add `Award` to the lucide-react import (keep all existing icons):

```tsx
import { Award, BookOpen, ClipboardList, FolderGit2, Leaf, LayoutGrid, Sprout, Tags, Trees, Wallet } from 'lucide-react';
```

Add the route import (with the other farm route imports):

```tsx
import { index as gradesIndex } from '@/routes/grades';
```

Add this item to `mainNavItems` directly after the `พันธุ์ผลไม้` item:

```tsx
    {
        title: 'เกรดผลไม้',
        href: gradesIndex(),
        icon: Award,
    },
```

- [ ] **Step 2: Build the frontend**

Run: `npm run build`
Expected: build succeeds. If `Award` is unavailable in the installed lucide-react, substitute an available icon (e.g. `Medal`, `Star`).

- [ ] **Step 3: Run the full farm test suite**

Run: `php artisan test --compact --filter=Farm`
Expected: PASS (all Module 1 + 2 + 3 farm tests)

- [ ] **Step 4: Format and commit**

```bash
vendor/bin/pint --dirty --format agent
git add resources/js/components/app-sidebar.tsx
git commit -m "feat: add grade navigation to sidebar"
```

---

## Final Verification

- [ ] Run the full test suite: `php artisan test --compact`
- [ ] Run linting: `vendor/bin/pint --test --format agent` (ignore pre-existing failures in files this module didn't touch) and `npm run lint`
- [ ] Build: `npm run build`
- [ ] Manually verify: add grades for a fruit type → open a crop cycle → record a harvest with two grade rows → confirm yield total updates → record a sale with a grade row and price → confirm revenue, cost/kg and profit update → delete a harvest and a sale and confirm the summary recalculates

---

## Notes for the implementer

- **Yield ≠ sold weight:** harvest weight and sale weight are intentionally independent (own consumption, gifts, spoilage). Cost/kg uses **harvest** weight as the divisor (`totalDirectCost ÷ totalYield`), per the spec.
- **Subtotal is server-authoritative:** never trust a client-sent subtotal; the controller computes `round(weight_kg × price_per_kg, 2)`. The DB stores it to avoid re-multiplication rounding drift in aggregates.
- **Grades are per fruit type:** the cycle page only offers grades matching the cycle's `fruitVariety.fruit_type_id`. If a cycle has no grades defined for its fruit type, the harvest/sale grade dropdowns will be empty — that's expected; the user adds grades on the เกรดผลไม้ page first.
- **Dynamic line items:** use the array map **index** for the field name (`items[${idx}][...]`) and a stable row id for React `key`. This keeps Laravel's `items.*` indices contiguous after a row removal. The shadcn `<Select name>` emits a hidden input so it serializes inside `<Form>`.
- **`decimal:2` → string:** all weights/prices/subtotals/amounts serialize as strings; use `Number(...)` for math and `.toLocaleString('th-TH', { minimumFractionDigits: 2 })` for currency display.
- **Wayfinder imports:** run `php artisan wayfinder:generate` after route changes; never `git add resources/js/actions` or `resources/js/routes` (gitignored).
- **Thai everywhere** for visible strings, validation messages, toasts; English identifiers.
