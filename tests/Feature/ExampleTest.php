<?php

test('home redirects guests to login', function () {
    $this->get(route('home'))->assertRedirect(route('login'));
});
