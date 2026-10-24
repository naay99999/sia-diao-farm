<?php

use App\Enums\RestoreConflict;
use App\Models\Finding;
use App\Models\Harvest;
use App\Models\Tree;
use App\Models\TreeIssue;
use App\Support\RecordRestorer;

test('conflict is null for a deleted record', function () {
    $harvest = Harvest::factory()->create();
    $harvest->delete();

    expect(RecordRestorer::conflict($harvest))->toBeNull();
});

test('not deleted records report NotDeleted', function () {
    $harvest = Harvest::factory()->create();

    expect(RecordRestorer::conflict($harvest))->toBe(RestoreConflict::NotDeleted);
});

test('app restore refuses an open issue beside another open one', function () {
    $tree = Tree::factory()->create();
    $finding = Finding::factory()->problem()->create();
    $trashed = TreeIssue::factory()->create(['tree_id' => $tree->id, 'finding_id' => $finding->id]);
    $trashed->delete();
    TreeIssue::factory()->create(['tree_id' => $tree->id, 'finding_id' => $finding->id]);

    expect(RecordRestorer::conflict($trashed))->toBe(RestoreConflict::OpenIssueExists);
    expect(fn () => RecordRestorer::restore($trashed))->toThrow(LogicException::class);
    expect($trashed->fresh()->trashed())->toBeTrue();
});

test('restore brings the record back', function () {
    $harvest = Harvest::factory()->create();
    $harvest->delete();

    RecordRestorer::restore($harvest);

    expect(Harvest::query()->find($harvest->id))->not->toBeNull();
});
