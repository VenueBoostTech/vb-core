<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use App\Models\Schedule;
use App\Observers\LeaveRequestObserver;

class LeaveManagementServiceProvider extends ServiceProvider
{
    public function register()
    {
        $this->mergeConfigFrom(
            __DIR__.'/../../config/leave.php', 'leave'
        );
    }

    public function boot()
    {
        // Publish configuration
        $this->publishes([
            __DIR__.'/../../config/leave.php' => config_path('leave.php'),
        ], 'leave-config');

        // Register observers
        Schedule::observe(LeaveRequestObserver::class);
    }
}
