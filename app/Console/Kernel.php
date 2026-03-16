<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * Define the application's command schedule.
     */
    protected function schedule(Schedule $schedule): void
    {
        // 每周一凌晨 2 点更新 EVE 物品数据
        $schedule->command('eve:update-data')
                 ->weeklyOn(1, '02:00')
                 ->withoutOverlapping();

        // 每天凌晨 3 点刷新市场分组树和星域列表缓存
        $schedule->command('market:cache-groups')
                 ->dailyAt('03:00')
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
