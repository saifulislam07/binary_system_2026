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

    // First day of the week for weekly caps/cycles (Carbon: 0 = Sunday … 6 = Saturday).
    // Bangladesh's working week starts on Saturday.
    'week_starts_on' => (int) env('WEEK_STARTS_ON', 6),

    // Suspicious-activity scanner (rule #12). Flags for review, never blocks.
    'fraud' => [
        // A withdrawal requested this soon after activation is flagged.
        'rapid_withdrawal_hours' => (int) env('FRAUD_RAPID_WITHDRAWAL_HOURS', 72),
        // How far back the scheduled scan looks at withdrawals.
        'scan_days' => (int) env('FRAUD_SCAN_DAYS', 30),
    ],

    // Extra members LoadTestNetworkSeeder adds (dev/staging performance runs only).
    'load_test_members' => (int) env('LOAD_TEST_MEMBERS', 1000),

    // Initial super-admin created by DatabaseSeeder. Change the password in production.
    'seed_admin' => [
        'email' => env('ADMIN_EMAIL', 'admin@example.com'),
        'password' => env('ADMIN_PASSWORD', 'password'),
    ],

];
