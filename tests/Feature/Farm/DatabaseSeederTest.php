<?php

use App\Models\FarmNote;
use App\Models\Observation;
use App\Models\Orchard;
use App\Models\Season;
use App\Models\Tree;
use App\Models\TreeIssue;
use App\Models\User;
use App\Support\RecentAiRecords;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;

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

it('seeds demo issues, observations, and notes in the local environment', function () {
    app()->detectEnvironment(fn () => 'local');

    $this->seed(DatabaseSeeder::class);

    expect(Season::whereNotNull('ended_on')->count())->toBe(2)
        ->and(Tree::whereNotNull('removed_on')->count())->toBe(2)
        ->and(TreeIssue::open()->count())->toBe(6)
        ->and(TreeIssue::due(now())->count())->toBe(4)
        ->and(TreeIssue::whereNotNull('resolved_on')->count())->toBe(2)
        ->and(TreeIssue::has('careRecords')->count())->toBe(4)
        ->and(Observation::whereNotNull('outcome')->count())->toBe(4)
        ->and(FarmNote::count())->toBe(9)
        ->and(RecentAiRecords::latest())->toHaveCount(3);
});

it('does not overwrite an existing owner when re-seeding', function () {
    $this->seed(DatabaseSeeder::class);
    User::first()->update(['name' => 'ชื่อใหม่', 'password' => 'changed-password']);

    $this->seed(DatabaseSeeder::class);

    expect(User::count())->toBe(1)
        ->and(User::first()->name)->toBe('ชื่อใหม่')
        ->and(Hash::check('changed-password', User::first()->password))->toBeTrue();
});

it('refuses to seed the default owner password outside local and testing', function () {
    app()->detectEnvironment(fn () => 'production');
    config(['farm.owner.password' => 'password']);

    expect(fn () => (new DatabaseSeeder)->run())->toThrow(RuntimeException::class);
    expect(User::count())->toBe(0);

    config(['farm.owner.password' => null]);
    expect(fn () => (new DatabaseSeeder)->run())->toThrow(RuntimeException::class);

    config(['farm.owner.password' => 'a-real-secret']);
    (new DatabaseSeeder)->run();
    expect(User::count())->toBe(1);
});
