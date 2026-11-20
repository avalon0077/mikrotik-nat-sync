<?php

namespace Avalon\MikroTikNATSync\Providers;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\ServiceProvider;

class MikroTikNATSyncPluginProvider extends ServiceProvider
{
    public const INTERVALS = ['everyMinute', 'everyFiveMinutes', 'everyTenMinutes', 'hourly'];

    public function boot(): void
    {
        // Pelican registers this provider once per process (the console command
        // itself is auto-discovered from src/Console/Commands). The scheduler is
        // only resolved by schedule:run / schedule:list, so this is a no-op on web requests.
        $this->callAfterResolving(Schedule::class, function (Schedule $schedule) {
            $interval = (string) config('mikrotik-nat-sync.interval', 'everyFiveMinutes');

            if (!in_array($interval, self::INTERVALS, true)) {
                $interval = 'everyFiveMinutes';
            }

            $schedule->command('mikrotik:sync')
                ->{$interval}()
                ->withoutOverlapping();
        });
    }
}
