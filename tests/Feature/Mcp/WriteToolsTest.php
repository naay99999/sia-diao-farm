<?php

use App\Enums\CareType;
use App\Enums\ExpenseCategory;
use App\Enums\FollowUpOutcome;
use App\Mcp\Servers\FarmServer;
use App\Mcp\Tools\RecordFollowUp;
use App\Mcp\Tools\RecordObservation;
use App\Mcp\Tools\SaveCareRecord;
use App\Mcp\Tools\SaveExpense;
use App\Mcp\Tools\SaveHarvest;
use App\Mcp\Tools\SaveSale;
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
use App\Support\Provenance;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Facades\Date;

beforeEach(function () {
    Date::setTestNow('2026-10-21');

    $this->user = User::factory()->create();
    $this->orchard = Orchard::factory()->create(['name' => 'สวนหน้าบ้าน']);
    $this->season = Season::factory()->for($this->orchard)->create(['name' => 'ฤดู 2569', 'started_on' => '2026-01-01']);
    $this->treeA1 = Tree::factory()->for($this->orchard)->create(['code' => 'A-001']);
    $this->treeA2 = Tree::factory()->for($this->orchard)->create(['code' => 'A-002']);
    $this->redMite = Finding::where('name', 'ไรแดง')->firstOrFail();
});

test('every write tool description starts with the confirmation rule', function (string $tool) {
    expect(app($tool)->description())->toStartWith('Only call after the user confirmed a summary in chat.');
})->with([
    SaveCareRecord::class,
    RecordObservation::class,
    RecordFollowUp::class,
    SaveHarvest::class,
    SaveSale::class,
    SaveExpense::class,
]);

test('write tools are named in snake case', function () {
    expect(app(SaveCareRecord::class)->name())->toBe('save_care_record')
        ->and(app(RecordObservation::class)->name())->toBe('record_observation')
        ->and(app(RecordFollowUp::class)->name())->toBe('record_follow_up')
        ->and(app(SaveHarvest::class)->name())->toBe('save_harvest')
        ->and(app(SaveSale::class)->name())->toBe('save_sale')
        ->and(app(SaveExpense::class)->name())->toBe('save_expense');
});

test('save care record via mcp marks provenance mcp', function () {
    $payload = FarmServer::actingAs($this->user)
        ->tool(SaveCareRecord::class, [
            'type' => 'fertilizer',
            'performed_on' => '2026-10-20',
            'product' => '15-15-15',
            'quantity' => '2 กก. ต่อต้น',
            'tree_codes' => ['A-001', 'A-002'],
        ])
        ->assertOk()
        ->payload();

    $record = CareRecord::findOrFail($payload['care_record']['id']);

    expect($record->created_via)->toBe('mcp')
        ->and($record->updated_via)->toBe('mcp')
        ->and($record->created_by)->toBe($this->user->id)
        ->and($record->isFromAi())->toBeTrue()
        ->and($record->type)->toBe(CareType::Fertilizer)
        ->and($record->trees()->pluck('code')->sort()->values()->all())->toBe(['A-001', 'A-002'])
        ->and($payload['care_record'])->toMatchArray([
            'orchard_id' => $this->orchard->id,
            'type' => 'fertilizer',
            'performed_on' => '2026-10-20',
            'tree_codes' => ['A-001', 'A-002'],
            'issue_ids' => [],
            'from_ai' => true,
        ]);
});

test('mcp provenance does not leak outside the tool call', function () {
    FarmServer::actingAs($this->user)
        ->tool(SaveHarvest::class, ['tree_code' => 'A-001', 'harvested_on' => '2026-10-20', 'weight_kg' => 10])
        ->assertOk();

    expect(Context::get(Provenance::VIA_KEY, Provenance::VIA_APP))->toBe(Provenance::VIA_APP);

    $harvest = Harvest::factory()->for($this->treeA1)->create();

    expect($harvest->created_via)->toBe('app');
});

test('pesticide via mcp links open issues by default', function () {
    $open = TreeIssue::factory()->for($this->treeA1)->create(['finding_id' => $this->redMite->id, 'opened_on' => '2026-10-14', 'follow_up_on' => '2026-10-21']);
    TreeIssue::factory()->for($this->treeA1)->resolved()->create();
    TreeIssue::factory()->for($this->treeA2)->create();

    $payload = FarmServer::actingAs($this->user)
        ->tool(SaveCareRecord::class, [
            'type' => 'pesticide',
            'performed_on' => '2026-10-21',
            'product' => 'อะบาเมกติน',
            'tree_codes' => ['A-001'],
        ])
        ->assertOk()
        ->payload();

    expect($payload['care_record']['issue_ids'])->toBe([$open->id])
        ->and($open->fresh()->follow_up_on->toDateString())->toBe('2026-10-28');
});

