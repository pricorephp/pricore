<?php

it('returns a successful response', function () {
    $response = $this->get(route('login'));

    $response->assertStatus(200);
});
