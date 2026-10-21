<?php

use App\Enums\FindingKind;
use App\Mcp\Servers\FarmServer;
use App\Mcp\Tools\DeleteRecord;
use App\Mcp\Tools\GetFarmOverview;
use App\Mcp\Tools\GetTree;
use App\Mcp\Tools\ListNotes;
use App\Mcp\Tools\ListRecords;
use App\Mcp\Tools\RestoreRecord;
use App\Mcp\Tools\SaveNote;
use App\Models\CareRecord;
use App\Models\Expense;
use App\Models\FarmNote;
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

beforeEach(function () {
    Date::setTestNow('2026-10-21');

    $this->user = User::factory()->create();
    $this->orchard = Orchard::factory()->create(['name' => 'สวนหน้าบ้าน']);
    $this->season = Season::factory()->for($this->orchard)->create(['name' => 'ฤดู 2569', 'started_on' => '2026-01-01']);
    $this->tree = Tree::factory()->for($this->orchard)->create(['code' => 'A-001']);
    $this->otherTree = Tree::factory()->for($this->orchard)->create(['code' => 'A-002']);
    $this->redMite = Finding::where('name', 'ไรแดง')->firstOrFail();
});

test('write note tools carry the confirmation rule and destructive annotation', function () {
    foreach ([SaveNote::class, DeleteRecord::class, RestoreRecord::class] as $tool) {
        expect(app($tool)->description())->toStartWith('Only call after the user confirmed a summary in chat.');
    }

    expect(app(SaveNote::class)->name())->toBe('save_note')
        ->and(app(ListNotes::class)->name())->toBe('list_notes')
        ->and(app(DeleteRecord::class)->name())->toBe('delete_record')
        ->and(app(RestoreRecord::class)->name())->toBe('restore_record');
});

test('delete tool is annotated destructive', function () {
    expect(app(DeleteRecord::class)->annotations())->toMatchArray(['destructiveHint' => true])
        ->and(app(ListNotes::class)->annotations())->toMatchArray(['readOnlyHint' => true])
        ->and(app(SaveNote::class)->annotations())->not->toHaveKey('destructiveHint')
        ->and(app(RestoreRecord::class)->annotations())->not->toHaveKey('destructiveHint');
});

test('the notes and deletion tools are registered on the farm server', function () {
    $registered = (new ReflectionProperty(FarmServer::class, 'tools'))->getDefaultValue();

    expect($registered)->toContain(SaveNote::class, ListNotes::class, DeleteRecord::class, RestoreRecord::class);
});

test('save note via mcp marks provenance and links an existing finding and tree', function () {
    $payload = FarmServer::actingAs($this->user)
        ->tool(SaveNote::class, [
            'body' => 'ไรแดงมักระบาดช่วงแล้ง ควรพ่นน้ำที่ใต้ใบ',
            'finding' => ' ไรแดง ',
            'tree_code' => 'A-001',
        ])
        ->assertOk()
        ->payload();

    $note = FarmNote::findOrFail($payload['note']['id']);

    expect($payload['note'])->toMatchArray([
        'body' => 'ไรแดงมักระบาดช่วงแล้ง ควรพ่นน้ำที่ใต้ใบ',
        'finding_id' => $this->redMite->id,
        'finding' => 'ไรแดง',
        'tree_id' => $this->tree->id,
        'tree_code' => 'A-001',
        'from_ai' => true,
    ])
        ->and($note->created_via)->toBe('mcp')
        ->and($note->created_by)->toBe($this->user->id)
        ->and(Finding::where('name', 'ไรแดง')->count())->toBe(1);
});

test('save note creates a new finding with the given kind, defaulting to problem', function () {
    FarmServer::actingAs($this->user)
        ->tool(SaveNote::class, ['body' => 'a', 'finding' => 'เพลี้ยไฟ'])
        ->assertOk();
    FarmServer::actingAs($this->user)
        ->tool(SaveNote::class, ['body' => 'b', 'finding' => 'ระยะดอกบาน', 'kind' => 'stage'])
        ->assertOk();

    expect(Finding::where('name', 'เพลี้ยไฟ')->firstOrFail()->kind)->toBe(FindingKind::Problem)
        ->and(Finding::where('name', 'ระยะดอกบาน')->firstOrFail()->kind)->toBe(FindingKind::Stage);
});