test('explicit empty issue ids links nothing even for pesticide', function () {
    $open = TreeIssue::factory()->for($this->treeA1)->create(['follow_up_on' => '2026-10-21']);

    $payload = FarmServer::actingAs($this->user)
        ->tool(SaveCareRecord::class, [
            'type' => 'pesticide',
            'performed_on' => '2026-10-21',
            'tree_codes' => ['A-001'],
            'issue_ids' => [],
        ])
        ->assertOk()
        ->payload();

    expect($payload['care_record']['issue_ids'])->toBe([])
        ->and($open->fresh()->follow_up_on->toDateString())->toBe('2026-10-21');
});

test('updating a care record without issue ids keeps its links', function () {
    $issue = TreeIssue::factory()->for($this->treeA1)->create();
    $record = CareRecord::factory()->for($this->orchard)->create(['type' => CareType::Pesticide, 'performed_on' => '2026-10-15']);
    $record->trees()->attach($this->treeA1);
    $record->issues()->attach($issue);

    $payload = FarmServer::actingAs($this->user)
        ->tool(SaveCareRecord::class, [
            'id' => $record->id,
            'type' => 'pesticide',
            'performed_on' => '2026-10-16',
            'tree_codes' => ['A-001'],
        ])
        ->assertOk()
        ->payload();

    expect($payload['care_record']['id'])->toBe($record->id)
        ->and($payload['care_record']['issue_ids'])->toBe([$issue->id])
        ->and(CareRecord::count())->toBe(1);
});

test('care record rejects a removed tree with the action message', function () {
    Tree::factory()->for($this->orchard)->removed()->create(['code' => 'A-009']);

    FarmServer::actingAs($this->user)
        ->tool(SaveCareRecord::class, ['type' => 'other', 'performed_on' => '2026-10-21', 'tree_codes' => ['A-009']])
        ->assertHasErrors(['ต้นไม้ไม่อยู่ในสวนนี้หรือไม่อยู่แล้ว']);

    expect(CareRecord::count())->toBe(0);
});

test('record observation for multiple trees appends to existing open issue', function () {
    $existing = TreeIssue::factory()->for($this->treeA1)->create(['finding_id' => $this->redMite->id, 'opened_on' => '2026-10-14', 'follow_up_on' => '2026-10-21']);

    $payload = FarmServer::actingAs($this->user)
        ->tool(RecordObservation::class, [
            'tree_codes' => ['A-001', 'A-002'],
            'finding' => ' ไรแดง ',
            'kind' => 'problem',
            'observed_on' => '2026-10-21',
            'severity' => 'medium',
            'notes' => 'ใบเหลือง',
        ])
        ->assertOk()
        ->payload();

    expect(TreeIssue::count())->toBe(2)
        ->and(Observation::count())->toBe(2)
        ->and($payload['observations'])->toHaveCount(2)
        ->and($payload['observations'][0])->toMatchArray([
            'tree_code' => 'A-001',
            'finding' => 'ไรแดง',
            'tree_issue_id' => $existing->id,
            'severity' => 'medium',
            'from_ai' => true,
        ])
        ->and($payload['observations'][1]['tree_code'])->toBe('A-002')
        ->and($payload['observations'][1]['tree_issue_id'])->not->toBe($existing->id);

    expect(Observation::query()->pluck('created_via')->unique()->all())->toBe(['mcp']);
});

test('record observation creates a new finding for an unknown name', function () {
    FarmServer::actingAs($this->user)
        ->tool(RecordObservation::class, ['tree_codes' => ['A-001'], 'finding' => 'ราดำ', 'kind' => 'problem', 'observed_on' => '2026-10-21'])
        ->assertOk();

    expect(Finding::where('name', 'ราดำ')->exists())->toBeTrue();
});

test('record observation for several trees is all or nothing', function () {
    Tree::factory()->for($this->orchard)->removed()->create(['code' => 'A-009']);

    FarmServer::actingAs($this->user)
        ->tool(RecordObservation::class, [
            'tree_codes' => ['A-001', 'A-009'],
            'finding' => 'ไรแดง',
            'kind' => 'problem',
            'observed_on' => '2026-10-21',
        ])
        ->assertHasErrors(['ต้นไม้นี้ไม่อยู่แล้ว']);

    expect(Observation::count())->toBe(0)->and(TreeIssue::count())->toBe(0);
});

