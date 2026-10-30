<?php

use App\Models\FarmNote;
use App\Models\Finding;
use App\Models\Tree;
use App\Models\User;
use App\Support\Provenance;
use Illuminate\Support\Facades\Context;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->user = User::factory()->create();
});

test('members see every note with what it is tied to and who wrote it', function () {
    $finding = Finding::factory()->create(['name' => 'ราสีชมพูทดสอบ']);
    $tree = Tree::factory()->create(['code' => 'A-001']);
    $this->actingAs($this->user);
    Context::add(Provenance::VIA_KEY, Provenance::VIA_MCP);
    FarmNote::factory()->create([
        'body' => 'ฉีดยาแล้วหายเร็ว',
        'finding_id' => $finding->id,
        'tree_id' => $tree->id,
    ]);
    Context::forget(Provenance::VIA_KEY);
    FarmNote::factory()->create(['body' => 'โน้ตที่ลบแล้ว'])->delete();

    $this->get(route('notes.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->component('settings/notes')
            ->has('notes', 1)
            ->where('notes.0.body', 'ฉีดยาแล้วหายเร็ว')
            ->where('notes.0.findingName', 'ราสีชมพูทดสอบ')
            ->where('notes.0.treeCode', 'A-001')
            ->where('notes.0.orchardName', $tree->orchard->name)
            ->where('notes.0.fromAi', true)
            ->where('notes.0.aiBy', $this->user->name)
            ->has('findings')
            ->has('trees', 1));
});

test('members can add, edit and delete notes', function () {
    $finding = Finding::factory()->create();
    $tree = Tree::factory()->create();

    $this->actingAs($this->user)->post(route('notes.store'), [
        'body' => '  ช่วงดอกบานห้ามฉีดยาฆ่าแมลง  ',
        'finding_id' => $finding->id,
        'tree_id' => null,
    ])->assertRedirect(route('notes.index'));

    $note = FarmNote::sole();
    expect($note)
        ->body->toBe('ช่วงดอกบานห้ามฉีดยาฆ่าแมลง')
        ->finding_id->toBe($finding->id)
        ->created_via->toBe(Provenance::VIA_APP)
        ->created_by->toBe($this->user->id);

    $this->actingAs($this->user)->patch(route('notes.update', $note), [
        'body' => 'แก้แล้ว',
        'finding_id' => null,
        'tree_id' => $tree->id,
    ])->assertRedirect(route('notes.index'));

    expect($note->fresh())->body->toBe('แก้แล้ว')->finding_id->toBeNull()->tree_id->toBe($tree->id);

    $this->actingAs($this->user)->delete(route('notes.destroy', $note))
        ->assertRedirect(route('notes.index'));

    $this->assertSoftDeleted($note);
});

test('a note needs text and existing links', function () {
    $this->actingAs($this->user)->post(route('notes.store'), [
        'body' => '   ',
        'finding_id' => 999999,
        'tree_id' => 999999,
    ])->assertSessionHasErrors(['body', 'finding_id', 'tree_id']);

    $this->actingAs($this->user)->post(route('notes.store'), ['body' => str_repeat('ก', 5001)])
        ->assertSessionHasErrors('body');

    expect(FarmNote::count())->toBe(0);
});

test('guests cannot reach notes', function () {
    $this->get(route('notes.index'))->assertRedirect(route('login'));
    $this->post(route('notes.store'), ['body' => 'x'])->assertRedirect(route('login'));
});
