<?php

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
