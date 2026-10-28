<?php

return [

    /*
    |--------------------------------------------------------------------------
    | First Company Manager
    |--------------------------------------------------------------------------
    |
    | The seeded manager account that makes a fresh install usable. A manager
    | always covers both departments (D-063). Set the password explicitly in
    | real environments; when it is left empty the seeder generates one and
    | prints it once to the console so the operator can sign in and change it.
    |
    */

    'first_manager' => [
        'name' => env('CRM_MANAGER_NAME', 'مدير الشركة'),
        'email' => env('CRM_MANAGER_EMAIL'),
        'password' => env('CRM_MANAGER_PASSWORD'),
    ],

];
