<?php

use App\Models\Orchard;
use App\Models\Season;
use App\Models\User;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->orchard = Orchard::factory()->create();
});

function postSeason(mixed $test, Orchard $orchard, array $data)
{
    return $test->actingAs($test->user)->post(route('orchards.seasons.store', $orchard), $data);
}

test('creates an open season', function () {
    postSeason($this, $this->orchard, ['name' => 'ฤดู 2569', 'started_on' => '2026-01-01'])
        ->assertRedirect(route('orchards.show', $this->orchard))
        ->assertSessionHasNoErrors();

    $this->assertDatabaseHas('seasons', ['orchard_id' => $this->orchard->id, 'name' => 'ฤดู 2569', 'ended_on' => null]);
});

test('rejects a second open season', function () {
    Season::factory()->for($this->orchard)->create(['started_on' => '2026-01-01']);

    postSeason($this, $this->orchard, ['name' => 'อีกรอบ', 'started_on' => '2026-06-01'])
        ->assertSessionHasErrors(['started_on' => 'มีรอบที่ยังไม่ปิดอยู่แล้ว']);

    expect($this->orchard->seasons()->count())->toBe(1);
});

test('a season starting on the previous end date overlaps', function () {
    Season::factory()->for($this->orchard)->create(['started_on' => '2025-06-01', 'ended_on' => '2026-03-31']);

    postSeason($this, $this->orchard, ['name' => 'ซ้อน', 'started_on' => '2026-03-31'])
        ->assertSessionHasErrors(['started_on' => 'ช่วงวันซ้อนกับรอบอื่น']);
});

test('a season starting the day after the previous end date is accepted', function () {
    Season::factory()->for($this->orchard)->create(['started_on' => '2025-06-01', 'ended_on' => '2026-03-31']);

    postSeason($this, $this->orchard, ['name' => 'ต่อเนื่อง', 'started_on' => '2026-04-01'])
        ->assertSessionHasNoErrors();

    expect($this->orchard->seasons()->count())->toBe(2);
});

test('a closed season overlapping an open season is rejected', function () {
    Season::factory()->for($this->orchard)->create(['started_on' => '2026-06-01']);

    postSeason($this, $this->orchard, ['name' => 'ซ้อน', 'started_on' => '2026-05-01', 'ended_on' => '2026-07-01'])
        ->assertSessionHasErrors(['started_on' => 'ช่วงวันซ้อนกับรอบอื่น']);
});

test('a closed season entirely before an open season is accepted', function () {
    Season::factory()->for($this->orchard)->create(['started_on' => '2026-06-01']);

    postSeason($this, $this->orchard, ['name' => 'ก่อนหน้า', 'started_on' => '2025-06-01', 'ended_on' => '2026-05-31'])
        ->assertSessionHasNoErrors();
});

test('seasons of other orchards do not conflict', function () {
    Season::factory()->create(['started_on' => '2026-01-01']);

    postSeason($this, $this->orchard, ['name' => 'อีกสวน', 'started_on' => '2026-01-01'])
        ->assertSessionHasNoErrors();
});

test('end date must not precede start date', function () {
    postSeason($this, $this->orchard, ['name' => 'x', 'started_on' => '2026-05-01', 'ended_on' => '2026-04-01'])
        ->assertSessionHasErrors('ended_on');
});

test('update ignores the season itself when checking overlap', function () {
    $season = Season::factory()->for($this->orchard)->create(['started_on' => '2026-01-01']);

    $this->actingAs($this->user)
        ->patch(route('seasons.update', $season), ['name' => 'ใหม่', 'started_on' => '2026-02-01'])
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('orchards.show', $this->orchard));

    expect($season->fresh()->name)->toBe('ใหม่');
});

test('close sets ended_on, defaulting to today', function () {
    $season = Season::factory()->for($this->orchard)->create(['started_on' => '2026-01-01']);

    $this->actingAs($this->user)->patch(route('seasons.close', $season))->assertSessionHasNoErrors();

    expect($season->fresh()->ended_on->toDateString())->toBe(today()->toDateString());
});

test('close accepts an explicit date and rejects one before the start', function () {
    $season = Season::factory()->for($this->orchard)->create(['started_on' => '2026-01-01']);

    $this->actingAs($this->user)->patch(route('seasons.close', $season), ['ended_on' => '2025-12-31'])
        ->assertSessionHasErrors('ended_on');

    $this->actingAs($this->user)->patch(route('seasons.close', $season), ['ended_on' => '2026-03-31'])
        ->assertSessionHasNoErrors();

    expect($season->fresh()->ended_on->toDateString())->toBe('2026-03-31');
});

test('destroy deletes the season', function () {
    $season = Season::factory()->for($this->orchard)->create();

    $this->actingAs($this->user)->delete(route('seasons.destroy', $season))
        ->assertRedirect(route('orchards.show', $this->orchard));

    $this->assertModelMissing($season);
});

test('show renders the season page', function () {
    $season = Season::factory()->for($this->orchard)->create();

    $this->actingAs($this->user)->get(route('seasons.show', $season))
        ->assertInertia(fn ($page) => $page->component('farm/seasons/show')->where('season.id', $season->id));
});

test('orchard show passes the current season and past seasons newest first', function () {
    $old = Season::factory()->for($this->orchard)->create(['started_on' => '2024-01-01', 'ended_on' => '2024-12-31']);
    $newer = Season::factory()->for($this->orchard)->create(['started_on' => '2025-01-01', 'ended_on' => '2025-12-31']);
    $open = Season::factory()->for($this->orchard)->create(['started_on' => '2026-01-01']);

    $this->actingAs($this->user)->get(route('orchards.show', $this->orchard))
        ->assertInertia(fn ($page) => $page
            ->where('currentSeason.id', $open->id)
            ->where('seasons.0.id', $newer->id)
            ->where('seasons.1.id', $old->id)
            ->has('seasons', 2));
});
