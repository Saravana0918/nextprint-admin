<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * Register Artisan commands.
     */
    protected $commands = [
        \App\Console\Commands\SyncShopifyDesignerProducts::class,
        \App\Console\Commands\ShopifySyncVariants::class,
    ];

    /**
     * Define the application's command schedule.
     */
    protected function schedule(Schedule $schedule): void
    {
        // Auto sync Shopify products from our collection
        $schedule->command('shopify:sync-designer')
            ->everyMinute()   // use everyMinute for testing; later change to everyFiveMinutes()
            ->withoutOverlapping();
    }

    /**
     * Register the commands for the application.
     */
    protected function commands(): void
    {
        $this->load(__DIR__.'/Commands');
        require base_path('routes/console.php');
    }
}
