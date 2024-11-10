<?php

namespace App\Observers;

use App\Models\Schedule;
use App\Models\NotificationType;
use App\Models\NotificationSetting;
use App\Models\Notification;
use App\Models\User;
use App\Models\FirebaseUserToken;
use Kreait\Firebase\Messaging\CloudMessage;

class LeaveRequestObserver
{
    public function created(Schedule $schedule)
    {
        if ($schedule->status === 'time_off') {
            $this->notifyManager($schedule);
        }
    }

    public function updated(Schedule $schedule)
    {
        if ($schedule->isDirty('status') && $schedule->status === 'time_off') {
            $this->notifyEmployee($schedule);
        }
    }

    private function notifyManager(Schedule $schedule)
    {
        try {
            // Get manager through employee relation
            $manager = $schedule->employee->manager;
            if (!$manager || !$manager->user_id) {
                return;
            }

            // Check notification type and settings
            $notificationType = NotificationType::where('name', 'leave_request_submitted')->first();
            if (!$notificationType) {
                return;
            }

            // Check if manager has enabled this notification type
            $notificationEnabled = NotificationSetting::where('user_id', $manager->user_id)
                ->where('notification_type_id', $notificationType->id)
                ->where('is_enabled', true)
                ->exists();

            $settingExists = NotificationSetting::where('user_id', $manager->user_id)
                ->where('notification_type_id', $notificationType->id)
                ->exists();

            if (!$settingExists || $notificationEnabled) {
                // Create database notification
                $notification = Notification::create([
                    'employee_id' => $manager->id,
                    'user_id' => $manager->user_id,
                    'venue_id' => $schedule->employee->restaurant_id,
                    'notification_type_id' => $notificationType->id,
                    'text' => "{$schedule->employee->name} has submitted a leave request from {$schedule->date->format('M d')} to {$schedule->end_date->format('M d')}",
                    'sent_at' => now()
                ]);

                // Get manager's Firebase tokens
                $firebaseTokens = FirebaseUserToken::where('user_id', $manager->user_id)
                    ->where('is_active', true)
                    ->pluck('firebase_token')
                    ->toArray();

                if (!empty($firebaseTokens)) {
                    $messaging = app('firebase.messaging');

                    // Prepare notification message
                    $message = CloudMessage::new()
                        ->withNotification([
                            'title' => 'New Leave Request',
                            'body' => "{$schedule->employee->name} has requested time off",
                            'sound' => 'default'
                        ])
                        ->withData([
                            'notification_id' => (string)$notification->id,
                            'type' => 'leave_request',
                            'schedule_id' => (string)$schedule->id,
                            'employee_id' => (string)$schedule->employee_id,
                            'click_action' => 'leave_request_details',
                            'priority' => 'high'
                        ]);

                    // Send to each token
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
        } catch (\Exception $e) {
            \Log::error('Leave request notification failed: ' . $e->getMessage());
        }
    }

    private function notifyEmployee(Schedule $schedule)
    {
        try {
            if (!$schedule->employee || !$schedule->employee->user_id) {
                return;
            }

            // Check notification type and settings
            $notificationType = NotificationType::where('name', 'leave_request_status_updated')->first();
            if (!$notificationType) {
                return;
            }

            // Check if employee has enabled this notification type
            $notificationEnabled = NotificationSetting::where('user_id', $schedule->employee->user_id)
                ->where('notification_type_id', $notificationType->id)
                ->where('is_enabled', true)
                ->exists();

            $settingExists = NotificationSetting::where('user_id', $schedule->employee->user_id)
                ->where('notification_type_id', $notificationType->id)
                ->exists();

            if (!$settingExists || $notificationEnabled) {
                // Create database notification
                $notification = Notification::create([
                    'employee_id' => $schedule->employee_id,
                    'user_id' => $schedule->employee->user_id,
                    'venue_id' => $schedule->employee->restaurant_id,
                    'notification_type_id' => $notificationType->id,
                    'text' => "Your leave request status has been updated",
                    'sent_at' => now()
                ]);

                // Get employee's Firebase tokens
                $firebaseTokens = FirebaseUserToken::where('user_id', $schedule->employee->user_id)
                    ->where('is_active', true)
                    ->pluck('firebase_token')
                    ->toArray();

                if (!empty($firebaseTokens)) {
                    $messaging = app('firebase.messaging');

                    // Prepare notification message
                    $message = CloudMessage::new()
                        ->withNotification([
                            'title' => 'Leave Request Update',
                            'body' => "Your leave request status has been updated",
                            'sound' => 'default'
                        ])
                        ->withData([
                            'notification_id' => (string)$notification->id,
                            'type' => 'leave_request_status',
                            'schedule_id' => (string)$schedule->id,
                            'status' => $schedule->status,
                            'click_action' => 'leave_request_details',
                            'priority' => 'high'
                        ]);

                    // Send to each token
                    foreach ($firebaseTokens as $token) {
                        try {
                            $messaging->send(
                                $message->withChangedTarget('token', $token)
                            );
                        } catch (\Exception $e) {
                            if (str_contains($e->getMessage(), 'invalid-registration-token')) {
                                FirebaseUserToken::where('firebase_token', $token)
                                    ->update(['is_active' => false]);
                            }
                            \Log::error('Firebase notification failed: ' . $e->getMessage());
                        }
                    }
                }
            }
        } catch (\Exception $e) {
            \Log::error('Leave request status notification failed: ' . $e->getMessage());
        }
    }
}
