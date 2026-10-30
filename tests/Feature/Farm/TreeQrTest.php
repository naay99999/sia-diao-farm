<?php

use App\Models\Orchard;
use App\Models\Tree;
use App\Models\User;
use App\Support\TreeQrCode;
use Illuminate\Support\Facades\Cache;

test('tree qr code is an svg', function () {
    $svg = TreeQrCode::svg(Tree::factory()->create());

    expect($svg)->toStartWith('<?xml')
        ->and($svg)->toContain('<svg')
        ->and($svg)->not->toContain('error');
});

test('qr page lists active trees only in code order', function () {
    $orchard = Orchard::factory()->create();
    Tree::factory()->for($orchard)->create(['code' => 'B2']);
    Tree::factory()->for($orchard)->create(['code' => 'A1']);
    Tree::factory()->for($orchard)->create(['code' => 'C3', 'removed_on' => '2026-01-01']);

    $this->actingAs(User::factory()->create())
        ->get(route('orchards.qr', $orchard))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('farm/orchards/qr')
            ->has('labels', 2)
            ->where('labels.0.code', 'A1')
            ->where('labels.1.code', 'B2')
            ->where('labels.0.orchardName', $orchard->name)
            ->has('labels.0.svg'));
});

test('guests cannot open the qr page', function () {
    $this->get(route('orchards.qr', Orchard::factory()->create()))->assertRedirect(route('login'));
});

test('qr codes are drawn once and then read from the cache', function () {
    $drawn = Tree::factory()->create();
    $cached = Tree::factory()->create();
    Cache::put('tree-qr:'.sha1(route('trees.qr', $cached)), '<svg>cached</svg>');

    $svgs = TreeQrCode::svgs(collect([$drawn, $cached]));

    expect($svgs[$drawn->id])->toBe(TreeQrCode::svg($drawn))
        ->and($svgs[$cached->id])->toBe('<svg>cached</svg>')
        ->and(Cache::get('tree-qr:'.sha1(route('trees.qr', $drawn))))->toBe($svgs[$drawn->id]);
});
