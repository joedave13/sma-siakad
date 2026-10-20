<?php

test('redirects the landing route to the dashboard', function () {
    $response = $this->get(route('home'));

    $response->assertRedirect('/dashboard');
});
