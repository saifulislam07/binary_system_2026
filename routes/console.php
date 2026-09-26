<?php

use App\Jobs\QueueHeartbeat;
use App\Services\SystemHealth;
use Illuminate\Support\Facades\Schedule;

/*
| Commission cycle (rule #5/#6). COMMISSION_CYCLE=daily runs every night for
| the previous day; weekly runs once a week for the week that just ended
| (config('business.week_starts_on') decides where weeks start).
*/
$cycle = Schedule::command('commission:run')
    ->withoutOverlapping()
    ->onOneServer();

if (config('business.commission_cycle') === 'weekly') {
    $cycle->weeklyOn((int) config('business.week_starts_on'), '00:15');
} else {
    $cycle->dailyAt('00:15');
}

/*
| Rank promotions + leadership/sales threshold bonuses (rule #11), on the
| same cadence, after the commission cycle has settled.
*/
$ranks = Schedule::command('ranks:evaluate')
    ->withoutOverlapping()
    ->onOneServer();

if (config('business.commission_cycle') === 'weekly') {
    $ranks->weeklyOn((int) config('business.week_starts_on'), '00:45');
} else {
    $ranks->dailyAt('00:45');
}

// Suspicious-activity scan (rule #12): flags for admin review, never blocks.
Schedule::command('fraud:scan')->hourly()->withoutOverlapping()->onOneServer();

/*
| Backups (Spatie Backup): database dump + uploaded files, after the nightly
| commission and rank runs. Only failures are mailed (BACKUP_NOTIFICATION_EMAIL).
*/
Schedule::command('backup:clean')->dailyAt('01:30')->onOneServer();
Schedule::command('backup:run')->dailyAt('02:00')->withoutOverlapping()->onOneServer();
Schedule::command('backup:monitor')->dailyAt('03:00')->onOneServer();

if (config('business.backup_verify_restore')) {
    Schedule::command('backup:verify-restore')->weeklyOn(5, '04:00')->withoutOverlapping()->onOneServer();
}

/*
| Heartbeats for /up and /admin/health: proves cron is running every minute
| and a queue worker is consuming jobs.
*/
Schedule::call(fn () => app(SystemHealth::class)->recordSchedulerHeartbeat())
    ->everyMinute()
    ->name('health:scheduler-heartbeat')
    ->onOneServer();
Schedule::job(new QueueHeartbeat)->everyFiveMinutes()->name('health:queue-heartbeat')->onOneServer();
