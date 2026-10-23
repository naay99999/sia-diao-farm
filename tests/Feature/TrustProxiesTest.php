<?php

test('links follow the https address the reverse proxy forwards', function () {
    $this->get('/', [
        'X-Forwarded-Proto' => 'https',
        'X-Forwarded-Host' => 'farm.example.com',
        'X-Forwarded-Port' => '443',
    ])->assertRedirect('https://farm.example.com/login');
});
