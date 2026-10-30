<?php

use App\Enums\CareType;
use App\Mcp\Servers\FarmServer;
use App\Mcp\Support\TreeLookup;
use App\Mcp\Tools\GetTreatmentOutcomes;
use App\Mcp\Tools\GetTree;
use App\Mcp\Tools\ListRecords;
use App\Models\CareRecord;
use App\Models\Expense;
use App\Models\Finding;
use App\Models\Harvest;
use App\Models\Observation;
use App\Models\Orchard;
use App\Models\Sale;
use App\Models\Season;
use App\Models\Tree;
use App\Models\TreeIssue;
use App\Models\User;
use Illuminate\Support\Facades\Date;
use Illuminate\Validation\ValidationException;

beforeEach(function () {
    Date::setTestNow('2026-10-21');

    $this->user = User::factory()->create();
    $this->orchard = Orchard::factory()->create(['name' => 'สวนหน้าบ้าน']);
    $this->season = Season::factory()->for($this->orchard)->create(['name' => 'ฤดู 2569', 'started_on' => '2026-01-01']);
    $this->tree = Tree::factory()->for($this->orchard)->create(['code' => 'A-001']);
    $this->redMite = Finding::where('name', 'ไรแดง')->firstOrFail();
});

test('ambiguous tree code without orchard id is rejected', function () {
    Orchard::factory()->create(['name' => 'สวนหลังบ้าน']);

    FarmServer::actingAs($this->user)
        ->tool(GetTree::class, ['code' => 'A-001'])
        ->assertHasErrors(['Multiple orchards exist; pass orchard_id from get_farm_overview.']);
});

test('single orchard resolves code without orchard id', function () {
    $payload = FarmServer::actingAs($this->user)
        ->tool(GetTree::class, ['code' => 'A-001'])
        ->assertOk()
        ->payload();

    expect($payload['tree']['id'])->toBe($this->tree->id)
        ->and($payload['tree']['code'])->toBe('A-001')
        ->and($payload['tree']['orchard_id'])->toBe($this->orchard->id);
});

test('tree lookup rejects unknown orchard and lists every missing code', function () {
    Tree::factory()->for($this->orchard)->removed()->create(['code' => 'A-009']);

    $resolved = TreeLookup::resolve($this->orchard->id, ['A-001', 'A-009']);

    expect($resolved->pluck('code')->all())->toBe(['A-001', 'A-009'])
        ->and($resolved->last()->isRemoved())->toBeTrue();

    expect(fn () => TreeLookup::resolve(null, ['A-001', 'Z-1', 'Z-2']))
        ->toThrow(fn (ValidationException $exception) => expect($exception->errors()['tree_codes'][0])
            ->toContain('Z-1')->toContain('Z-2')->not->toContain('A-001'));

    expect(fn () => TreeLookup::resolve(999999, ['A-001']))
        ->toThrow(fn (ValidationException $exception) => expect($exception->errors())->toHaveKey('orchard_id'));
});

test('tree lookup only matches codes inside the chosen orchard', function () {
    $other = Orchard::factory()->create();
    Tree::factory()->for($other)->create(['code' => 'B-001']);

    FarmServer::actingAs($this->user)
        ->tool(GetTree::class, ['orchard_id' => $this->orchard->id, 'code' => 'B-001'])
        ->assertHasErrors(['B-001']);
});

test('get tree returns open issues and timeline', function () {
    $issue = TreeIssue::factory()->for($this->tree)->create([
        'finding_id' => $this->redMite->id,
        'opened_on' => '2026-10-14',
        'follow_up_on' => '2026-10-21',
    ]);
    TreeIssue::factory()->for($this->tree)->resolved()->create();
    Observation::factory()->for($this->tree)->create([
        'finding_id' => $this->redMite->id,
        'tree_issue_id' => $issue->id,
        'observed_on' => '2026-10-14',
    ]);
    Observation::factory()->for($this->tree)->create([
        'finding_id' => Finding::where('name', 'ติดผล')->firstOrFail()->id,
        'observed_on' => '2026-09-01',
    ]);
    $care = CareRecord::factory()->for($this->orchard)->create(['type' => CareType::Pesticide, 'product' => 'อะบาเมกติน', 'performed_on' => '2026-10-15']);
    $care->trees()->attach($this->tree);
    Harvest::factory()->for($this->tree)->create(['harvested_on' => '2026-06-01', 'weight_kg' => 12.5, 'fruit_count' => 3]);
    Harvest::factory()->for($this->tree)->create(['harvested_on' => '2026-06-02', 'weight_kg' => 99])->delete();

    $payload = FarmServer::actingAs($this->user)
        ->tool(GetTree::class, ['code' => 'A-001'])
        ->assertOk()
        ->payload();

    expect(collect($payload['open_issues'])->pluck('id')->all())->toBe([$issue->id])
        ->and($payload['open_issues'][0])->toMatchArray(['treeCode' => 'A-001', 'finding' => 'ไรแดง', 'isDue' => true])
        ->and($payload['latest_stage'])->toMatchArray(['finding' => 'ติดผล', 'observedOn' => '2026-09-01'])
        ->and(collect($payload['timeline'])->pluck('kind')->unique()->sort()->values()->all())->toBe(['care', 'harvest', 'observation'])
        ->and($payload['timeline'][0]['date'])->toBe('2026-10-15')
        ->and($payload['season_yields'][0])->toMatchArray(['seasonName' => 'ฤดู 2569', 'weightKg' => 12.5, 'fruitCount' => 3]);
});