test('save note without finding or tree is a general note and id replaces an existing note', function () {
    $created = FarmServer::actingAs($this->user)
        ->tool(SaveNote::class, ['body' => 'ใส่ปุ๋ยหลังเก็บเกี่ยวทุกครั้ง'])
        ->assertOk()
        ->payload()['note'];

    expect($created)->toMatchArray(['finding_id' => null, 'finding' => null, 'tree_id' => null, 'tree_code' => null]);

    $updated = FarmServer::actingAs($this->user)
        ->tool(SaveNote::class, ['id' => $created['id'], 'body' => 'ใส่ปุ๋ยหลังเก็บเกี่ยว 2 สัปดาห์', 'finding' => 'ไรแดง'])
        ->assertOk()
        ->payload()['note'];

    expect($updated['id'])->toBe($created['id'])
        ->and($updated['body'])->toBe('ใส่ปุ๋ยหลังเก็บเกี่ยว 2 สัปดาห์')
        ->and($updated['finding_id'])->toBe($this->redMite->id)
        ->and(FarmNote::count())->toBe(1);
});

test('save note explains validation problems in English', function () {
    FarmServer::actingAs($this->user)
        ->tool(SaveNote::class, [])
        ->assertHasErrors(['body is required']);
    FarmServer::actingAs($this->user)
        ->tool(SaveNote::class, ['body' => '   '])
        ->assertHasErrors(['body is required']);
    FarmServer::actingAs($this->user)
        ->tool(SaveNote::class, ['body' => 'x', 'finding' => 'ไรแดง', 'kind' => 'weather'])
        ->assertHasErrors(['kind must be one of: problem, stage']);
    FarmServer::actingAs($this->user)
        ->tool(SaveNote::class, ['body' => 'x', 'tree_code' => 'Z-999'])
        ->assertHasErrors(['Z-999']);
    FarmServer::actingAs($this->user)
        ->tool(SaveNote::class, ['id' => 999999, 'body' => 'x'])
        ->assertHasErrors(['No note with id 999999', 'restore_record']);

    expect(FarmNote::count())->toBe(0);
});

test('save and list notes filtered by finding includes general notes', function () {
    $stage = Finding::factory()->stage()->create(['name' => 'ระยะผลอ่อน']);
    $mite = FarmNote::factory()->create(['body' => 'โน้ตไรแดง', 'finding_id' => $this->redMite->id, 'created_at' => '2026-10-10']);
    $general = FarmNote::factory()->create(['body' => 'โน้ตทั่วไป', 'created_at' => '2026-10-15']);
    FarmNote::factory()->create(['body' => 'โน้ตระยะผลอ่อน', 'finding_id' => $stage->id]);
    $deleted = FarmNote::factory()->create(['body' => 'โน้ตที่ลบแล้ว', 'finding_id' => $this->redMite->id]);
    $deleted->delete();

    FarmServer::actingAs($this->user)
        ->tool(SaveNote::class, ['body' => 'โน้ตใหม่เรื่องไรแดง', 'finding' => 'ไรแดง'])
        ->assertOk();

    $payload = FarmServer::actingAs($this->user)
        ->tool(ListNotes::class, ['finding' => 'ไรแดง'])
        ->assertOk()
        ->payload();

    expect(collect($payload['notes'])->pluck('body')->all())
        ->toBe(['โน้ตใหม่เรื่องไรแดง', 'โน้ตทั่วไป', 'โน้ตไรแดง'])
        ->and($payload['has_more'])->toBeFalse();

    $all = FarmServer::actingAs($this->user)->tool(ListNotes::class)->assertOk()->payload();

    expect($all['notes'])->toHaveCount(4)
        ->and(collect($all['notes'])->pluck('id')->all())->not->toContain($deleted->id)
        ->and($general->id)->toBeInt()
        ->and($mite->id)->toBeInt();
});

test('list notes filtered by tree includes notes of that tree and general notes only', function () {
    FarmNote::factory()->create(['body' => 'โน้ตของ A-001', 'tree_id' => $this->tree->id]);
    FarmNote::factory()->create(['body' => 'โน้ตของ A-002', 'tree_id' => $this->otherTree->id]);
    FarmNote::factory()->create(['body' => 'โน้ตทั่วไป']);
    FarmNote::factory()->create(['body' => 'โน้ตไรแดงทั้งสวน', 'finding_id' => $this->redMite->id]);

    $payload = FarmServer::actingAs($this->user)
        ->tool(ListNotes::class, ['tree_code' => 'A-001'])
        ->assertOk()
        ->payload();

    expect(collect($payload['notes'])->pluck('body')->sort()->values()->all())
        ->toBe(collect(['โน้ตของ A-001', 'โน้ตทั่วไป'])->sort()->values()->all());
});

