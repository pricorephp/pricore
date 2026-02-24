<?php

use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('confirm password screen can be rendered', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->get(route('password.confirm'));

    $response->assertStatus(200);

    $response->assertInertia(fn (Assert $page) => $page
        ->component('auth/confirm-password')
        ->where('hasPassword', true)
    );
});

test('password confirmation requires authentication', function () {
    $response = $this->get(route('password.confirm'));

    $response->assertRedirect(route('login'));
});

test('oauth user can confirm without entering a password', function () {
    $user = User::factory()->withoutPassword()->create();

    $response = $this->actingAs($user)
        ->post(route('password.confirm.store'), [
            'password' => '',
        ]);

    $response->assertRedirect();
    $response->assertSessionHasNoErrors();
});

test('confirm password page returns hasPassword false for oauth user', function () {
    $user = User::factory()->withoutPassword()->create();

    $this->actingAs($user)
        ->get(route('password.confirm'))
        ->assertInertia(fn (Assert $page) => $page
            ->component('auth/confirm-password')
            ->where('hasPassword', false)
        );
});
