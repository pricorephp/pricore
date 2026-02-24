<?php

test('registration screen can be rendered', function () {
    config()->set('fortify.sign_up_enabled', true);

    $response = $this->get(route('register'));

    $response->assertStatus(200);
});

test('new users can register', function () {
    config()->set('fortify.sign_up_enabled', true);

    $response = $this->post(route('register.store'), [
        'name' => 'Test User',
        'email' => 'test@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
    ]);

    $this->assertAuthenticated();
    $response->assertRedirect(route('dashboard', absolute: false));
});

test('registration screen redirects to login when sign up is disabled', function () {
    config()->set('fortify.sign_up_enabled', false);

    $response = $this->get(route('register'));

    $response->assertRedirect(route('login'));
});

test('registration screen is accessible with invitation token when sign up is disabled', function () {
    config()->set('fortify.sign_up_enabled', false);

    $response = $this->withSession(['invitation_token' => 'test-token'])
        ->get(route('register'));

    $response->assertStatus(200);
});

test('new users cannot register when sign up is disabled', function () {
    config()->set('fortify.sign_up_enabled', false);

    $response = $this->post(route('register.store'), [
        'name' => 'Test User',
        'email' => 'test@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
    ]);

    $response->assertStatus(403);
    $this->assertGuest();
});

test('new users can register with invitation token when sign up is disabled', function () {
    config()->set('fortify.sign_up_enabled', false);

    $response = $this->withSession(['invitation_token' => 'test-token'])
        ->post(route('register.store'), [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

    $this->assertAuthenticated();
    $response->assertRedirect(route('dashboard', absolute: false));
});
