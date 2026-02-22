<?php

use App\Models\User;

test('app version is shared via inertia', function () {
    config(['app.version' => '1.2.3']);

    $this->actingAs(User::factory()->create());

    $this->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('version', '1.2.3'));
});
