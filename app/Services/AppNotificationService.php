<?php

namespace App\Services;

use App\Models\Employee;
use App\Models\Notification;
use App\Models\NotificationType;
use App\Models\NotificationSetting;
use App\Models\User;
use App\Models\FirebaseUserToken;
use Kreait\Firebase\Messaging\CloudMessage;
use App\Services\FirebaseService;

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
            $notification = Notification::create([
                'employee_id' => $employee->id,
                'user_id' => $employee->user_id,
                'venue_id' => $employee->restaurant_id,
                'notification_type_id' => $notificationType->id,
                'text' => $message,
                'sent_at' => now(),
            ]);
            return $notification;
        }

        return null;
    }

    public function sendPushNotificationToUser(Employee $employee, string $notificationTypeName, string $message, array $data = [], Notification $notification = null)
    {

        $notificationType = NotificationType::where('name', $notificationTypeName)->first();

        if (!$notificationType) {
            throw new \Exception("Notification type not found: $notificationTypeName");
        }

        $notificationSetting = NotificationSetting::where('user_id', $employee->user_id)
            ->where('notification_type_id', $notificationType->id)
            ->first();

            $user = User::find($employee->user_id);
        if (!$notificationSetting || $notificationSetting->is_enabled) {
            if($user){

                $firebaseTokens = $user->firebaseTokens()
                        ->where('is_active', true)
                        ->pluck('firebase_token')
                        ->toArray();
                if (!empty($firebaseTokens)) {
                    
                    $firebase = FirebaseService::getFirebaseInstance('staffluent-app');
                    $messaging = $firebase->createMessaging();
                    
                    $data = array_merge($data, [
                        'notification_id' => (string)$notification->id,
                        'type' => $notificationType->name,
                        'venue_id' => (string)$employee->restaurant_id,
                        'click_action' => 'team_details',
                        'priority' => 'high'
                    ]);
                    $message = CloudMessage::new()
                            ->withNotification([
                                'title' => $notificationType->name,
                                'body' => $message,
                                'sound' => 'default'
                            ])
                            ->withData($data);

                            foreach ($firebaseTokens as $token) {
                                try {
                                    $messaging->send(
                                        $message->withChangedTarget('token', $token)
                                    );
                                } catch (\Exception $e) {
                                    // If token is invalid, mark it as inactive
                                    if (str_contains($e->getMessage(), 'invalid-registration-token')) {
                                        FirebaseUserToken::where('firebase_token', $token)
                                            ->update(['is_active' => false]);
                                    }
                                    \Log::error('Firebase notification failed: ' . $e->getMessage());
                                }
                            }
                }
            }
        }
        

    }
}