test('record observation requires tree codes finding and kind when creating', function () {
    FarmServer::actingAs($this->user)
        ->tool(RecordObservation::class, ['observed_on' => '2026-10-21'])
        ->assertHasErrors(['tree_codes', 'finding', 'kind']);
});

test('record observation by id only changes date severity and notes', function () {
    $other = Finding::where('name', 'ออกดอก')->firstOrFail();
    $observation = Observation::factory()->for($this->treeA1)->create([
        'finding_id' => $other->id,
        'observed_on' => '2026-10-10',
        'notes' => 'เดิม',
    ]);

    $payload = FarmServer::actingAs($this->user)
        ->tool(RecordObservation::class, [
            'id' => $observation->id,
            'tree_codes' => ['A-002'],
            'finding' => 'ไรแดง',
            'kind' => 'problem',
            'observed_on' => '2026-10-12',
            'severity' => 'high',
        ])
        ->assertOk()
        ->payload();

    $observation->refresh();

    expect($payload['observations'])->toHaveCount(1)
        ->and($observation->observed_on->toDateString())->toBe('2026-10-12')
        ->and($observation->severity->value)->toBe('high')
        ->and($observation->notes)->toBe('เดิม')
        ->and($observation->tree_id)->toBe($this->treeA1->id)
        ->and($observation->finding_id)->toBe($other->id)
        ->and($observation->updated_via)->toBe('mcp')
        ->and(Observation::count())->toBe(1);
});

test('record follow up resolves issue', function () {
    $issue = TreeIssue::factory()->for($this->treeA1)->create(['finding_id' => $this->redMite->id, 'opened_on' => '2026-10-14', 'follow_up_on' => '2026-10-21']);

    $payload = FarmServer::actingAs($this->user)
        ->tool(RecordFollowUp::class, [
            'issue_id' => $issue->id,
            'observed_on' => '2026-10-21',
            'outcome' => 'resolved',
            'notes' => 'ไม่พบไรแล้ว',
        ])
        ->assertOk()
        ->payload();

    $observation = Observation::findOrFail($payload['observation']['id']);

    expect($issue->fresh()->resolved_on->toDateString())->toBe('2026-10-21')
        ->and($observation->outcome)->toBe(FollowUpOutcome::Resolved)
        ->and($observation->created_via)->toBe('mcp')
        ->and($payload['observation'])->toMatchArray(['tree_issue_id' => $issue->id, 'outcome' => 'resolved'])
        ->and($payload['issue'])->toMatchArray(['id' => $issue->id, 'resolved_on' => '2026-10-21']);
});

test('record follow up not resolved schedules the next check', function () {
    $issue = TreeIssue::factory()->for($this->treeA1)->create(['follow_up_on' => '2026-10-21']);

    $payload = FarmServer::actingAs($this->user)
        ->tool(RecordFollowUp::class, ['issue_id' => $issue->id, 'observed_on' => '2026-10-21', 'outcome' => 'not_resolved'])
        ->assertOk()
        ->payload();

    expect($payload['issue']['follow_up_on'])->toBe('2026-10-28')
        ->and($payload['issue']['resolved_on'])->toBeNull();
});

test('record follow up rejects unknown and closed issues', function () {
    $closed = TreeIssue::factory()->for($this->treeA1)->resolved()->create();

    FarmServer::actingAs($this->user)
        ->tool(RecordFollowUp::class, ['issue_id' => 999999, 'observed_on' => '2026-10-21', 'outcome' => 'resolved'])
        ->assertHasErrors(['issue_id 999999 does not exist']);

    FarmServer::actingAs($this->user)
        ->tool(RecordFollowUp::class, ['issue_id' => $closed->id, 'observed_on' => '2026-10-21', 'outcome' => 'resolved'])
        ->assertHasErrors(['ปัญหานี้ปิดไปแล้ว']);

    FarmServer::actingAs($this->user)
        ->tool(RecordFollowUp::class, ['issue_id' => $closed->id, 'observed_on' => '2026-10-21', 'outcome' => 'maybe'])
        ->assertHasErrors(['outcome must be one of: resolved, not_resolved']);
});

