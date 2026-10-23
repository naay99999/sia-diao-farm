<?php

use App\Enums\FindingKind;
use App\Enums\FollowUpOutcome;
use App\Enums\Severity;
use App\Models\CareRecord;
use App\Models\Finding;
use App\Models\Observation;
use App\Models\ObservationPhoto;
use App\Models\Orchard;
use App\Models\Season;
use App\Models\Tree;
use App\Models\TreeIssue;
use App\Models\User;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia;

beforeEach(function () {
    Date::setTestNow('2026-10-21');
    Storage::fake('photos');

    $this->user = User::factory()->create();
    $this->orchard = Orchard::factory()->create();
    $this->tree = Tree::factory()->for($this->orchard)->create(['code' => 'A-001']);
    $this->redMite = Finding::where('name', 'ไรแดง')->firstOrFail();
    $this->flowering = Finding::where('name', 'ออกดอก')->firstOrFail();
});

test('tree page exposes open issues latest stage and findings', function () {
    Season::factory()->for($this->orchard)->create(['started_on' => '2026-01-01']);

    $open = TreeIssue::factory()->for($this->tree)->create([
        'finding_id' => $this->redMite->id,
        'opened_on' => '2026-10-14',
        'follow_up_on' => '2026-10-21',
    ]);
    TreeIssue::factory()->for($this->tree)->resolved()->create();
    TreeIssue::factory()->create();

    Observation::factory()->for($this->tree)->create([
        'finding_id' => $this->flowering->id,
        'observed_on' => '2026-10-01',
    ]);
    $latestStage = Finding::where('name', 'ติดผล')->firstOrFail();
    Observation::factory()->for($this->tree)->create([
        'finding_id' => $latestStage->id,
        'observed_on' => '2026-10-10',
    ]);
    Observation::factory()->for($this->tree)->create([
        'finding_id' => $this->redMite->id,
        'observed_on' => '2026-10-20',
    ]);

    $this->actingAs($this->user)
        ->get(route('trees.show', $this->tree))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('farm/trees/show')
            ->has('openIssues', 1)
            ->where('openIssues.0', [
                'id' => $open->id,
                'treeId' => $this->tree->id,
                'treeCode' => 'A-001',
                'finding' => 'ไรแดง',
                'openedOn' => '2026-10-14',
                'followUpOn' => '2026-10-21',
                'isDue' => true,
            ])
            ->where('latestStage', [
                'finding' => 'ติดผล',
                'observedOn' => '2026-10-10',
                'inCurrentSeason' => true,
            ])
            ->has('findings', Finding::count())
            ->where('findings.0', fn ($finding) => collect($finding)->keys()->sort()->values()->all() === ['id', 'kind', 'name']));
});

test('latest stage outside current season is flagged', function () {
    Season::factory()->for($this->orchard)->create(['started_on' => '2026-01-01']);

    Observation::factory()->for($this->tree)->create([
        'finding_id' => $this->flowering->id,
        'observed_on' => '2025-12-20',
    ]);

    $this->actingAs($this->user)
        ->get(route('trees.show', $this->tree))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('latestStage.finding', 'ออกดอก')
            ->where('latestStage.inCurrentSeason', false));

    $otherTree = Tree::factory()->create();
    Observation::factory()->for($otherTree)->create(['finding_id' => $this->flowering->id, 'observed_on' => '2026-10-01']);

    $this->actingAs($this->user)
        ->get(route('trees.show', $otherTree))
        ->assertInertia(fn (AssertableInertia $page) => $page->where('latestStage.inCurrentSeason', false));

    $emptyTree = Tree::factory()->for($this->orchard)->create();

    $this->actingAs($this->user)
        ->get(route('trees.show', $emptyTree))
        ->assertInertia(fn (AssertableInertia $page) => $page->where('latestStage', null)->where('openIssues', []));
});

test('timeline includes observations with ai flag and photo urls', function () {
    $issue = TreeIssue::factory()->for($this->tree)->create(['finding_id' => $this->redMite->id]);

    $problem = Observation::factory()->for($this->tree)->severity(Severity::High)->create([
        'finding_id' => $this->redMite->id,
        'tree_issue_id' => $issue->id,
        'observed_on' => '2026-10-20',
        'notes' => 'ใบม้วน',
    ]);
    ObservationPhoto::factory()->for($problem)->create(['path' => 'observations/1/a.jpg']);
    ObservationPhoto::factory()->for($problem)->create(['path' => 'observations/1/b.jpg']);

    Context::add('record_via', 'mcp');
    $followUp = Observation::factory()->for($this->tree)->followUp(FollowUpOutcome::Resolved)->create([
        'finding_id' => $this->redMite->id,
        'tree_issue_id' => $issue->id,
        'observed_on' => '2026-10-21',
    ]);
    Context::forget('record_via');

    $stage = Observation::factory()->for($this->tree)->create([
        'finding_id' => $this->flowering->id,
        'observed_on' => '2026-10-19',
    ]);

    $this->actingAs($this->user)
        ->get(route('trees.show', $this->tree))
        ->assertInertia(function (AssertableInertia $page) use ($problem, $followUp, $stage) {
            $page->has('timeline', 3)
                ->where('timeline.0.kind', 'observation')
                ->where('timeline.0.id', $followUp->id)
                ->where('timeline.0.title', 'ดูซ้ำ: ไรแดง — หายแล้ว')
                ->where('timeline.0.detail', null)
                ->where('timeline.0.fromAi', true)
                ->where('timeline.0.photos', [])
                ->where('timeline.1.id', $problem->id)
                ->where('timeline.1.title', 'ไรแดง')
                ->where('timeline.1.detail', 'มาก · ใบม้วน')
                ->where('timeline.1.fromAi', false)
                ->has('timeline.1.photos', 2)
                ->where('timeline.2.id', $stage->id)
                ->where('timeline.2.title', 'ออกดอก')
                ->where('timeline.2.fromAi', false)
                ->where('timeline.1.photos.0', fn (string $url) => str_contains($url, 'observations/1/a.jpg') && str_contains($url, 'expiration='.Date::now()->addMinutes(30)->getTimestamp()));
        });
});

