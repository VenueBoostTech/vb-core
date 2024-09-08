<?php

namespace App\Listeners;

use App\Events\NotificationCheckEvent;
use App\Http\Controllers\v2\NotificationController;
use App\Services\NotificationService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

class CheckNotificationConditionsListener
{
    /**
     * Create the event listener.
     *
     * @return void
     */
    public function __construct(NotificationService $notificationService)
    {
        $this->notificationService = $notificationService;
    }

    /**
     * Handle the event.
     *
     * @param  \App\Events\NotificationCheckEvent  $event
     * @return void
     */
    public function handle(NotificationCheckEvent $event)
    {
        // Instantiate the NotificationService and call the method to check and send notifications
        $this->notificationService->checkAndSendNotifications();
    }
}