test('save harvest creates a harvest', function () {
    $payload = FarmServer::actingAs($this->user)
        ->tool(SaveHarvest::class, [
            'tree_code' => 'A-001',
            'harvested_on' => '2026-10-20',
            'weight_kg' => 12.5,
            'fruit_count' => 3,
        ])
        ->assertOk()
        ->payload();

    $harvest = Harvest::findOrFail($payload['harvest']['id']);

    expect($harvest->tree_id)->toBe($this->treeA1->id)
        ->and($harvest->created_via)->toBe('mcp')
        ->and($payload['harvest'])->toMatchArray(['tree_code' => 'A-001', 'weight_kg' => 12.5, 'fruit_count' => 3]);
});

test('save harvest on removed tree returns error', function () {
    Tree::factory()->for($this->orchard)->removed()->create(['code' => 'A-009']);

    FarmServer::actingAs($this->user)
        ->tool(SaveHarvest::class, ['tree_code' => 'A-009', 'harvested_on' => '2026-10-20', 'weight_kg' => 5])
        ->assertHasErrors(['A-009', 'removed']);

    expect(Harvest::count())->toBe(0);
});

test('save harvest validates the amounts', function () {
    FarmServer::actingAs($this->user)
        ->tool(SaveHarvest::class, ['tree_code' => 'A-001', 'harvested_on' => '2026-10-20', 'weight_kg' => 0])
        ->assertHasErrors(['weight_kg must be greater than 0']);

    FarmServer::actingAs($this->user)
        ->tool(SaveHarvest::class, ['harvested_on' => '2026-10-20', 'weight_kg' => 3])
        ->assertHasErrors(['tree_code is required']);
});

test('update via id changes existing record and sets updated_via mcp', function () {
    $harvest = Harvest::factory()->for($this->treeA1)->create(['harvested_on' => '2026-10-01', 'weight_kg' => 5, 'notes' => 'เดิม']);

    expect($harvest->created_via)->toBe('app');

    $payload = FarmServer::actingAs($this->user)
        ->tool(SaveHarvest::class, ['id' => $harvest->id, 'harvested_on' => '2026-10-02', 'weight_kg' => 7.25])
        ->assertOk()
        ->payload();

    $harvest->refresh();

    expect($payload['harvest']['id'])->toBe($harvest->id)
        ->and($harvest->weight_kg)->toBe('7.25')
        ->and($harvest->harvested_on->toDateString())->toBe('2026-10-02')
        ->and($harvest->notes)->toBeNull()
        ->and($harvest->created_via)->toBe('app')
        ->and($harvest->updated_via)->toBe('mcp')
        ->and($harvest->updated_by)->toBe($this->user->id)
        ->and(Harvest::count())->toBe(1);
});

test('update via id cannot move a harvest to another tree', function () {
    $harvest = Harvest::factory()->for($this->treeA1)->create();

    FarmServer::actingAs($this->user)
        ->tool(SaveHarvest::class, ['id' => $harvest->id, 'tree_code' => 'A-002', 'harvested_on' => '2026-10-02', 'weight_kg' => 7])
        ->assertHasErrors(['cannot be moved']);

    expect($harvest->fresh()->tree_id)->toBe($this->treeA1->id);
});

test('update with an unknown or deleted id is rejected', function () {
    $deleted = Harvest::factory()->for($this->treeA1)->create();
    $deleted->delete();

    FarmServer::actingAs($this->user)
        ->tool(SaveHarvest::class, ['id' => $deleted->id, 'harvested_on' => '2026-10-02', 'weight_kg' => 7])
        ->assertHasErrors(["No harvest with id {$deleted->id}", 'restore_record']);

    FarmServer::actingAs($this->user)
        ->tool(SaveSale::class, ['id' => 999999, 'sold_on' => '2026-10-02', 'weight_kg' => 7, 'amount' => 700])
        ->assertHasErrors(['No sale with id 999999']);
});

test('save sale creates and updates a sale', function () {
    $created = FarmServer::actingAs($this->user)
        ->tool(SaveSale::class, [
            'sold_on' => '2026-10-20',
            'buyer_name' => 'ล้งสมชาย',
            'weight_kg' => 100,
            'amount' => 25000,
        ])
        ->assertOk()
        ->payload();

    $sale = Sale::findOrFail($created['sale']['id']);

    expect($sale->orchard_id)->toBe($this->orchard->id)
        ->and($sale->created_via)->toBe('mcp')
        ->and($created['sale'])->toMatchArray(['buyer_name' => 'ล้งสมชาย', 'weight_kg' => 100.0, 'amount' => 25000.0]);

    FarmServer::actingAs($this->user)
        ->tool(SaveSale::class, ['id' => $sale->id, 'sold_on' => '2026-10-20', 'weight_kg' => 100, 'amount' => 26000])
        ->assertOk();

    expect($sale->fresh()->amount)->toBe('26000.00')
        ->and($sale->fresh()->buyer_name)->toBeNull()
        ->and(Sale::count())->toBe(1);
});

