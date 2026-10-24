<?php

use App\Models\Harvest;
use App\Models\Tree;
use App\Models\User;
use App\Support\Provenance;
use Illuminate\Support\Facades\Context;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->user = User::factory()->owner()->create();
});

test('guests are redirected to login', function () {
    $this->get(route('assistant.index'))->assertRedirect(route('login'));
});

test('page lists the latest records written through mcp', function () {
    $tree = Tree::factory()->create(['code' => 'A-03']);
    Harvest::factory()->create(['tree_id' => $tree->id, 'weight_kg' => 12.5, 'harvested_on' => now()->subDay()]);

    $this->actingAs($this->user);
    Context::add(Provenance::VIA_KEY, Provenance::VIA_MCP);
    Harvest::factory()->create(['tree_id' => $tree->id, 'weight_kg' => 20, 'harvested_on' => now()->toDateString()]);
    Context::forget(Provenance::VIA_KEY);

    $this->actingAs($this->user)
        ->get(route('assistant.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->has('recentAiRecords', 1)
            ->where('recentAiRecords.0.title', 'ผลผลิต 20 กก.')
            ->where('recentAiRecords.0.subject', 'ต้น A-03')
            ->where('recentAiRecords.0.by', $this->user->name)
            ->where('recentAiRecords.0.href', route('trees.show', $tree, false)));
});

test('assistant page no longer carries connection setup', function () {
    $this->actingAs($this->user)->get(route('assistant.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->component('assistant/index')
            ->missing('setup')
            ->missing('members')
            ->missing('myConnections'));
});
