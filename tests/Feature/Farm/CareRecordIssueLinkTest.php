<?php

use App\Actions\Farm\SaveCareRecord;
use App\Enums\CareType;
use App\Models\CareRecord;
use App\Models\Orchard;
use App\Models\Tree;
use App\Models\TreeIssue;
use App\Models\User;
use Illuminate\Support\Facades\Date;
use Illuminate\Validation\ValidationException;

beforeEach(function () {
    Date::setTestNow('2026-10-21');

    $this->user = User::factory()->create();
    $this->orchard = Orchard::factory()->create();
    $this->tree = Tree::factory()->for($this->orchard)->create();
    $this->issue = TreeIssue::factory()->for($this->tree)->create([
        'opened_on' => '2026-10-21',
        'follow_up_on' => '2026-10-28',
    ]);
});

function pesticidePayload(array $treeIds, array $issueIds, array $overrides = []): array
{
    return array_merge([
        'type' => 'pesticide',
        'performed_on' => '2026-10-25',
        'product' => 'อะบาเมกติน',
        'tree_ids' => $treeIds,
        'issue_ids' => $issueIds,
    ], $overrides);
}

test('linking an issue moves its follow up to care date plus seven', function () {
    $this->actingAs($this->user)
        ->post(route('orchards.care-records.store', $this->orchard), pesticidePayload([$this->tree->id], [$this->issue->id]))
        ->assertSessionDoesntHaveErrors();

    $record = CareRecord::firstOrFail();

    expect($record->issues()->pluck('tree_issues.id')->all())->toBe([$this->issue->id])
        ->and($this->issue->fresh()->follow_up_on->toDateString())->toBe('2026-11-01')
        ->and($this->issue->fresh()->isOpen())->toBeTrue();
});

test('care record without issue ids links nothing and leaves follow up alone', function () {
    $this->actingAs($this->user)
        ->post(route('orchards.care-records.store', $this->orchard), pesticidePayload([$this->tree->id], []))
        ->assertSessionDoesntHaveErrors();

    expect(CareRecord::firstOrFail()->issues()->count())->toBe(0)
        ->and($this->issue->fresh()->follow_up_on->toDateString())->toBe('2026-10-28');
});

test('issue from a tree not on the record is rejected', function () {
    $otherTree = Tree::factory()->for($this->orchard)->create();

    $this->actingAs($this->user)
        ->post(route('orchards.care-records.store', $this->orchard), pesticidePayload([$otherTree->id], [$this->issue->id]))
        ->assertSessionHasErrors('issue_ids');

    expect(CareRecord::count())->toBe(0)
        ->and($this->issue->fresh()->follow_up_on->toDateString())->toBe('2026-10-28');
});

test('resolved issue cannot be newly linked', function () {
    $resolved = TreeIssue::factory()->for($this->tree)->resolved()->create();

    $this->actingAs($this->user)
        ->post(route('orchards.care-records.store', $this->orchard), pesticidePayload([$this->tree->id], [$resolved->id]))
        ->assertSessionHasErrors('issue_ids');

    expect(CareRecord::count())->toBe(0);
});

test('already linked issue stays linked after it is resolved and keeps its follow up', function () {
    $record = CareRecord::factory()->for($this->orchard)->create(['type' => CareType::Pesticide, 'performed_on' => '2026-10-25']);
    $record->trees()->sync([$this->tree->id]);
    $record->issues()->sync([$this->issue->id]);
    $this->issue->update(['resolved_on' => '2026-10-26', 'follow_up_on' => '2026-11-01']);

    $this->actingAs($this->user)
        ->patch(route('care-records.update', $record), pesticidePayload([$this->tree->id], [$this->issue->id], ['performed_on' => '2026-10-27']))
        ->assertSessionDoesntHaveErrors();

    expect($record->issues()->pluck('tree_issues.id')->all())->toBe([$this->issue->id])
        ->and($this->issue->fresh()->follow_up_on->toDateString())->toBe('2026-11-01');
});

test('deleting a linked care record keeps the issue open and follow up unchanged', function () {
    $this->actingAs($this->user)
        ->post(route('orchards.care-records.store', $this->orchard), pesticidePayload([$this->tree->id], [$this->issue->id]));
    $record = CareRecord::firstOrFail();

    $this->actingAs($this->user)
        ->delete(route('care-records.destroy', $record))
        ->assertRedirect();

    $this->assertSoftDeleted($record);

    $issue = $this->issue->fresh();
    expect($issue->isOpen())->toBeTrue()
        ->and($issue->deleted_at)->toBeNull()
        ->and($issue->follow_up_on->toDateString())->toBe('2026-11-01');
});

test('removing a tree from a record unlinks its issues', function () {
    $otherTree = Tree::factory()->for($this->orchard)->create();
    $otherIssue = TreeIssue::factory()->for($otherTree)->create(['follow_up_on' => '2026-10-28']);

    $this->actingAs($this->user)
        ->post(route('orchards.care-records.store', $this->orchard), pesticidePayload([$this->tree->id, $otherTree->id], [$this->issue->id, $otherIssue->id]));
    $record = CareRecord::firstOrFail();
    expect($record->issues()->count())->toBe(2);

    $this->actingAs($this->user)
        ->patch(route('care-records.update', $record), pesticidePayload([$otherTree->id], [$otherIssue->id]))
        ->assertSessionDoesntHaveErrors();

    expect($record->issues()->pluck('tree_issues.id')->all())->toBe([$otherIssue->id])
        ->and($this->issue->fresh()->follow_up_on->toDateString())->toBe('2026-11-01')
        ->and($this->issue->fresh()->isOpen())->toBeTrue();
});

