<?php

use App\Models\CareRecord;
use App\Models\Expense;
use App\Models\Harvest;
use App\Models\Observation;
use App\Models\Orchard;
use App\Models\Sale;
use App\Models\Season;
use App\Models\Tree;
use App\Models\TreeIssue;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->owner = User::factory()->create();
    $this->actingAs($this->owner);
});

/**
 * Create a record, then stamp who wrote it and how, since provenance is filled from the session.
 */
function stampProvenance(CareRecord|Observation|Harvest|Sale|Expense $record, User $user, string $via): void
{
    $record->newQuery()->whereKey($record->getKey())->update([
        'created_by' => $user->id,
        'created_via' => $via,
    ]);
}

test('guests are sent to login', function () {
    auth()->logout();

    $this->get(route('records.index', 'care'))->assertRedirect(route('login'));
});

test('records index lists each type newest first', function (string $type) {
    $dates = ['2026-01-01', '2026-03-01', '2026-02-01'];

    foreach ($dates as $date) {
        match ($type) {
            'care' => CareRecord::factory()->create(['performed_on' => $date]),
            'observations' => Observation::factory()->create(['observed_on' => $date]),
            'harvests' => Harvest::factory()->create(['harvested_on' => $date]),
            'sales' => Sale::factory()->create(['sold_on' => $date]),
        };
    }

    $dateKey = ['care' => 'performed_on', 'observations' => 'observed_on', 'harvests' => 'harvested_on', 'sales' => 'sold_on'][$type];

    $this->get(route('records.index', $type))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('records/index')
            ->where('type', $type)
            ->has('records.data', 3)
            ->where('records.data.0.'.$dateKey, '2026-03-01')
            ->where('records.data.1.'.$dateKey, '2026-02-01')
            ->where('records.data.0.fromAi', false)
            ->where('records.data.0.aiBy', null)
            ->where('records.data.0.createdBy', $this->owner->name)
            ->has('records.data.0.orchard.name')
            ->has('orchards')
            ->has('seasons')
            ->has('members', 1));
})->with(['care', 'observations', 'harvests', 'sales']);

