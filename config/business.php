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

    // Operational health (/up and /admin/health). Turn require_workers on in
    // production once cron and the queue worker run: /up then fails when
    // either stops.
    'health' => [
        'require_workers' => (bool) env('HEALTH_REQUIRE_WORKERS', false),
        'scheduler_max_minutes' => 3,
        'queue_max_minutes' => 15,
    ],

    // Weekly automatic restore test (backup:verify-restore). Needs a DB user
    // allowed to create/drop `<database>_restore_check` — enable on staging.
    'backup_verify_restore' => (bool) env('BACKUP_VERIFY_RESTORE', false),

    // Public contact details for the shop's footer. Blank ones are hidden.
    'contact' => [
        'phone' => env('SUPPORT_PHONE'),
        'email' => env('SUPPORT_EMAIL'),
        'address' => env('SUPPORT_ADDRESS'),
        'hours' => env('SUPPORT_HOURS'),
    ],

    // Extra members LoadTestNetworkSeeder adds (dev/staging performance runs only).
    'load_test_members' => (int) env('LOAD_TEST_MEMBERS', 1000),

    // Initial super-admin created by DatabaseSeeder. Change the password in production.
    'seed_admin' => [
        'email' => env('ADMIN_EMAIL', 'admin@example.com'),
        'password' => env('ADMIN_PASSWORD', 'password'),
    ],

];
