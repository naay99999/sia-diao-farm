<?php

use App\Enums\FindingKind;
use App\Enums\FollowUpOutcome;
use App\Enums\Severity;
use App\Models\Finding;
use App\Models\Observation;
use App\Models\ObservationPhoto;
use App\Models\Orchard;
use App\Models\Tree;
use App\Models\TreeIssue;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Date::setTestNow('2026-10-21');
    Storage::fake('photos');

    $this->user = User::factory()->create();
    $this->orchard = Orchard::factory()->create();
    $this->tree = Tree::factory()->for($this->orchard)->create();
    $this->redMite = Finding::where('name', 'ไรแดง')->firstOrFail();
    $this->flowering = Finding::where('name', 'ออกดอก')->firstOrFail();
});

test('problem observation opens an issue with follow up seven days later', function () {
    $this->actingAs($this->user)
        ->post(route('trees.observations.store', $this->tree), [
            'finding_id' => $this->redMite->id,
            'observed_on' => '2026-10-21',
            'severity' => 'high',
            'notes' => 'ใบม้วนทั้งกิ่ง',
        ])
        ->assertRedirect(route('trees.show', $this->tree))
        ->assertSessionHasNoErrors();

    $issue = TreeIssue::sole();
    $observation = Observation::sole();

    expect($issue->tree_id)->toBe($this->tree->id)
        ->and($issue->finding_id)->toBe($this->redMite->id)
        ->and($issue->opened_on->toDateString())->toBe('2026-10-21')
        ->and($issue->follow_up_on->toDateString())->toBe('2026-10-28')
        ->and($issue->resolved_on)->toBeNull()
        ->and($observation->tree_issue_id)->toBe($issue->id)
        ->and($observation->severity)->toBe(Severity::High)
        ->and($observation->notes)->toBe('ใบม้วนทั้งกิ่ง');
});

test('explicit follow up date is used when opening an issue', function () {
    $this->actingAs($this->user)
        ->post(route('trees.observations.store', $this->tree), [
            'finding_id' => $this->redMite->id,
            'observed_on' => '2026-10-21',
            'follow_up_on' => '2026-10-24',
        ])
        ->assertSessionHasNoErrors();

    expect(TreeIssue::sole()->follow_up_on->toDateString())->toBe('2026-10-24');
});

test('same problem on same tree appends to the open issue', function () {
    $this->actingAs($this->user)
        ->post(route('trees.observations.store', $this->tree), [
            'finding_id' => $this->redMite->id, 'observed_on' => '2026-10-21',
        ]);

    $this->actingAs($this->user)
        ->post(route('trees.observations.store', $this->tree), [
            'finding_id' => $this->redMite->id, 'observed_on' => '2026-10-23',
        ])
        ->assertRedirect(route('trees.show', $this->tree));

    $issue = TreeIssue::sole();

    expect(Observation::count())->toBe(2)
        ->and(Observation::where('tree_issue_id', $issue->id)->count())->toBe(2)
        ->and($issue->follow_up_on->toDateString())->toBe('2026-10-28');
});

test('appending to an open issue changes the follow up date only when one is given', function () {
    $issue = TreeIssue::factory()->for($this->tree)->create([
        'finding_id' => $this->redMite->id, 'follow_up_on' => '2026-10-28',
    ]);

    $this->actingAs($this->user)
        ->post(route('trees.observations.store', $this->tree), [
            'finding_id' => $this->redMite->id, 'observed_on' => '2026-10-22', 'follow_up_on' => '2026-10-25',
        ]);

    expect(TreeIssue::count())->toBe(1)
        ->and($issue->fresh()->follow_up_on->toDateString())->toBe('2026-10-25');
});

test('same problem opens a new issue once the previous one is resolved', function () {
    TreeIssue::factory()->resolved()->for($this->tree)->create(['finding_id' => $this->redMite->id]);

    $this->actingAs($this->user)
        ->post(route('trees.observations.store', $this->tree), [
            'finding_id' => $this->redMite->id, 'observed_on' => '2026-10-21',
        ]);

    expect(TreeIssue::count())->toBe(2)
        ->and(TreeIssue::open()->count())->toBe(1);
});

test('same problem on another tree opens its own issue', function () {
    $other = Tree::factory()->for($this->orchard)->create();
    TreeIssue::factory()->for($other)->create(['finding_id' => $this->redMite->id]);

    $this->actingAs($this->user)
        ->post(route('trees.observations.store', $this->tree), [
            'finding_id' => $this->redMite->id, 'observed_on' => '2026-10-21',
        ]);

    expect(TreeIssue::count())->toBe(2);
});