test('observations on the same day are ordered by id descending and carry issue status', function () {
    $tree = Tree::factory()->create();
    $open = TreeIssue::factory()->for($tree)->create(['resolved_on' => null]);
    $closed = TreeIssue::factory()->for($tree)->resolved()->create();

    $none = Observation::factory()->for($tree)->create(['observed_on' => '2026-05-01']);
    $withOpen = Observation::factory()->for($tree)->create(['observed_on' => '2026-05-01', 'tree_issue_id' => $open->id]);
    $withClosed = Observation::factory()->for($tree)->create(['observed_on' => '2026-05-01', 'tree_issue_id' => $closed->id]);

    $this->get(route('records.index', 'observations'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('records.data.0.id', $withClosed->id)
            ->where('records.data.0.issueStatus', 'resolved')
            ->where('records.data.1.id', $withOpen->id)
            ->where('records.data.1.issueStatus', 'open')
            ->where('records.data.2.id', $none->id)
            ->where('records.data.2.issueStatus', null));
});

test('ai written rows are flagged with the member who used ai', function () {
    $member = User::factory()->create();
    $record = CareRecord::factory()->create();
    stampProvenance($record, $member, 'mcp');
    $record->newQuery()->whereKey($record->id)->update(['updated_via' => 'mcp', 'updated_by' => $member->id]);

    $this->get(route('records.index', 'care'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('records.data.0.fromAi', true)
            ->where('records.data.0.aiBy', $member->name)
            ->where('records.data.0.createdBy', $member->name));
});

test('records filter by orchard, dates, who and via', function () {
    $member = User::factory()->create();
    $orchardA = Orchard::factory()->create();
    $orchardB = Orchard::factory()->create();

    $a = CareRecord::factory()->for($orchardA)->create(['performed_on' => '2026-10-01']);
    stampProvenance($a, $member, 'mcp');
    $b = CareRecord::factory()->for($orchardB)->create(['performed_on' => '2026-09-01']);
    stampProvenance($b, $this->owner, 'app');

    $count = fn (array $query) => $this->get(route('records.index', ['type' => 'care', ...$query]))
        ->assertOk()
        ->viewData('page')['props']['records']['data'];

    expect($count(['orchard_id' => $orchardA->id]))->toHaveCount(1)
        ->and($count(['via' => 'mcp']))->toHaveCount(1)
        ->and($count(['created_by' => $this->owner->id]))->toHaveCount(1)
        ->and($count(['from' => '2026-09-15']))->toHaveCount(1)
        ->and($count(['to' => '2026-09-15']))->toHaveCount(1)
        ->and($count([]))->toHaveCount(2);
});

test('records filter by orchard through the tree', function () {
    $orchardA = Orchard::factory()->create();
    Harvest::factory()->for(Tree::factory()->for($orchardA))->create();
    Harvest::factory()->create();

    $this->get(route('records.index', ['type' => 'harvests', 'orchard_id' => $orchardA->id]))
        ->assertInertia(fn (Assert $page) => $page->has('records.data', 1)->where('filters.orchard_id', $orchardA->id));
});

test('filters are validated', function (array $query, string $error) {
    $this->get(route('records.index', ['type' => 'care', ...$query]))->assertSessionHasErrors($error);
})->with([
    'unknown orchard' => [['orchard_id' => 999], 'orchard_id'],
    'unknown season' => [['season_id' => 999], 'season_id'],
    'unknown member' => [['created_by' => 999], 'created_by'],
    'bad via' => [['via' => 'fax'], 'via'],
    'to before from' => [['from' => '2026-02-01', 'to' => '2026-01-01'], 'to'],
]);

test('season filter keeps the season orchard and window', function () {
    $orchardA = Orchard::factory()->create();
    $orchardB = Orchard::factory()->create();
    $season = Season::factory()->for($orchardA)->create(['started_on' => '2026-01-01', 'ended_on' => '2026-06-30']);

    CareRecord::factory()->for($orchardA)->create(['performed_on' => '2026-03-01']);
    CareRecord::factory()->for($orchardA)->create(['performed_on' => '2026-08-01']);
    CareRecord::factory()->for($orchardB)->create(['performed_on' => '2026-03-01']);

    $this->get(route('records.index', ['type' => 'care', 'season_id' => $season->id]))
        ->assertInertia(fn (Assert $page) => $page->has('records.data', 1));
});

test('expenses filter by orchard through their season and drop season-less ones', function () {
    $orchardA = Orchard::factory()->create();
    $seasonA = Season::factory()->for($orchardA)->create();
    $seasonB = Season::factory()->create();
    Expense::factory()->create(['season_id' => $seasonA->id, 'amount' => 100]);
    Expense::factory()->create(['season_id' => $seasonB->id, 'amount' => 200]);
    Expense::factory()->create(['season_id' => null, 'amount' => 400]);

    $this->get(route('expenses.index', ['orchard_id' => $orchardA->id]))
        ->assertInertia(fn (Assert $page) => $page->has('expenses', 1)->where('total', 100));

    $this->get(route('expenses.index', ['season_id' => $seasonB->id]))
        ->assertInertia(fn (Assert $page) => $page->has('expenses', 1)->where('total', 200));

    $this->get(route('expenses.index'))
        ->assertInertia(fn (Assert $page) => $page->has('expenses', 3));
});

test('unknown record type is not found', function () {
    $this->get('/records/bogus')->assertNotFound();
});

test('records root goes to care', function () {
    $this->get('/records')->assertRedirect(route('records.index', 'care'));
});

test('old expenses link keeps its query', function () {
    $this->get('/expenses?from=2026-01-01')
        ->assertRedirect(route('expenses.index', ['from' => '2026-01-01']));
});

test('records index paginates at 50', function () {
    Harvest::factory()->count(51)->create();

    $this->get(route('records.index', 'harvests'))
        ->assertInertia(fn (Assert $page) => $page->has('records.data', 50)->where('records.last_page', 2));
});

test('expenses carry the filter options and who recorded each row', function () {
    $member = User::factory()->create();
    $mcp = Expense::factory()->create();
    stampProvenance($mcp, $member, 'mcp');
    $mcp->newQuery()->whereKey($mcp->id)->update(['updated_via' => 'mcp', 'updated_by' => $member->id]);
    $app = Expense::factory()->create();
    stampProvenance($app, $this->owner, 'app');

    $this->get(route('expenses.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->has('orchards')
            ->has('members', 2)
            ->has('seasons')
            ->where('expenses', fn ($rows) => collect($rows)->firstWhere('id', $mcp->id)['fromAi'] === true
                && collect($rows)->firstWhere('id', $mcp->id)['aiBy'] === $member->name
                && collect($rows)->firstWhere('id', $mcp->id)['createdBy'] === $member->name
                && collect($rows)->firstWhere('id', $app->id)['fromAi'] === false));

    $this->get(route('expenses.index', ['via' => 'mcp']))
        ->assertInertia(fn (Assert $page) => $page->has('expenses', 1)->where('filters.via', 'mcp'));
});

test('care records carry the trees and open problems the edit form needs', function () {
    $orchard = Orchard::factory()->create();
    $tree = Tree::factory()->for($orchard)->create(['code' => 'A1']);
    Tree::factory()->for($orchard)->create(['code' => 'A2', 'removed_on' => '2026-01-01']);
    $issue = TreeIssue::factory()->for($tree)->create(['resolved_on' => null]);
    CareRecord::factory()->for($orchard)->create();

    $this->get(route('records.index', 'care'))
        ->assertInertia(fn (Assert $page) => $page
            ->has("orchardTrees.{$orchard->id}", 1)
            ->where("orchardTrees.{$orchard->id}.0.code", 'A1')
            ->has('openIssues', 1)
            ->where('openIssues.0.id', $issue->id));

    $this->get(route('records.index', 'harvests'))
        ->assertInertia(fn (Assert $page) => $page->missing('orchardTrees')->missing('openIssues'));
});

test('observation rows carry the finding kind', function () {
    Observation::factory()->create();

    $this->get(route('records.index', 'observations'))
        ->assertInertia(fn (Assert $page) => $page->has('records.data.0.finding.kind'));
});
