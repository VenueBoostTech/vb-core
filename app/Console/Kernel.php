<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;


class Kernel extends ConsoleKernel
{
    /**
     * Define the application's command schedule.
     *
     * @param Schedule $schedule
     * @return void
     */
    protected function schedule(Schedule $schedule)
    {
        // Running Cron-jobs
        $schedule->command('bb-calendar:refresh-connections')->everyMinute();
        $schedule->command('pricing-plans:sync')->everySixHours();
        $schedule->command('bb-inventory-alpha:sync')->hourly();

        // Invalid cron-jobs, need re-check
        // $schedule->command('post-onboarding:set-survey-feedback-email')->everySixHours();
        // $schedule->command('chatbot:lead-capture')->hourly();

        // $schedule->command('inventory:sync-warehouse')->hourly();
        // $schedule->command('inventory:sync-retail')->hourly();

        // $schedule->call(function () {
        // event(new \App\Events\NotificationCheckEvent());
        // })->everyMinute();

        // $schedule->command('reservation:set-reminder-sms')->everyMinute();
        // $schedule->command('waitlist:notify-guests')->everyMinute();
        // $schedule->command('expenses:integrate-with-payroll')->everySixHours();
        // $schedule->command('openai:admin-chatbot-train:food')->hourly();
        // $schedule->command('openai:admin-chatbot-train:retail')->hourly();
        // $schedule->command('openai:admin-chatbot-train:accommodation')->hourly();
        // $schedule->command('openai:admin-chatbot-train:entertainment')->hourly();
        // $schedule->command('integrations:retrieve-orders-from-uber-eats')->everyMinute();
        // $schedule->command('inventories:third-party-sync')->everyMinute();
        // $schedule->command('campaign:send-notification')->everyMinute();

        // Tested and working (uncomment these lines once a food venue client is registered)
        // $schedule->command('reservation:set-confirm-cancel-sms')->everyMinute();
        // $schedule->command('communication-preferences:send-pre-arrival-reminders')->everyMinute();
        // $schedule->command('communication-preferences:send-in-place-notification')->everyMinute();
        // $schedule->command('communication-preferences:send-post-reservation-notification')->everyMinute();

        // $schedule->command('occupancy:prepare-data 0.8')->hourly();
        // $schedule->command('time-series:train-forecast-occupancy-rates-model')->hourly();
    }

    /**
     * Register the commands for the application.
     *
     * @return void
     */
    protected function commands()
    {
        $this->load(__DIR__.'/Commands');

        require base_path('routes/console.php');
    }
}
