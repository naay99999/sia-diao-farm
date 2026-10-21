<?php

use App\Models\Orchard;
use App\Models\Season;
use App\Models\Tree;
use Illuminate\Support\Carbon;

test('boundary dates belong to the season', function () {
    $season = Season::factory()->create(['started_on' => '2025-06-01', 'ended_on' => '2026-03-31']);

    expect($season->containsDate(Carbon::parse('2025-06-01')))->toBeTrue()
        ->and($season->containsDate(Carbon::parse('2026-03-31')))->toBeTrue()
        ->and($season->containsDate(Carbon::parse('2026-04-01')))->toBeFalse()
        ->and($season->containsDate(Carbon::parse('2025-05-31')))->toBeFalse();
});

test('an open season runs up to today', function () {
    $season = Season::factory()->create(['started_on' => '2026-01-01', 'ended_on' => null]);

    expect($season->effectiveEnd()->toDateString())->toBe(today()->toDateString())
        ->and($season->containsDate(today()))->toBeTrue()
        ->and($season->containsDate(today()->addDay()))->toBeFalse();
});

test('covering returns the right season across adjacent seasons', function () {
    $orchard = Orchard::factory()->create();
    $first = Season::factory()->for($orchard)->create(['started_on' => '2025-06-01', 'ended_on' => '2026-03-31']);
    $second = Season::factory()->for($orchard)->create(['started_on' => '2026-04-01', 'ended_on' => null]);
    Season::factory()->create(['started_on' => '2025-06-01', 'ended_on' => null]);

    expect(Season::covering($orchard->id, Carbon::parse('2026-03-31'))->first()->is($first))->toBeTrue()
        ->and(Season::covering($orchard->id, Carbon::parse('2026-04-01'))->first()->is($second))->toBeTrue()
        ->and(Season::covering($orchard->id, Carbon::parse('2025-05-31'))->exists())->toBeFalse()
        ->and(Season::covering($orchard->id, today()->addDay())->exists())->toBeFalse();
});

test('applyDateWindow filters inclusively by the season dates', function () {
    $season = Season::factory()->create(['started_on' => '2025-06-01', 'ended_on' => '2026-03-31']);
    $orchard = $season->orchard;
    foreach (['2025-05-31', '2025-06-01', '2026-03-31', '2026-04-01'] as $date) {
        Tree::factory()->for($orchard)->create(['code' => $date, 'planted_on' => $date]);
    }

    $codes = $season->applyDateWindow(Tree::query(), 'planted_on')->orderBy('code')->pluck('code')->all();

    expect($codes)->toBe(['2025-06-01', '2026-03-31']);
});

test('current season is the orchard open season', function () {
    $orchard = Orchard::factory()->create();
    Season::factory()->for($orchard)->create(['started_on' => '2025-01-01', 'ended_on' => '2025-12-31']);
    $open = Season::factory()->for($orchard)->create(['started_on' => '2026-01-01']);

    expect($orchard->currentSeason->is($open))->toBeTrue();
});
