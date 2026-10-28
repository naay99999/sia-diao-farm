<?php

use App\Enums\CareType;
use App\Models\CareRecord;
use App\Models\Expense;
use App\Models\Harvest;
use App\Models\Orchard;
use App\Models\Sale;
use App\Models\Season;
use App\Models\Tree;
use App\Models\TreeIssue;
use App\Models\User;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;

test('guests are sent to login', function () {
    $this->get(route('overview'))->assertRedirect(route('login'));
});

test('overview renders orchards and farm-wide issues', function () {
    Date::setTestNow('2026-10-24');
    $first = Orchard::factory()->create();
    $second = Orchard::factory()->create();
    TreeIssue::factory()->for(Tree::factory()->for($first)->create())->create(['follow_up_on' => '2026-10-20', 'resolved_on' => null]);
    TreeIssue::factory()->for(Tree::factory()->for($second)->create())->create(['follow_up_on' => '2026-11-30', 'resolved_on' => null]);

    $this->actingAs(User::factory()->create())
        ->get(route('overview'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('overview')
            ->has('orchards', 2)
            ->has('dueIssues', 1)
            ->has('openIssues', 2)
            ->missing('recentAiRecords')
            ->loadDeferredProps(fn ($reload) => $reload->has('recentAiRecords')));
});

test('overview runs the same number of queries however many orchards there are', function () {
    $count = function (int $orchards): int {
        foreach (range(1, $orchards) as $i) {
            $orchard = Orchard::factory()->create();
            $season = Season::factory()->for($orchard)->create(['started_on' => '2026-01-01', 'ended_on' => null]);
            $tree = Tree::factory()->for($orchard)->create();
            TreeIssue::factory()->for($tree)->create(['resolved_on' => null]);
            Harvest::factory()->for($tree)->create(['harvested_on' => '2026-05-01']);
            Sale::factory()->for($orchard)->create(['sold_on' => '2026-05-02']);
            Expense::factory()->create(['season_id' => $season->id]);
            CareRecord::factory()->for($orchard)->create(['type' => CareType::Fertilizer]);
        }

        $user = User::factory()->create();
        DB::enableQueryLog();
        DB::flushQueryLog();
        $this->actingAs($user)->get(route('overview'))->assertOk();
        $queries = count(DB::getQueryLog());
        DB::disableQueryLog();

        return $queries;
    };

    expect($count(5))->toBe($count(1));
});
