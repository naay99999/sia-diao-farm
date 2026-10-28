<?php

use App\Models\Harvest;
use App\Support\DateFilter;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

test('the day bounds are inclusive', function () {
    foreach (['2026-03-31', '2026-04-01', '2026-04-30', '2026-05-01'] as $day) {
        Harvest::factory()->create(['harvested_on' => $day]);
    }

    $days = DateFilter::onOrBefore(DateFilter::onOrAfter(Harvest::query(), 'harvested_on', '2026-04-01'), 'harvested_on', Carbon::parse('2026-04-30 18:00'))
        ->orderBy('harvested_on')
        ->get()
        ->map(fn (Harvest $harvest): string => $harvest->harvested_on->toDateString())
        ->all();

    expect($days)->toBe(['2026-04-01', '2026-04-30']);
});

test('the filter leaves the column bare so the database can use an index on it', function () {
    $sql = DateFilter::onOrBefore(DateFilter::onOrAfter(Harvest::query(), 'harvested_on', '2026-04-01'), 'harvested_on', '2026-04-30')->toSql();

    expect($sql)->toContain('"harvested_on" >= ?')
        ->and($sql)->toContain('"harvested_on" < ?');
});

test('a passed date is not changed by the filter', function () {
    $day = Carbon::parse('2026-04-30 18:00');

    DateFilter::onOrBefore(Harvest::query(), 'harvested_on', $day);

    expect($day->toDateTimeString())->toBe('2026-04-30 18:00:00');
});

test('dates are stored as plain days, which the string comparison relies on', function () {
    Harvest::factory()->create(['harvested_on' => '2026-04-01 18:00']);

    expect(DB::table('harvests')->value('harvested_on'))->toBe('2026-04-01');
});
