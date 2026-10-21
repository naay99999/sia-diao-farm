<?php

use App\Models\Orchard;
use App\Models\User;

test('guests are redirected to login from orchards index', function () {
    $this->get(route('orchards.index'))->assertRedirect(route('login'));
});

test('home redirects to orchards', function () {
    $this->actingAs(User::factory()->create())
        ->get('/')
        ->assertRedirect(route('orchards.index'));
});

test('home redirects guests to login', function () {
    $this->get('/')->assertRedirect(route('login'));
});

test('orchards index lists orchards', function () {
    Orchard::factory()->count(2)->create();

    $this->actingAs(User::factory()->create())
        ->get(route('orchards.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('farm/orchards/index')
            ->has('orchards', 2));
});

test('store creates an orchard and redirects to show', function () {
    $this->actingAs(User::factory()->create())
        ->post(route('orchards.store'), ['name' => 'สวนบ้านล่าง'])
        ->assertRedirect(route('orchards.show', Orchard::first()));

    $this->assertDatabaseHas('orchards', ['name' => 'สวนบ้านล่าง']);
});

test('store requires a name', function () {
    $this->actingAs(User::factory()->create())
        ->post(route('orchards.store'), ['name' => ''])
        ->assertSessionHasErrors('name');
});

test('show renders the orchard', function () {
    $orchard = Orchard::factory()->create();

    $this->actingAs(User::factory()->create())
        ->get(route('orchards.show', $orchard))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('farm/orchards/show')
            ->where('orchard.id', $orchard->id));
});

test('update changes the orchard', function () {
    $orchard = Orchard::factory()->create();

    $this->actingAs(User::factory()->create())
        ->patch(route('orchards.update', $orchard), ['name' => 'สวนใหม่', 'location' => 'จันทบุรี'])
        ->assertRedirect(route('orchards.show', $orchard));

    expect($orchard->fresh())->name->toBe('สวนใหม่')->location->toBe('จันทบุรี');
});

test('destroy removes an orchard', function () {
    $orchard = Orchard::factory()->create();

    $this->actingAs(User::factory()->create())
        ->delete(route('orchards.destroy', $orchard))
        ->assertRedirect(route('orchards.index'));

    $this->assertModelMissing($orchard);
});
