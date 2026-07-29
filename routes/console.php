<?php

use Illuminate\Support\Facades\Schedule;

/*
|--------------------------------------------------------------------------
| Scheduled tasks
|--------------------------------------------------------------------------
|
| These only fire if something is running `php artisan schedule:run` every
| minute. Laragon does not do that for you -- see the "Daily returns" section
| of README.md for the Windows Task Scheduler entry.
*/

// Pay the daily return on every active investment. withoutOverlapping guards
// against a slow run being started again before it finishes; the command itself
// is idempotent, so a duplicate run would be harmless anyway.
Schedule::command('investments:accrue')
    ->dailyAt('00:05')
    ->timezone(config('zenvora.defaults.withdrawal_timezone', 'Africa/Lagos'))
    ->withoutOverlapping()
    ->onFailure(fn () => logger()->error('Scheduled investment accrual failed.'));
