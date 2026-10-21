<?php

use App\Enums\ExpenseCategory;
use App\Models\Expense;
use App\Models\Orchard;
use App\Models\Season;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->user = User::factory()->create();
});

test('expense stores without a season', function () {
    $this->actingAs($this->user)
        ->post(route('expenses.store'), [
            'category' => 'labor', 'spent_on' => '2026-01-10', 'amount' => '1500.5',
        ])
        ->assertRedirect(route('expenses.index'));

    $this->assertDatabaseHas('expenses', ['season_id' => null, 'category' => 'labor', 'amount' => '1500.50']);
});

test('expense stores with a season', function () {
    $season = Season::factory()->create();

    $this->actingAs($this->user)
        ->post(route('expenses.store'), [
            'season_id' => $season->id, 'category' => 'fertilizer', 'spent_on' => '2026-01-10', 'amount' => 900, 'notes' => 'ปุ๋ยคอก',
        ])
        ->assertSessionHasNoErrors();

    $this->assertDatabaseHas('expenses', ['season_id' => $season->id, 'notes' => 'ปุ๋ยคอก']);
});

test('expense validates category, amount and season', function (array $data, string $error) {
    $this->actingAs($this->user)
        ->post(route('expenses.store'), ['category' => 'labor', 'spent_on' => '2026-01-10', 'amount' => 100, ...$data])
        ->assertSessionHasErrors($error);

    expect(Expense::count())->toBe(0);
})->with([
    'invalid category' => [['category' => 'bogus'], 'category'],
    'zero amount' => [['amount' => 0], 'amount'],
    'negative amount' => [['amount' => -1], 'amount'],
    'unknown season' => [['season_id' => 999], 'season_id'],
]);

test('expense updates and destroys', function () {
    $expense = Expense::factory()->create();

    $this->actingAs($this->user)
        ->patch(route('expenses.update', $expense), ['category' => 'equipment', 'spent_on' => '2026-02-02', 'amount' => 250])
        ->assertRedirect(route('expenses.index'));

    expect($expense->refresh()->category)->toBe(ExpenseCategory::Equipment)
        ->and($expense->amount)->toBe('250.00');

    $this->actingAs($this->user)
        ->delete(route('expenses.destroy', $expense))
        ->assertRedirect(route('expenses.index'));

    $this->assertModelMissing($expense);
});

test('index filters by inclusive date range and totals the filtered set', function () {
    Expense::factory()->create(['spent_on' => '2026-01-01', 'amount' => 100, 'category' => 'labor']);
    Expense::factory()->create(['spent_on' => '2026-01-31', 'amount' => 200, 'category' => 'labor']);
    Expense::factory()->create(['spent_on' => '2026-02-01', 'amount' => 400, 'category' => 'labor']);

    $this->actingAs($this->user)
        ->get(route('expenses.index', ['from' => '2026-01-01', 'to' => '2026-01-31']))
        ->assertInertia(fn (Assert $page) => $page
            ->has('expenses', 2)
            ->where('total', 300)
            ->has('categories', 6));
});

test('index lists newest first without filters', function () {
    Expense::factory()->create(['spent_on' => '2026-01-01']);
    Expense::factory()->create(['spent_on' => '2026-03-01']);

    $this->actingAs($this->user)
        ->get(route('expenses.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->has('expenses', 2)
            ->where('expenses.0.spent_on', fn ($value) => str_starts_with($value, '2026-03-01')));
});

test('index groups totals by category', function () {
    Expense::factory()->create(['category' => 'labor', 'amount' => 100]);
    Expense::factory()->create(['category' => 'labor', 'amount' => 50]);
    Expense::factory()->create(['category' => 'fertilizer', 'amount' => 30]);

    $this->actingAs($this->user)
        ->get(route('expenses.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('total', 180)
            ->has('totalsByCategory', 2)
            ->where('totalsByCategory.0', ['category' => 'labor', 'label' => 'ค่าแรง', 'total' => 150])
            ->where('totalsByCategory.1', ['category' => 'fertilizer', 'label' => 'ปุ๋ย', 'total' => 30]));
});

test('index labels seasons with their orchard', function () {
    $orchard = Orchard::factory()->create(['name' => 'สวนบน']);
    Season::factory()->for($orchard)->create(['name' => 'ฤดู 2569']);

    $this->actingAs($this->user)
        ->get(route('expenses.index'))
        ->assertInertia(fn (Assert $page) => $page->where('seasons.0.label', 'สวนบน · ฤดู 2569'));
});
