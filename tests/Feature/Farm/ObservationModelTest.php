<?php

use App\Enums\FindingKind;
use App\Models\Finding;
use App\Models\Observation;
use App\Models\Orchard;
use App\Models\Tree;
use App\Models\TreeIssue;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Date;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->orchard = Orchard::factory()->create();
});

test('default findings are seeded by migration', function () {
    expect(Finding::where('name', 'ไรแดง')->firstOrFail()->kind)->toBe(FindingKind::Problem)
        ->and(Finding::where('name', 'ออกดอก')->firstOrFail()->kind)->toBe(FindingKind::Stage)
        ->and(Finding::where('kind', FindingKind::Problem)->count())->toBe(8)
        ->and(Finding::where('kind', FindingKind::Stage)->count())->toBe(6)
        ->and(Finding::count())->toBe(14);
});

test('resolveByName reuses existing finding and creates new ones', function () {
    $existing = Finding::where('name', 'ไรแดง')->firstOrFail();

    expect(Finding::resolveByName('  ไรแดง ', FindingKind::Problem)->id)->toBe($existing->id)
        ->and(Finding::count())->toBe(14);

    $created = Finding::resolveByName('ราดำ', FindingKind::Problem);

    expect($created->name)->toBe('ราดำ')
        ->and($created->kind)->toBe(FindingKind::Problem)
        ->and(Finding::count())->toBe(15);
});

test('due scope returns open issues due today or overdue on active trees', function () {
    Date::setTestNow('2026-10-21');
    $today = Carbon::parse('2026-10-21');
    $tree = Tree::factory()->for($this->orchard)->create();

    $dueToday = TreeIssue::factory()->for($tree)->create(['follow_up_on' => '2026-10-21']);
    $overdue = TreeIssue::factory()->for($tree)->create(['follow_up_on' => '2026-10-15']);
    TreeIssue::factory()->for($tree)->create(['follow_up_on' => '2026-10-22']);
    TreeIssue::factory()->for($tree)->resolved()->create(['follow_up_on' => '2026-10-15']);
    TreeIssue::factory()->for(Tree::factory()->for($this->orchard)->removed())->create(['follow_up_on' => '2026-10-15']);

    expect(TreeIssue::query()->due($today)->pluck('id')->sort()->values()->all())
        ->toBe([$dueToday->id, $overdue->id])
        ->and(TreeIssue::query()->open()->count())->toBe(4);
});

test('tree with observations cannot be hard deleted', function () {
    $tree = Tree::factory()->for($this->orchard)->create();
    Observation::factory()->for($tree)->create();

    $this->actingAs($this->user)
        ->delete(route('trees.destroy', $tree))
        ->assertSessionHasErrors('tree');

    expect(Tree::find($tree->id))->not->toBeNull();
});

test('tree with only a tree issue or a soft deleted observation cannot be hard deleted', function () {
    $withIssue = Tree::factory()->for($this->orchard)->create();
    TreeIssue::factory()->for($withIssue)->create();

    $withTrashedObservation = Tree::factory()->for($this->orchard)->create();
    Observation::factory()->for($withTrashedObservation)->create()->delete();

    expect($withIssue->hasRecords())->toBeTrue()
        ->and($withTrashedObservation->hasRecords())->toBeTrue();
});

test('observation factory states build problem and stage observations', function () {
    $problem = Observation::factory()->problem()->create();
    $stage = Observation::factory()->stage()->create();

    expect($problem->finding->kind)->toBe(FindingKind::Problem)
        ->and($stage->finding->kind)->toBe(FindingKind::Stage);
});

test('issues and observations track provenance and soft delete', function () {
    $issue = TreeIssue::factory()->create();
    $observation = Observation::factory()->create(['tree_issue_id' => $issue->id]);

    expect($issue->created_via)->toBe('app')
        ->and($observation->isFromAi())->toBeFalse();

    $observation->delete();
    $issue->delete();

    $this->assertSoftDeleted($observation);
    $this->assertSoftDeleted($issue);
});