test('list notes combines finding and tree filters and reports unknown names', function () {
    FarmNote::factory()->create(['body' => 'ไรแดง', 'finding_id' => $this->redMite->id]);
    FarmNote::factory()->create(['body' => 'ต้น A-001', 'tree_id' => $this->tree->id]);
    FarmNote::factory()->create(['body' => 'ต้น A-002', 'tree_id' => $this->otherTree->id]);
    FarmNote::factory()->create(['body' => 'ทั่วไป']);

    $payload = FarmServer::actingAs($this->user)
        ->tool(ListNotes::class, ['finding' => 'ไรแดง', 'tree_code' => 'A-001'])
        ->assertOk()
        ->payload();

    expect(collect($payload['notes'])->pluck('body')->sort()->values()->all())
        ->toBe(collect(['ไรแดง', 'ต้น A-001', 'ทั่วไป'])->sort()->values()->all());

    FarmServer::actingAs($this->user)
        ->tool(ListNotes::class, ['finding' => 'ไม่มีจริง'])
        ->assertHasErrors(['No finding named', 'get_farm_overview']);
});

test('list notes can include deleted notes and respects the limit', function () {
    $gone = FarmNote::factory()->create(['body' => 'ลบแล้ว']);
    $gone->delete();
    FarmNote::factory()->count(3)->create();

    $withDeleted = FarmServer::actingAs($this->user)
        ->tool(ListNotes::class, ['include_deleted' => true])
        ->assertOk()
        ->payload();

    expect($withDeleted['notes'])->toHaveCount(4)
        ->and(collect($withDeleted['notes'])->firstWhere('id', $gone->id)['deleted_at'])->not->toBeNull();

    $limited = FarmServer::actingAs($this->user)->tool(ListNotes::class, ['limit' => 2])->assertOk()->payload();

    expect($limited['notes'])->toHaveCount(2)->and($limited['has_more'])->toBeTrue();
});

test('delete record soft deletes and restore brings it back', function () {
    $care = CareRecord::factory()->for($this->orchard)->create();
    $note = FarmNote::factory()->create(['body' => 'ลบโน้ตนี้']);

    foreach ([['care', $care], ['note', $note]] as [$type, $record]) {
        $deleted = FarmServer::actingAs($this->user)
            ->tool(DeleteRecord::class, ['type' => $type, 'id' => $record->id])
            ->assertOk()
            ->payload();

        expect($deleted)->toMatchArray([
            'type' => $type,
            'id' => $record->id,
            'message' => 'Deleted. It can be restored with restore_record.',
        ])
            ->and($record::withTrashed()->find($record->id)->trashed())->toBeTrue()
            ->and($record::query()->find($record->id))->toBeNull();

        $restored = FarmServer::actingAs($this->user)
            ->tool(RestoreRecord::class, ['type' => $type, 'id' => $record->id])
            ->assertOk()
            ->payload();

        expect($restored)->toMatchArray(['type' => $type, 'id' => $record->id])
            ->and($restored['record']['id'])->toBe($record->id)
            ->and($record::query()->find($record->id))->not->toBeNull();
    }
});

test('delete and restore work for every record type', function (string $type, Closure $make) {
    $record = $make($this);

    FarmServer::actingAs($this->user)
        ->tool(DeleteRecord::class, ['type' => $type, 'id' => $record->id])
        ->assertOk();

    expect($record::withTrashed()->findOrFail($record->id)->trashed())->toBeTrue();

    FarmServer::actingAs($this->user)
        ->tool(RestoreRecord::class, ['type' => $type, 'id' => $record->id])
        ->assertOk();

    expect($record::query()->find($record->id))->not->toBeNull();
})->with([
    'care' => ['care', fn ($t) => CareRecord::factory()->for($t->orchard)->create()],
    'harvest' => ['harvest', fn ($t) => Harvest::factory()->for($t->tree)->create()],
    'sale' => ['sale', fn ($t) => Sale::factory()->for($t->orchard)->create()],
    'expense' => ['expense', fn ($t) => Expense::factory()->for($t->season)->create()],
    'observation' => ['observation', fn ($t) => Observation::factory()->for($t->tree)->create(['finding_id' => $t->redMite->id])],
    'issue' => ['issue', fn ($t) => TreeIssue::factory()->for($t->tree)->create(['finding_id' => $t->redMite->id])],
    'note' => ['note', fn ($t) => FarmNote::factory()->create()],
]);

test('delete and restore explain what went wrong', function () {
    $care = CareRecord::factory()->for($this->orchard)->create();

    FarmServer::actingAs($this->user)
        ->tool(DeleteRecord::class, ['type' => 'care', 'id' => 999999])
        ->assertHasErrors(['No care record with id 999999']);
    FarmServer::actingAs($this->user)
        ->tool(DeleteRecord::class, ['type' => 'tree', 'id' => 1])
        ->assertHasErrors(['type must be one of: care, harvest, sale, expense, observation, issue, note']);
    FarmServer::actingAs($this->user)
        ->tool(DeleteRecord::class, ['type' => 'care'])
        ->assertHasErrors(['id is required']);
    FarmServer::actingAs($this->user)
        ->tool(RestoreRecord::class, ['type' => 'care', 'id' => $care->id])
        ->assertHasErrors(['is not deleted']);
    FarmServer::actingAs($this->user)
        ->tool(RestoreRecord::class, ['type' => 'care', 'id' => 999999])
        ->assertHasErrors(['No care record with id 999999']);

    $care->delete();

    FarmServer::actingAs($this->user)
        ->tool(DeleteRecord::class, ['type' => 'care', 'id' => $care->id])
        ->assertHasErrors(['already deleted', 'restore_record']);
});