test('get tree still returns a removed tree', function () {
    Tree::factory()->for($this->orchard)->removed()->create(['code' => 'A-009']);

    $payload = FarmServer::actingAs($this->user)
        ->tool(GetTree::class, ['code' => 'A-009'])
        ->assertOk()
        ->payload();

    expect($payload['tree']['removed_on'])->not->toBeNull();
});

test('list records filters by type date and excludes deleted by default', function () {
    $old = Harvest::factory()->for($this->tree)->create(['harvested_on' => '2026-05-01']);
    $mid = Harvest::factory()->for($this->tree)->create(['harvested_on' => '2026-06-10']);
    $new = Harvest::factory()->for($this->tree)->create(['harvested_on' => '2026-07-15']);
    $deleted = Harvest::factory()->for($this->tree)->create(['harvested_on' => '2026-06-20']);
    $deleted->delete();
    Sale::factory()->for($this->orchard)->create();

    $all = FarmServer::actingAs($this->user)
        ->tool(ListRecords::class, ['type' => 'harvest'])
        ->assertOk()
        ->payload();

    expect(collect($all['records'])->pluck('id')->all())->toBe([$new->id, $mid->id, $old->id])
        ->and($all['records'][0])->toMatchArray(['tree_code' => 'A-001', 'harvested_on' => '2026-07-15'])
        ->and($all['records'][0])->not->toHaveKey('deleted_at');

    $window = FarmServer::actingAs($this->user)
        ->tool(ListRecords::class, ['type' => 'harvest', 'from' => '2026-06-01', 'to' => '2026-06-30', 'include_deleted' => true])
        ->assertOk()
        ->payload();

    expect(collect($window['records'])->pluck('id')->all())->toBe([$deleted->id, $mid->id])
        ->and($window['records'][0]['deleted_at'])->not->toBeNull()
        ->and($window['records'][1]['deleted_at'])->toBeNull();
});

test('list records supports every record type', function () {
    $care = CareRecord::factory()->for($this->orchard)->create(['product' => 'ปุ๋ยสูตร 15-15-15']);
    $care->trees()->attach($this->tree);
    Sale::factory()->for($this->orchard)->create(['buyer_name' => 'พ่อค้า']);
    Expense::factory()->create(['season_id' => $this->season->id]);
    Observation::factory()->for($this->tree)->create();
    TreeIssue::factory()->for($this->tree)->create(['finding_id' => $this->redMite->id]);

    foreach (['care', 'harvest', 'sale', 'expense', 'observation', 'issue'] as $type) {
        $payload = FarmServer::actingAs($this->user)
            ->tool(ListRecords::class, ['type' => $type])
            ->assertOk()
            ->payload();

        expect($payload['type'])->toBe($type)
            ->and(count($payload['records']))->toBe($type === 'harvest' ? 0 : 1);

        if ($type !== 'harvest') {
            expect($payload['records'][0])->toHaveKey('id');
        }
    }
});

test('list records filters by tree code and orchard', function () {
    $other = Orchard::factory()->create();
    $otherTree = Tree::factory()->for($other)->create(['code' => 'A-001']);
    $mine = Harvest::factory()->for($this->tree)->create();
    Harvest::factory()->for($otherTree)->create();

    $byTree = FarmServer::actingAs($this->user)
        ->tool(ListRecords::class, ['type' => 'harvest', 'orchard_id' => $this->orchard->id, 'tree_code' => 'A-001'])
        ->assertOk()
        ->payload();
    $byOrchard = FarmServer::actingAs($this->user)
        ->tool(ListRecords::class, ['type' => 'harvest', 'orchard_id' => $this->orchard->id])
        ->assertOk()
        ->payload();

    expect(collect($byTree['records'])->pluck('id')->all())->toBe([$mine->id])
        ->and(collect($byOrchard['records'])->pluck('id')->all())->toBe([$mine->id]);

    FarmServer::actingAs($this->user)
        ->tool(ListRecords::class, ['type' => 'harvest', 'tree_code' => 'A-001'])
        ->assertHasErrors(['Multiple orchards exist; pass orchard_id from get_farm_overview.']);
});

