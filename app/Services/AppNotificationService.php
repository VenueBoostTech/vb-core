<?php

namespace App\Services;

use App\Models\Employee;
use App\Models\Notification;
use App\Models\NotificationType;
use App\Models\NotificationSetting;

class AppNotificationService
{
    public function sendNotification(Employee $employee, string $notificationTypeName, string $message)
    {
        $notificationType = NotificationType::where('name', $notificationTypeName)->first();

        if (!$notificationType) {
            throw new \Exception("Notification type not found: $notificationTypeName");
        }

        $notificationSetting = NotificationSetting::where('user_id', $employee->user_id)
            ->where('notification_type_id', $notificationType->id)
            ->first();


        if (!$notificationSetting || $notificationSetting->is_enabled) {
            Notification::create([
                'employee_id' => $employee->id,
                'user_id' => $employee->user_id,
                'venue_id' => $employee->restaurant_id,
                'notification_type_id' => $notificationType->id,
                'text' => $message,
                'sent_at' => now(),
            ]);
        }
    }
}
