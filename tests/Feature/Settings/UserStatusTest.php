<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('users are active until deactivated', function () {
    expect(User::factory()->create()->isActive())->toBeTrue()
        ->and(User::factory()->deactivated()->create()->isActive())->toBeFalse();
});
