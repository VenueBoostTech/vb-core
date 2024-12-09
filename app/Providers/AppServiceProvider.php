<?php

namespace App\Providers;

use App\Services\FirebaseService;
use App\Services\NotificationService;
use Illuminate\Support\ServiceProvider;
use Illuminate\Database\Eloquent\Relations\Relation;


class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        $this->app->bind(NotificationService::class, function ($app) {
            return new NotificationService($app->make(FirebaseService::class));
        });
    }

    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
         Relation::morphMap([
                'milestone' => 'App\Models\Milestone',
            ]);
    }
}