test('removing a tree while still sending its linked issue id unlinks it without error', function () {
    $otherTree = Tree::factory()->for($this->orchard)->create();
    $otherIssue = TreeIssue::factory()->for($otherTree)->create(['follow_up_on' => '2026-10-28']);

    $this->actingAs($this->user)
        ->post(route('orchards.care-records.store', $this->orchard), pesticidePayload([$this->tree->id, $otherTree->id], [$this->issue->id, $otherIssue->id]));
    $record = CareRecord::firstOrFail();

    $this->actingAs($this->user)
        ->patch(route('care-records.update', $record), pesticidePayload([$otherTree->id], [$this->issue->id, $otherIssue->id]))
        ->assertSessionDoesntHaveErrors();

    expect($record->issues()->pluck('tree_issues.id')->all())->toBe([$otherIssue->id]);
});

test('unlinking an issue from a record keeps its follow up date', function () {
    $this->actingAs($this->user)
        ->post(route('orchards.care-records.store', $this->orchard), pesticidePayload([$this->tree->id], [$this->issue->id]));
    $record = CareRecord::firstOrFail();

    $this->actingAs($this->user)
        ->patch(route('care-records.update', $record), pesticidePayload([$this->tree->id], [], ['performed_on' => '2026-10-30']))
        ->assertSessionDoesntHaveErrors();

    expect($record->issues()->count())->toBe(0)
        ->and($this->issue->fresh()->follow_up_on->toDateString())->toBe('2026-11-01');
});

test('relinking only moves the follow up of newly linked issues', function () {
    $second = TreeIssue::factory()->for($this->tree)->create(['follow_up_on' => '2026-10-28']);

    $this->actingAs($this->user)
        ->post(route('orchards.care-records.store', $this->orchard), pesticidePayload([$this->tree->id], [$this->issue->id]));
    $record = CareRecord::firstOrFail();

    $this->actingAs($this->user)
        ->patch(route('care-records.update', $record), pesticidePayload([$this->tree->id], [$this->issue->id, $second->id], ['performed_on' => '2026-11-03']))
        ->assertSessionDoesntHaveErrors();

    expect($this->issue->fresh()->follow_up_on->toDateString())->toBe('2026-11-01')
        ->and($second->fresh()->follow_up_on->toDateString())->toBe('2026-11-10');
});

test('default issue ids are open issues for pesticide only', function () {
    $resolved = TreeIssue::factory()->for($this->tree)->resolved()->create();
    $untouched = TreeIssue::factory()->for(Tree::factory()->for($this->orchard))->create();
    $action = app(SaveCareRecord::class);

    expect($action->defaultIssueIds(CareType::Pesticide, [$this->tree->id]))->toBe([$this->issue->id])
        ->and($action->defaultIssueIds(CareType::Pesticide, [$this->tree->id, $untouched->tree_id]))->toBe([$this->issue->id, $untouched->id])
        ->and($action->defaultIssueIds(CareType::Fertilizer, [$this->tree->id]))->toBe([])
        ->and($action->defaultIssueIds(CareType::Other, [$this->tree->id]))->toBe([])
        ->and($action->defaultIssueIds(CareType::Pesticide, []))->toBe([])
        ->and($resolved->isOpen())->toBeFalse();
});

test('action creates and updates a record and rejects trees outside the orchard', function () {
    $action = app(SaveCareRecord::class);

    $record = $action->handle($this->orchard, [
        'type' => CareType::Pesticide,
        'performed_on' => '2026-10-25',
        'tree_ids' => [$this->tree->id],
        'issue_ids' => [$this->issue->id],
    ]);

    expect($record->orchard_id)->toBe($this->orchard->id)
        ->and($record->type)->toBe(CareType::Pesticide)
        ->and($this->issue->fresh()->follow_up_on->toDateString())->toBe('2026-11-01');

    $updated = $action->handle($this->orchard, [
        'type' => 'fertilizer',
        'performed_on' => '2026-10-26',
        'product' => '15-15-15',
        'tree_ids' => [$this->tree->id],
    ], $record);

    expect($updated->is($record))->toBeTrue()
        ->and($updated->type)->toBe(CareType::Fertilizer)
        ->and($updated->product)->toBe('15-15-15')
        ->and($updated->issues()->pluck('tree_issues.id')->all())->toBe([$this->issue->id]);

    $foreign = Tree::factory()->create();

    expect(fn () => $action->handle($this->orchard, [
        'type' => 'fertilizer',
        'performed_on' => '2026-10-26',
        'tree_ids' => [$foreign->id],
    ]))->toThrow(ValidationException::class);

    expect(CareRecord::count())->toBe(1);
});
