<?php

/*
| Business-level settings that are environment-driven. Rates, caps and other
| tunables live in the `commission_rules` / `settings` tables (Phase 2+) so
| admins can change them without a deploy — do not add them here.
*/

return [

    // Commission matching cadence: 'daily' or 'weekly'.
    'commission_cycle' => env('COMMISSION_CYCLE', 'daily'),

    'currency' => 'BDT',

    // Initial super-admin created by DatabaseSeeder. Change the password in production.
    'seed_admin' => [
        'email' => env('ADMIN_EMAIL', 'admin@example.com'),
        'password' => env('ADMIN_PASSWORD', 'password'),
    ],

];
