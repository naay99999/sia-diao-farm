<?php

use App\Models\Orchard;
use App\Models\Season;
use App\Models\Tree;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('seeds only the owner outside the local environment', function () {
    $this->seed(DatabaseSeeder::class);

    expect(User::count())->toBe(1)
        ->and(User::first()->name)->toBe('เจ้าของสวน')
        ->and(Orchard::count())->toBe(0);
});

it('seeds demo orchards with one open season each in the local environment', function () {
    app()->detectEnvironment(fn () => 'local');

    $this->seed(DatabaseSeeder::class);

    expect(Orchard::count())->toBe(2)
        ->and(Tree::count())->toBe(40)
        ->and(Season::whereNull('ended_on')->count())->toBe(2);
});