test('stage observation does not open an issue', function () {
    $this->actingAs($this->user)
        ->post(route('trees.observations.store', $this->tree), [
            'finding_id' => $this->flowering->id, 'observed_on' => '2026-10-21',
        ])
        ->assertRedirect(route('trees.show', $this->tree));

    expect(TreeIssue::count())->toBe(0)
        ->and(Observation::sole()->tree_issue_id)->toBeNull();
});

test('new finding name is created', function () {
    $this->actingAs($this->user)
        ->post(route('trees.observations.store', $this->tree), [
            'finding_name' => ' ราดำ ', 'kind' => 'problem', 'observed_on' => '2026-10-21',
        ])
        ->assertSessionHasNoErrors();

    $finding = Finding::where('name', 'ราดำ')->firstOrFail();

    expect($finding->kind)->toBe(FindingKind::Problem)
        ->and(Observation::sole()->finding_id)->toBe($finding->id)
        ->and(TreeIssue::count())->toBe(1);
});

test('existing finding name is reused', function () {
    $count = Finding::count();

    $this->actingAs($this->user)
        ->post(route('trees.observations.store', $this->tree), [
            'finding_name' => 'ไรแดง', 'kind' => 'problem', 'observed_on' => '2026-10-21',
        ]);

    expect(Finding::count())->toBe($count)
        ->and(Observation::sole()->finding_id)->toBe($this->redMite->id);
});

test('observation requires a finding and a date', function () {
    $this->actingAs($this->user)
        ->post(route('trees.observations.store', $this->tree), [])
        ->assertSessionHasErrors(['finding_id', 'finding_name', 'observed_on']);

    $this->actingAs($this->user)
        ->post(route('trees.observations.store', $this->tree), [
            'finding_name' => 'ราดำ', 'observed_on' => '2026-10-21',
        ])
        ->assertSessionHasErrors('kind');

    expect(Observation::count())->toBe(0);
});

test('removed tree rejects observations', function () {
    $tree = Tree::factory()->for($this->orchard)->removed()->create();

    $this->actingAs($this->user)
        ->post(route('trees.observations.store', $tree), [
            'finding_id' => $this->redMite->id, 'observed_on' => '2026-10-21',
        ])
        ->assertSessionHasErrors('tree');

    expect(Observation::count())->toBe(0)->and(TreeIssue::count())->toBe(0);
});

test('photos are stored on photos disk', function () {
    $this->actingAs($this->user)
        ->post(route('trees.observations.store', $this->tree), [
            'finding_id' => $this->redMite->id,
            'observed_on' => '2026-10-21',
            'photos' => [
                UploadedFile::fake()->image('a.jpg'),
                UploadedFile::fake()->image('b.png'),
            ],
        ])
        ->assertSessionHasNoErrors();

    $observation = Observation::sole();

    expect(ObservationPhoto::count())->toBe(2);

    foreach ($observation->photos as $photo) {
        expect($photo->path)->toStartWith("observations/{$observation->id}/");
        Storage::disk('photos')->assertExists($photo->path);
    }
});

test('a fourth photo is rejected', function () {
    $this->actingAs($this->user)
        ->post(route('trees.observations.store', $this->tree), [
            'finding_id' => $this->redMite->id,
            'observed_on' => '2026-10-21',
            'photos' => [
                UploadedFile::fake()->image('a.jpg'),
                UploadedFile::fake()->image('b.jpg'),
                UploadedFile::fake()->image('c.jpg'),
                UploadedFile::fake()->image('d.jpg'),
            ],
        ])
        ->assertSessionHasErrors('photos');

    expect(Observation::count())->toBe(0)->and(ObservationPhoto::count())->toBe(0);
});

test('oversized or non image photos are rejected', function () {
    $this->actingAs($this->user)
        ->post(route('trees.observations.store', $this->tree), [
            'finding_id' => $this->redMite->id,
            'observed_on' => '2026-10-21',
            'photos' => [
                UploadedFile::fake()->image('big.jpg')->size(8193),
                UploadedFile::fake()->create('notes.pdf', 10, 'application/pdf'),
            ],
        ])
        ->assertSessionHasErrors(['photos.0', 'photos.1']);

    expect(Observation::count())->toBe(0);
});

test('follow up resolved closes the issue', function () {
    $issue = TreeIssue::factory()->for($this->tree)->create(['finding_id' => $this->redMite->id]);

    $this->actingAs($this->user)
        ->post(route('tree-issues.follow-ups.store', $issue), [
            'observed_on' => '2026-10-28', 'outcome' => 'resolved', 'notes' => 'หายดี',
        ])
        ->assertRedirect(route('trees.show', $this->tree))
        ->assertSessionHasNoErrors();

    $followUp = Observation::sole();

    expect($issue->fresh()->resolved_on->toDateString())->toBe('2026-10-28')
        ->and($followUp->tree_issue_id)->toBe($issue->id)
        ->and($followUp->tree_id)->toBe($this->tree->id)
        ->and($followUp->finding_id)->toBe($this->redMite->id)
        ->and($followUp->outcome)->toBe(FollowUpOutcome::Resolved)
        ->and($followUp->notes)->toBe('หายดี');
});

