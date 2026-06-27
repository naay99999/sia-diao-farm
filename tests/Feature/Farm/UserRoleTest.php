<?php

use App\Enums\UserRole;
use App\Models\User;

test('users default to the user role', function () {
    $user = User::factory()->create();

    expect($user->role)->toBe(UserRole::User);
    expect($user->isAdmin())->toBeFalse();
});

test('admin factory state creates an admin', function () {
    $admin = User::factory()->admin()->create();

    expect($admin->role)->toBe(UserRole::Admin);
    expect($admin->isAdmin())->toBeTrue();
});
