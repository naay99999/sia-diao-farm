<?php

use App\Enums\FindingKind;
use App\Models\FarmNote;
use App\Models\Finding;
use App\Models\Observation;
use App\Models\TreeIssue;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->user = User::factory()->create();
});

test('members can list, add and rename findings', function () {
    $used = Finding::factory()->problem()->create(['name' => 'ไรแดงทดสอบ']);
    Observation::factory()->create(['finding_id' => $used->id]);
    Finding::factory()->stage()->create(['name' => 'ออกดอกทดสอบ']);

    $this->actingAs($this->user)->get(route('findings.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->component('settings/findings')
            ->where('findings', fn ($findings) => collect($findings)->firstWhere('name', 'ไรแดงทดสอบ')['inUse'] === true
                && collect($findings)->firstWhere('name', 'ออกดอกทดสอบ')['inUse'] === false
                && collect($findings)->firstWhere('name', 'ออกดอกทดสอบ')['kind'] === 'stage'));

    $this->actingAs($this->user)->post(route('findings.store'), ['name' => '  เพลี้ยไฟ  ', 'kind' => 'problem'])
        ->assertRedirect(route('findings.index'));

    $finding = Finding::where('name', 'เพลี้ยไฟ')->sole();
    expect($finding->kind)->toBe(FindingKind::Problem);

    $this->actingAs($this->user)->patch(route('findings.update', $finding), ['name' => 'เพลี้ยจั๊กจั่น', 'kind' => 'stage'])
        ->assertRedirect(route('findings.index'));

    expect($finding->fresh())->name->toBe('เพลี้ยจั๊กจั่น')->kind->toBe(FindingKind::Stage);
});

test('finding names are unique', function () {
    Finding::factory()->create(['name' => 'ชื่อซ้ำทดสอบ']);
    $other = Finding::factory()->create(['name' => 'ชื่ออื่นทดสอบ']);

    $this->actingAs($this->user)->post(route('findings.store'), ['name' => ' ชื่อซ้ำทดสอบ ', 'kind' => 'stage'])
        ->assertSessionHasErrors('name');

    $this->actingAs($this->user)->patch(route('findings.update', $other), ['name' => 'ชื่อซ้ำทดสอบ', 'kind' => 'problem'])
        ->assertSessionHasErrors('name');

    $this->actingAs($this->user)->patch(route('findings.update', $other), ['name' => 'ชื่ออื่นทดสอบ', 'kind' => 'problem'])
        ->assertSessionHasNoErrors();
});

test('the kind cannot change while a finding is in use', function () {
    $finding = Finding::factory()->problem()->create();
    TreeIssue::factory()->create(['finding_id' => $finding->id]);

    $this->actingAs($this->user)->patch(route('findings.update', $finding), ['name' => $finding->name, 'kind' => 'stage'])
        ->assertSessionHasErrors(['kind' => 'เปลี่ยนชนิดไม่ได้ เพราะมีบันทึกใช้อยู่']);

    expect($finding->fresh()->kind)->toBe(FindingKind::Problem);
});

test('unused finding can be deleted', function () {
    $finding = Finding::factory()->create();

    $this->actingAs($this->user)->delete(route('findings.destroy', $finding))
        ->assertRedirect(route('findings.index'));

    $this->assertModelMissing($finding);
});

test('finding in use cannot be deleted', function () {
    $finding = Finding::factory()->create();
    Observation::factory()->create(['finding_id' => $finding->id]);

    $this->actingAs($this->user)->delete(route('findings.destroy', $finding))
        ->assertRedirect(route('findings.index'))
        ->assertInertiaFlash('toast.message', 'รายการนี้มีบันทึกใช้อยู่ ลบไม่ได้');

    $this->assertModelExists($finding);
});

test('finding used by a trashed observation cannot be deleted', function () {
    $finding = Finding::factory()->create();
    Observation::factory()->create(['finding_id' => $finding->id])->delete();

    $this->actingAs($this->user)->delete(route('findings.destroy', $finding));

    $this->assertModelExists($finding);
});

test('finding used by a trashed issue cannot be deleted', function () {
    $finding = Finding::factory()->create();
    TreeIssue::factory()->create(['finding_id' => $finding->id])->delete();

    $this->actingAs($this->user)->delete(route('findings.destroy', $finding));

    $this->assertModelExists($finding);
});

test('finding used by a note cannot be deleted', function () {
    $finding = Finding::factory()->create();
    FarmNote::factory()->create(['finding_id' => $finding->id]);

    $this->actingAs($this->user)->delete(route('findings.destroy', $finding))
        ->assertInertiaFlash('toast.message', 'รายการนี้มีบันทึกใช้อยู่ ลบไม่ได้');

    $this->assertModelExists($finding);
    expect(FarmNote::query()->whereNotNull('finding_id')->count())->toBe(1);
});

test('finding used by a trashed note cannot be deleted', function () {
    $finding = Finding::factory()->create();
    FarmNote::factory()->create(['finding_id' => $finding->id])->delete();

    $this->actingAs($this->user)->delete(route('findings.destroy', $finding));

    $this->assertModelExists($finding);
});

test('the finding list marks findings used only by a note as in use', function () {
    $finding = Finding::factory()->create(['name' => 'ก-ใช้โดยโน้ต']);
    FarmNote::factory()->create(['finding_id' => $finding->id]);

    $this->actingAs($this->user)->get(route('findings.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('findings', fn ($findings) => collect($findings)->firstWhere('id', $finding->id)['inUse'] === true));
});