test('follow up not resolved pushes follow up date', function () {
    $issue = TreeIssue::factory()->for($this->tree)->create(['follow_up_on' => '2026-10-28']);

    $this->actingAs($this->user)
        ->post(route('tree-issues.follow-ups.store', $issue), [
            'observed_on' => '2026-10-28', 'outcome' => 'not_resolved',
        ])
        ->assertSessionHasNoErrors();

    $issue->refresh();

    expect($issue->resolved_on)->toBeNull()
        ->and($issue->follow_up_on->toDateString())->toBe('2026-11-04')
        ->and(Observation::sole()->outcome)->toBe(FollowUpOutcome::NotResolved);
});

test('follow up not resolved accepts a custom next date and photos', function () {
    $issue = TreeIssue::factory()->for($this->tree)->create();

    $this->actingAs($this->user)
        ->post(route('tree-issues.follow-ups.store', $issue), [
            'observed_on' => '2026-10-28',
            'outcome' => 'not_resolved',
            'next_follow_up_on' => '2026-10-31',
            'photos' => [UploadedFile::fake()->image('a.jpg')],
        ])
        ->assertSessionHasNoErrors();

    expect($issue->fresh()->follow_up_on->toDateString())->toBe('2026-10-31')
        ->and(ObservationPhoto::count())->toBe(1);
});

test('follow up on closed issue is rejected', function () {
    $issue = TreeIssue::factory()->resolved()->for($this->tree)->create();

    $this->actingAs($this->user)
        ->post(route('tree-issues.follow-ups.store', $issue), [
            'observed_on' => '2026-10-28', 'outcome' => 'resolved',
        ])
        ->assertSessionHasErrors('issue');

    expect(Observation::count())->toBe(0);
});

test('follow up on a removed tree is rejected', function () {
    $tree = Tree::factory()->for($this->orchard)->removed()->create();
    $issue = TreeIssue::factory()->for($tree)->create();

    $this->actingAs($this->user)
        ->post(route('tree-issues.follow-ups.store', $issue), [
            'observed_on' => '2026-10-28', 'outcome' => 'resolved',
        ])
        ->assertSessionHasErrors('tree');

    expect(Observation::count())->toBe(0)->and($issue->fresh()->isOpen())->toBeTrue();
});

test('follow up requires a valid outcome', function () {
    $issue = TreeIssue::factory()->for($this->tree)->create();

    $this->actingAs($this->user)
        ->post(route('tree-issues.follow-ups.store', $issue), ['observed_on' => '2026-10-28', 'outcome' => 'maybe'])
        ->assertSessionHasErrors('outcome');
});

test('update changes only date severity and notes', function () {
    $other = Tree::factory()->for($this->orchard)->create();
    $observation = Observation::factory()->for($this->tree)->create([
        'finding_id' => $this->redMite->id,
        'observed_on' => '2026-10-20',
        'severity' => Severity::Low,
        'notes' => 'เดิม',
    ]);

    $this->actingAs($this->user)
        ->patch(route('observations.update', $observation), [
            'observed_on' => '2026-10-19',
            'severity' => 'high',
            'notes' => 'แก้ไข',
            'tree_id' => $other->id,
            'finding_id' => $this->flowering->id,
        ])
        ->assertRedirect(route('trees.show', $this->tree))
        ->assertSessionHasNoErrors();

    $observation->refresh();

    expect($observation->observed_on->toDateString())->toBe('2026-10-19')
        ->and($observation->severity)->toBe(Severity::High)
        ->and($observation->notes)->toBe('แก้ไข')
        ->and($observation->tree_id)->toBe($this->tree->id)
        ->and($observation->finding_id)->toBe($this->redMite->id);
});

test('update validates severity and date', function () {
    $observation = Observation::factory()->for($this->tree)->create();

    $this->actingAs($this->user)
        ->patch(route('observations.update', $observation), ['observed_on' => '', 'severity' => 'extreme'])
        ->assertSessionHasErrors(['observed_on', 'severity']);
});

test('destroy soft deletes observation', function () {
    $observation = Observation::factory()->for($this->tree)->create();

    $this->actingAs($this->user)
        ->delete(route('observations.destroy', $observation))
        ->assertRedirect(route('trees.show', $this->tree));

    $this->assertSoftDeleted($observation);
});

test('guests cannot record observations', function () {
    $this->post(route('trees.observations.store', $this->tree), [
        'finding_id' => $this->redMite->id, 'observed_on' => '2026-10-21',
    ])->assertRedirect(route('login'));
});
