<?php

declare(strict_types=1);

use Naluz\Schedule\Schedule;

// Scheduled tasks. Add ONE cron entry on the server and everything below runs on time:
//
//     * * * * * cd /path/to/app && php naluz schedule:run >> /dev/null 2>&1
//
return function (Schedule $schedule): void {
    // periods PayMongo charged by itself, and renewal orders for subscriptions that are due (both also run when an owner opens the dashboard)
    $schedule->call(static function (): void {
        $subscriptions = app(App\Services\Subscriptions::class);
        $subscriptions->syncAuto(); // periods the provider charged by itself
        $subscriptions->createDueRenewals();
    })->hourly();
    // $schedule->command('queue:work --stop-when-empty')->everyMinute()->withoutOverlapping();
    // $schedule->job(new App\Jobs\PruneOldRecords())->dailyAt('03:00');
    // $schedule->call(fn () => logger('tick'))->hourly();
};
