<?php

namespace App\Console;

use App\Console\Commands\IntegrateExpensesWithPayroll;
use App\Console\Commands\NotifyGuestsForWaitlist;
use App\Console\Commands\PrepareOccupancyRateData;
use App\Console\Commands\SendCampaignNotification;
use App\Console\Commands\SendInPlaceNotification;
use App\Console\Commands\SendPostOnboardingSurveyFeedbackEmail;
use App\Console\Commands\SendPreArrivalReminder;
use App\Console\Commands\SendReservationReminderSMS;
use App\Console\Commands\SendReservationConfirmCancelSMS;
use App\Console\Commands\SyncPricingPlans;
use App\Console\Commands\ThirdPartySyncInventories;
use App\Console\Commands\TrainOpenAIModelFood;
use App\Console\Commands\TrainOpenAIModelRetail;
use App\Console\Commands\TrainOpenAIModelEntertainment;
use App\Console\Commands\TrainOpenAIModelAccommodation;
use App\Console\Commands\TrainTimeSeriesModelAndForecastOccupancyRates;
use App\Console\Commands\Welcome12H;
use App\Jobs\SyncChatbotLeadCreation;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;


class Kernel extends ConsoleKernel
{
    /**
     * The Artisan commands provided by your application.
     *
     * @var array
     */
    protected $commands = [
        // TODO: after v1 testing add commands that store the prompts and responses in the database
        // scheduled run commands
        SendReservationReminderSMS::class,
        SendReservationConfirmCancelSMS::class,
        NotifyGuestsForWaitlist::class,
        IntegrateExpensesWithPayroll::class,
        TrainOpenAIModelFood::class,
        TrainOpenAIModelEntertainment::class,
        TrainOpenAIModelAccommodation::class,
        TrainOpenAIModelRetail::class,
        ThirdPartySyncInventories::class,
        PrepareOccupancyRateData::class,
        TrainTimeSeriesModelAndForecastOccupancyRates::class,
        SendPreArrivalReminder::class,
        SendInPlaceNotification::class,
        SendCampaignNotification::class,
        Welcome12H::class,
        SyncPricingPlans::class,
        SendPostOnboardingSurveyFeedbackEmail::class,
        //SyncChatbotLeadCreation::class
//        \App\Console\Commands\SyncWarehouseInventory::class,
//        \App\Console\Commands\SyncRetailInventory::class,
    ];
    /**
     * Define the application's command schedule.
     *
     * @param Schedule $schedule
     * @return void
     */
    protected function schedule(Schedule $schedule)
    {
        // those should run manually
        $schedule->command('pricing-plans:sync')->everySixHours();
        $schedule->command('post-onboarding:set-survey-feedback-email')->everySixHours();
        $schedule->command('chatbot:lead-capture')->hourly();

        $schedule->command('inventory:sync-warehouse')->hourly();
        $schedule->command('inventory:sync-retail')->hourly();

        $schedule->call(function () {
            event(new \App\Events\NotificationCheckEvent());
        })->everyMinute();

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

        $totalJobs = 15; // Total number of sync jobs
        $interval = 30 / $totalJobs; // Interval in minutes between each job

        $jobConfigs = [
            [1, 5], [6, 10], [11, 16], [17, 24], [25, 32], [33, 40], [41, 45],
            [46, 52], [53, 60], [61, 66], [67, 75], [76, 80], [81, 85], [86, 92], [92, 99]
        ];

        foreach ($jobConfigs as $index => $config) {
            $minutes = $index * $interval;
            $cronExpression = $minutes . ' */' . 30 . ' * * *';

            $schedule->command("inventory:sync {$config[0]} {$config[1]}")
                ->cron($cronExpression)
                ->withoutOverlapping();
        }

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