test('restore marks the record as touched by mcp', function () {
    $note = FarmNote::factory()->create();
    $note->delete();

    FarmServer::actingAs($this->user)
        ->tool(RestoreRecord::class, ['type' => 'note', 'id' => $note->id])
        ->assertOk();

    expect($note->fresh()->updated_via)->toBe('mcp');
});

test('deleted records can be found again through list tools', function () {
    $care = CareRecord::factory()->for($this->orchard)->create();

    FarmServer::actingAs($this->user)->tool(DeleteRecord::class, ['type' => 'care', 'id' => $care->id])->assertOk();

    $payload = FarmServer::actingAs($this->user)
        ->tool(ListRecords::class, ['type' => 'care', 'include_deleted' => true])
        ->assertOk()
        ->payload();

    expect($payload['records'][0])->toMatchArray(['id' => $care->id])
        ->and($payload['records'][0]['deleted_at'])->not->toBeNull();

    FarmServer::actingAs($this->user)
        ->tool(ListRecords::class, ['type' => 'note'])
        ->assertHasErrors(['type must be one of: care, harvest, sale, expense, observation, issue']);
});

test('deleting an issue hides it from overview due list', function () {
    $issue = TreeIssue::factory()->for($this->tree)->create([
        'finding_id' => $this->redMite->id,
        'opened_on' => '2026-10-14',
        'follow_up_on' => '2026-10-21',
    ]);

    $before = FarmServer::actingAs($this->user)->tool(GetFarmOverview::class)->assertOk()->payload();

    expect(collect($before['due_issues'])->pluck('id')->all())->toBe([$issue->id]);

    FarmServer::actingAs($this->user)
        ->tool(DeleteRecord::class, ['type' => 'issue', 'id' => $issue->id])
        ->assertOk();

    $after = FarmServer::actingAs($this->user)->tool(GetFarmOverview::class)->assertOk()->payload();

    expect($after['due_issues'])->toBe([])
        ->and($after['open_issues'])->toBe([]);

    FarmServer::actingAs($this->user)
        ->tool(RestoreRecord::class, ['type' => 'issue', 'id' => $issue->id])
        ->assertOk();

    $restored = FarmServer::actingAs($this->user)->tool(GetFarmOverview::class)->assertOk()->payload();

    expect(collect($restored['due_issues'])->pluck('id')->all())->toBe([$issue->id]);
});

test('observations of a deleted issue still read fine in tree and record tools', function () {
    $issue = TreeIssue::factory()->for($this->tree)->create(['finding_id' => $this->redMite->id]);
    $observation = Observation::factory()->for($this->tree)->create([
        'finding_id' => $this->redMite->id,
        'tree_issue_id' => $issue->id,
    ]);

    FarmServer::actingAs($this->user)->tool(DeleteRecord::class, ['type' => 'issue', 'id' => $issue->id])->assertOk();

    $tree = FarmServer::actingAs($this->user)->tool(GetTree::class, ['code' => 'A-001'])->assertOk()->payload();

    expect($tree['open_issues'])->toBe([])
        ->and(collect($tree['timeline'])->pluck('id')->all())->toContain($observation->id);

    $records = FarmServer::actingAs($this->user)
        ->tool(ListRecords::class, ['type' => 'observation'])
        ->assertOk()
        ->payload();

    expect($records['records'][0])->toMatchArray(['id' => $observation->id, 'tree_issue_id' => $issue->id]);
});

test('a farm note does not count as a tree record and survives the tree being removed', function () {
    $tree = Tree::factory()->for($this->orchard)->create(['code' => 'B-001']);
    $note = FarmNote::factory()->create(['tree_id' => $tree->id]);

    expect($tree->hasRecords())->toBeFalse();

    $tree->delete();

    expect($note->fresh()->tree_id)->toBeNull();
});

test('restoring an issue is refused while another open issue exists for the same tree and finding', function () {
    $deleted = TreeIssue::factory()->for($this->tree)->create(['finding_id' => $this->redMite->id]);
    $deleted->delete();
    TreeIssue::factory()->for($this->tree)->create(['finding_id' => $this->redMite->id]);

    FarmServer::actingAs($this->user)
        ->tool(RestoreRecord::class, ['type' => 'issue', 'id' => $deleted->id])
        ->assertHasErrors(['already has another open issue for the same finding']);

    expect($deleted->fresh()->trashed())->toBeTrue();
});
