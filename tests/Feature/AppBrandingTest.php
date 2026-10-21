<?php

use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('inertia shares app name and description', function () {
    config([
        'app.name' => 'เสี่ยเดียวฟาร์ม',
        'app.description' => 'ระบบ ERP ภายในฟาร์ม',
    ]);

    $this->actingAs(User::factory()->create())->get(route('orchards.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('name', 'เสี่ยเดียวฟาร์ม')
            ->where('description', 'ระบบ ERP ภายในฟาร์ม'),
        );
});
