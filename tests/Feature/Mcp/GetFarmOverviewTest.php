<?php

use App\Mcp\Servers\FarmServer;
use App\Mcp\Tools\GetFarmOverview;
use App\Models\Finding;
use App\Models\Orchard;
use App\Models\Season;
use App\Models\Tree;
use App\Models\TreeIssue;
use App\Models\User;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    Date::setTestNow('2026-10-21');

    $this->user = User::factory()->create();
    $this->orchard = Orchard::factory()->create(['name' => 'สวนหน้าบ้าน']);
    $this->season = Season::factory()->for($this->orchard)->create(['name' => 'ฤดู 2569', 'started_on' => '2026-01-01']);
    $this->tree = Tree::factory()->for($this->orchard)->create(['code' => 'A-001']);
    $this->redMite = Finding::where('name', 'ไรแดง')->firstOrFail();
});

test('overview lists orchards due issues and findings', function () {
    TreeIssue::factory()->for($this->tree)->create([
        'finding_id' => $this->redMite->id,
        'opened_on' => '2026-10-14',
        'follow_up_on' => '2026-10-21',
    ]);

    FarmServer::actingAs($this->user)
        ->tool(GetFarmOverview::class)
        ->assertOk()
        ->assertSee(['สวนหน้าบ้าน', 'ไรแดง', 'A-001', 'ฤดู 2569']);
});

test('overview payload has orchards with season and active tree count', function () {
    Tree::factory()->for($this->orchard)->create(['code' => 'A-002']);
    Tree::factory()->for($this->orchard)->removed()->create(['code' => 'A-003']);
    $other = Orchard::factory()->create(['name' => 'สวนหลังบ้าน']);

    $payload = FarmServer::actingAs($this->user)->tool(GetFarmOverview::class)->assertOk()->payload();

    expect($payload['today'])->toBe('2026-10-21')
        ->and($payload['orchards'])->toBe([
            ['id' => $this->orchard->id, 'name' => 'สวนหน้าบ้าน', 'current_season' => 'ฤดู 2569', 'active_tree_count' => 2, 'seasons' => [
                ['id' => $this->season->id, 'orchard_id' => $this->orchard->id, 'name' => 'ฤดู 2569', 'started_on' => '2026-01-01', 'ended_on' => null, 'notes' => null],
            ]],
            ['id' => $other->id, 'name' => 'สวนหลังบ้าน', 'current_season' => null, 'active_tree_count' => 0, 'seasons' => []],
        ])
        ->and(collect($payload['findings'])->firstWhere('name', 'ไรแดง'))
        ->toBe(['id' => $this->redMite->id, 'name' => 'ไรแดง', 'kind' => 'problem']);
});

test('overview separates due issues from open issues and carries the orchard id', function () {
    $due = TreeIssue::factory()->for($this->tree)->create([
        'finding_id' => $this->redMite->id,
        'opened_on' => '2026-10-14',
        'follow_up_on' => '2026-10-21',
    ]);
    $later = TreeIssue::factory()->for(Tree::factory()->for($this->orchard)->create(['code' => 'A-002']))->create([
        'finding_id' => $this->redMite->id,
        'opened_on' => '2026-10-20',
        'follow_up_on' => '2026-10-27',
    ]);

    $payload = FarmServer::actingAs($this->user)->tool(GetFarmOverview::class)->assertOk()->payload();

    expect(collect($payload['due_issues'])->pluck('id')->all())->toBe([$due->id])
        ->and(collect($payload['open_issues'])->pluck('id')->all())->toBe([$due->id, $later->id])
        ->and($payload['due_issues'][0])->toMatchArray([
            'treeCode' => 'A-001',
            'finding' => 'ไรแดง',
            'isDue' => true,
            'orchard_id' => $this->orchard->id,
        ]);
});

test('overview omits resolved issues and issues on removed trees', function () {
    TreeIssue::factory()->for($this->tree)->resolved()->create();
    $removed = Tree::factory()->for($this->orchard)->removed()->create(['code' => 'A-009']);
    TreeIssue::factory()->for($removed)->create(['follow_up_on' => '2026-10-01']);

    $payload = FarmServer::actingAs($this->user)->tool(GetFarmOverview::class)->assertOk()->payload();

    expect($payload['due_issues'])->toBe([])
        ->and($payload['open_issues'])->toBe([]);
});

test('overview query count is constant regardless of the number of orchards', function () {
    TreeIssue::factory()->for($this->tree)->create(['follow_up_on' => '2026-10-01']);

    $queryCount = function (): int {
        DB::flushQueryLog();
        DB::enableQueryLog();
        FarmServer::actingAs($this->user)->tool(GetFarmOverview::class)->assertOk();

        return count(DB::getQueryLog());
    };

    $before = $queryCount();

    Orchard::factory()->count(3)->create()->each(function (Orchard $orchard): void {
        Season::factory()->for($orchard)->create();
        TreeIssue::factory()->for(Tree::factory()->for($orchard)->create())->create(['follow_up_on' => '2026-10-01']);
    });

    expect($queryCount())->toBe($before);
});
