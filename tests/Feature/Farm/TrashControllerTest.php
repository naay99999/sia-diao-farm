<?php

use App\Models\CareRecord;
use App\Models\Expense;
use App\Models\FarmNote;
use App\Models\Harvest;
use App\Models\Observation;
use App\Models\Sale;
use App\Models\Tree;
use App\Models\TreeIssue;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->actingAs(User::factory()->create());
});

test('trash lists deleted records of every type', function () {
    $live = CareRecord::factory()->create();

    foreach ([CareRecord::class, Harvest::class, Sale::class, Expense::class, Observation::class, TreeIssue::class, FarmNote::class] as $class) {
        $class::factory()->create()->delete();
    }

    $this->get(route('records.trash'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('records/trash')
            ->has('items', 7)
            ->has('items.0', fn (Assert $item) => $item
                ->hasAll(['type', 'id', 'kind', 'title', 'subject', 'deletedAt'])
                ->etc())
            ->where('items', fn ($items) => collect($items)->doesntContain(fn ($item) => $item['type'] === 'care' && $item['id'] === $live->id)));
});

test('restoring brings the record back', function () {
    $harvest = Harvest::factory()->create();
    $harvest->delete();

    $this->patch(route('records.trash.restore', ['harvest', $harvest->id]))
        ->assertRedirect(route('records.trash'));

    expect($harvest->fresh()->trashed())->toBeFalse();
});

test('restoring an open issue beside another open one shows the Thai error', function () {
    $tree = Tree::factory()->create();
    $first = TreeIssue::factory()->create(['tree_id' => $tree->id, 'resolved_on' => null]);
    $first->delete();
    TreeIssue::factory()->create(['tree_id' => $tree->id, 'finding_id' => $first->finding_id, 'resolved_on' => null]);

    $this->from(route('records.trash'))
        ->patch(route('records.trash.restore', ['issue', $first->id]))
        ->assertSessionHasErrors(['id' => 'ต้นนี้มีปัญหาเดียวกันที่ยังเปิดอยู่ ให้บันทึกการดูซ้ำในเรื่องเดิมแทน']);

    expect(TreeIssue::withTrashed()->find($first->id)->trashed())->toBeTrue();
});

test('restoring a live record or a missing id fails', function () {
    $sale = Sale::factory()->create();

    $this->from(route('records.trash'))
        ->patch(route('records.trash.restore', ['sale', $sale->id]))
        ->assertSessionHasErrors(['id' => 'บันทึกนี้ไม่ได้อยู่ในถังขยะ']);

    $this->patch(route('records.trash.restore', ['sale', 999999]))->assertNotFound();
});

test('unknown trash type is not found', function () {
    $this->patch('/records/trash/banana/1')->assertNotFound();
});