test('timeline marks care records and harvests as ai when written through mcp', function () {
    $care = CareRecord::factory()->for($this->orchard)->create(['performed_on' => '2026-10-18']);
    $this->tree->careRecords()->attach($care);
    $care->issues()->attach(TreeIssue::factory()->for($this->tree)->create());

    $this->actingAs($this->user);
    Context::add('record_via', 'mcp');
    $harvest = $this->tree->harvests()->create(['harvested_on' => '2026-10-19', 'weight_kg' => 12]);
    Context::forget('record_via');

    $this->actingAs($this->user)
        ->get(route('trees.show', $this->tree))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('timeline.0.kind', 'harvest')
            ->where('timeline.0.id', $harvest->id)
            ->where('timeline.0.fromAi', true)
            ->where('timeline.0.aiBy', $this->user->name)
            ->where('timeline.1.kind', 'care')
            ->where('timeline.1.fromAi', false)
            ->where('timeline.1.aiBy', null)
            ->where('timeline.1.record.issue_ids', $care->issues->pluck('id')->all()));
});

test('orchard page lists due issues and excludes removed trees', function () {
    $dueToday = TreeIssue::factory()->for($this->tree)->create([
        'finding_id' => $this->redMite->id,
        'follow_up_on' => '2026-10-21',
    ]);
    $overdueTree = Tree::factory()->for($this->orchard)->create(['code' => 'A-002']);
    $overdue = TreeIssue::factory()->for($overdueTree)->create(['follow_up_on' => '2026-10-15']);
    $upcoming = TreeIssue::factory()->for($this->tree)->create(['follow_up_on' => '2026-10-25']);
    TreeIssue::factory()->for($this->tree)->resolved()->create(['follow_up_on' => '2026-10-10']);

    $removedTree = Tree::factory()->for($this->orchard)->create(['removed_on' => '2026-10-01']);
    TreeIssue::factory()->for($removedTree)->create(['follow_up_on' => '2026-10-10']);
    TreeIssue::factory()->create(['follow_up_on' => '2026-10-10']);

    $this->actingAs($this->user)
        ->get(route('orchards.show', $this->orchard))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->has('dueIssues', 2)
            ->where('dueIssues.0.id', $overdue->id)
            ->where('dueIssues.0.treeCode', 'A-002')
            ->where('dueIssues.0.isDue', true)
            ->where('dueIssues.1.id', $dueToday->id)
            ->has('openIssues', 3)
            ->where('openIssues', fn ($issues) => collect($issues)->pluck('id')->sort()->values()->all()
                === collect([$overdue->id, $dueToday->id, $upcoming->id])->sort()->values()->all())
            ->where('openIssues', fn ($issues) => collect($issues)->firstWhere('id', $upcoming->id)['isDue'] === false));
});

test('orchard page recent care records carry linked issue ids', function () {
    $care = CareRecord::factory()->for($this->orchard)->create();
    $issue = TreeIssue::factory()->for($this->tree)->create();
    $care->issues()->attach($issue);

    $this->actingAs($this->user)
        ->get(route('orchards.show', $this->orchard))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('recentCareRecords.0.issue_ids', [$issue->id])
            ->missing('recentCareRecords.0.issues'));
});

test('care record create page exposes open issues', function () {
    $issue = TreeIssue::factory()->for($this->tree)->create(['finding_id' => $this->redMite->id]);
    TreeIssue::factory()->for($this->tree)->resolved()->create();
    TreeIssue::factory()->for(Tree::factory()->for($this->orchard)->create(['removed_on' => '2026-10-01']))->create();
    TreeIssue::factory()->create();

    $this->actingAs($this->user)
        ->get(route('orchards.care-records.create', $this->orchard))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('farm/care-records/create')
            ->has('openIssues', 1)
            ->where('openIssues.0.id', $issue->id)
            ->where('openIssues.0.treeId', $this->tree->id)
            ->where('openIssues.0.finding', 'ไรแดง'));
});

test('finding kind is exposed as its backing value', function () {
    $this->actingAs($this->user)
        ->get(route('trees.show', $this->tree))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('findings', fn ($findings) => collect($findings)->contains(
                fn ($finding) => $finding['name'] === 'ไรแดง' && $finding['kind'] === FindingKind::Problem->value)));
});
