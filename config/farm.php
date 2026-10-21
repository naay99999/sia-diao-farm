<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Owner Account
    |--------------------------------------------------------------------------
    |
    | Credentials of the single owner account created by the database seeder.
    |
    */

    'owner' => [
        'email' => env('OWNER_EMAIL', 'owner@example.com'),
        'password' => env('OWNER_PASSWORD', 'password'),
    ],

];