test('save sale rejects a sale moved to another orchard', function () {
    $other = Orchard::factory()->create();
    $sale = Sale::factory()->for($this->orchard)->create();

    FarmServer::actingAs($this->user)
        ->tool(SaveSale::class, ['id' => $sale->id, 'orchard_id' => $other->id, 'sold_on' => '2026-10-20', 'weight_kg' => 1, 'amount' => 10])
        ->assertHasErrors(['cannot be moved']);
});

test('save expense creates and updates an expense', function () {
    $created = FarmServer::actingAs($this->user)
        ->tool(SaveExpense::class, [
            'season_id' => $this->season->id,
            'category' => 'fertilizer',
            'spent_on' => '2026-10-19',
            'amount' => 1800,
        ])
        ->assertOk()
        ->payload();

    $expense = Expense::findOrFail($created['expense']['id']);

    expect($expense->category)->toBe(ExpenseCategory::Fertilizer)
        ->and($expense->season_id)->toBe($this->season->id)
        ->and($expense->created_via)->toBe('mcp');

    FarmServer::actingAs($this->user)
        ->tool(SaveExpense::class, ['id' => $expense->id, 'category' => 'labor', 'spent_on' => '2026-10-19', 'amount' => 900])
        ->assertOk();

    expect($expense->fresh()->category)->toBe(ExpenseCategory::Labor)
        ->and($expense->fresh()->season_id)->toBeNull()
        ->and(Expense::count())->toBe(1);
});

test('save expense rejects an unknown category and season', function () {
    FarmServer::actingAs($this->user)
        ->tool(SaveExpense::class, ['category' => 'snacks', 'spent_on' => '2026-10-19', 'amount' => 10])
        ->assertHasErrors(['category must be one of: fertilizer, pesticide, labor, utilities, equipment, other']);

    FarmServer::actingAs($this->user)
        ->tool(SaveExpense::class, ['season_id' => 999999, 'category' => 'other', 'spent_on' => '2026-10-19', 'amount' => 10])
        ->assertHasErrors(['season_id 999999 does not exist']);
});

test('write tools reject tree codes without orchard id when the farm has several orchards', function () {
    Orchard::factory()->create(['name' => 'สวนหลังบ้าน']);

    $message = 'Multiple orchards exist; pass orchard_id from get_farm_overview.';

    FarmServer::actingAs($this->user)
        ->tool(SaveHarvest::class, ['tree_code' => 'A-001', 'harvested_on' => '2026-10-20', 'weight_kg' => 5])
        ->assertHasErrors([$message]);

    FarmServer::actingAs($this->user)
        ->tool(SaveCareRecord::class, ['type' => 'other', 'performed_on' => '2026-10-20', 'tree_codes' => ['A-001']])
        ->assertHasErrors([$message]);

    FarmServer::actingAs($this->user)
        ->tool(RecordObservation::class, ['tree_codes' => ['A-001'], 'finding' => 'ไรแดง', 'kind' => 'problem', 'observed_on' => '2026-10-20'])
        ->assertHasErrors([$message]);

    FarmServer::actingAs($this->user)
        ->tool(SaveSale::class, ['sold_on' => '2026-10-20', 'weight_kg' => 5, 'amount' => 100])
        ->assertHasErrors([$message]);

    expect(Harvest::count() + CareRecord::count() + Observation::count() + Sale::count())->toBe(0);

    FarmServer::actingAs($this->user)
        ->tool(SaveHarvest::class, ['orchard_id' => $this->orchard->id, 'tree_code' => 'A-001', 'harvested_on' => '2026-10-20', 'weight_kg' => 5])
        ->assertOk();
});

test('farm server registers every write tool', function () {
    $tools = collect((new ReflectionClass(FarmServer::class))->getDefaultProperties()['tools']);

    expect($tools->all())->toContain(
        SaveCareRecord::class,
        RecordObservation::class,
        RecordFollowUp::class,
        SaveHarvest::class,
        SaveSale::class,
        SaveExpense::class,
    );
});
