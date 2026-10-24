<?php

use App\Models\Orchard;
use App\Models\Tree;
use App\Models\TreeIssue;
use App\Models\User;
use Illuminate\Support\Facades\Date;

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
            ->has('recentAiRecords'));
});