test('list records care filter matches any tree of the record and respects limit', function () {
    $second = Tree::factory()->for($this->orchard)->create(['code' => 'A-002']);
    $shared = CareRecord::factory()->for($this->orchard)->create(['performed_on' => '2026-10-01']);
    $shared->trees()->attach([$this->tree->id, $second->id]);
    $onlySecond = CareRecord::factory()->for($this->orchard)->create(['performed_on' => '2026-10-02']);
    $onlySecond->trees()->attach($second);

    $payload = FarmServer::actingAs($this->user)
        ->tool(ListRecords::class, ['type' => 'care', 'tree_code' => 'A-001'])
        ->assertOk()
        ->payload();

    expect(collect($payload['records'])->pluck('id')->all())->toBe([$shared->id])
        ->and($payload['records'][0]['tree_codes'])->toBe(['A-001', 'A-002']);

    $limited = FarmServer::actingAs($this->user)
        ->tool(ListRecords::class, ['type' => 'care', 'limit' => 1])
        ->assertOk()
        ->payload();

    expect($limited['records'])->toHaveCount(1)
        ->and($limited['has_more'])->toBeTrue()
        ->and($limited['records'][0]['id'])->toBe($onlySecond->id);
});

test('list records rejects bad arguments with a fixable message', function () {
    FarmServer::actingAs($this->user)
        ->tool(ListRecords::class, ['type' => 'weather'])
        ->assertHasErrors(['type must be one of']);

    FarmServer::actingAs($this->user)
        ->tool(ListRecords::class, ['type' => 'sale', 'tree_code' => 'A-001'])
        ->assertHasErrors(['tree_code only applies']);

    FarmServer::actingAs($this->user)
        ->tool(ListRecords::class, ['type' => 'care', 'limit' => 500])
        ->assertHasErrors(['limit']);
});

test('treatment outcomes count resolved issues per product', function () {
    $product = 'อะบาเมกติน';
    $opened = ['2026-09-01', '2026-09-01', '2026-09-20'];
    $resolved = ['2026-09-08', '2026-09-12', null];

    foreach ($opened as $index => $openedOn) {
        $issue = TreeIssue::factory()
            ->for(Tree::factory()->for($this->orchard)->create(['code' => 'T-'.$index]))
            ->create([
                'finding_id' => $this->redMite->id,
                'opened_on' => $openedOn,
                'follow_up_on' => '2026-10-30',
                'resolved_on' => $resolved[$index],
            ]);
        $care = CareRecord::factory()->for($this->orchard)->create(['type' => CareType::Pesticide, 'product' => $product]);
        $issue->careRecords()->attach($care);
    }

    $payload = FarmServer::actingAs($this->user)
        ->tool(GetTreatmentOutcomes::class, ['finding' => 'ไรแดง'])
        ->assertOk()
        ->payload();

    expect($payload['outcomes'])->toEqual([[
        'finding' => 'ไรแดง',
        'product' => $product,
        'issues_treated' => 3,
        'resolved' => 2,
        'avg_days_to_resolve' => 9.0,
    ]]);
});

test('treatment outcomes ignore deleted rows, report null average and filter by finding', function () {
    $issue = TreeIssue::factory()->for($this->tree)->create(['finding_id' => $this->redMite->id]);
    $kept = CareRecord::factory()->for($this->orchard)->create(['product' => 'ยา ก']);
    $gone = CareRecord::factory()->for($this->orchard)->create(['product' => 'ยา ข']);
    $issue->careRecords()->attach([$kept->id, $gone->id]);
    $gone->delete();

    $deletedIssue = TreeIssue::factory()->for($this->tree)->create(['finding_id' => $this->redMite->id]);
    $deletedIssue->careRecords()->attach($kept);
    $deletedIssue->delete();

    $otherFinding = Finding::factory()->create(['name' => 'โรครากเน่า']);
    $other = TreeIssue::factory()->for($this->tree)->create(['finding_id' => $otherFinding->id]);
    $other->careRecords()->attach($kept);

    $all = FarmServer::actingAs($this->user)->tool(GetTreatmentOutcomes::class)->assertOk()->payload();
    $filtered = FarmServer::actingAs($this->user)->tool(GetTreatmentOutcomes::class, ['finding' => 'ไรแดง'])->assertOk()->payload();

    expect($all['outcomes'])->toHaveCount(2)
        ->and($filtered['outcomes'])->toBe([[
            'finding' => 'ไรแดง',
            'product' => 'ยา ก',
            'issues_treated' => 1,
            'resolved' => 0,
            'avg_days_to_resolve' => null,
        ]]);
});

test('treatment outcomes count an issue once per product and skip blank products', function () {
    $issue = TreeIssue::factory()->for($this->tree)->create([
        'finding_id' => $this->redMite->id,
        'opened_on' => '2026-09-01',
        'resolved_on' => '2026-09-05',
    ]);
    $issue->careRecords()->attach([
        CareRecord::factory()->for($this->orchard)->create(['product' => 'ยา ก'])->id,
        CareRecord::factory()->for($this->orchard)->create(['product' => ' ยา ก '])->id,
        CareRecord::factory()->for($this->orchard)->create(['product' => null])->id,
        CareRecord::factory()->for($this->orchard)->create(['product' => '  '])->id,
    ]);

    $payload = FarmServer::actingAs($this->user)->tool(GetTreatmentOutcomes::class)->assertOk()->payload();

    expect($payload['outcomes'])->toEqual([[
        'finding' => 'ไรแดง',
        'product' => 'ยา ก',
        'issues_treated' => 1,
        'resolved' => 1,
        'avg_days_to_resolve' => 4.0,
    ]]);
});
