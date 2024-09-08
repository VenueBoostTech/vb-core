<?php

namespace App\Services;

use App\Models\Blog;
use App\Models\FirebaseUserToken;
use App\Models\NotificationConfiguration;
use App\Models\NotificationSchedule;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Kreait\Firebase\Factory;
use Kreait\Firebase\Messaging;

class NotificationService
{
    protected $firebaseService;

    public function __construct(FirebaseService $firebaseService)
    {
        $this->firebaseService = $firebaseService;
    }

    public function checkAndSendNotifications()
    {
        $configurations = NotificationConfiguration::where('is_active', true)->with('types')->get();

        foreach ($configurations as $config) {
            foreach ($config->types as $type) {
                if ($data = $this->isTriggerConditionMet($config, $type->blog_id)) {
                    $this->sendNotificationToAllUsersWithToken($data, $config, $type->blog_id);
                }
            }
        }
    }


    protected function isTriggerConditionMet($config, $blogId = null)
    {
        if ($config->notification_type == 'blog_read_count') {
            return $this->checkBlogReadCountCondition($config->trigger_value, $blogId);
        }

        // More conditions in the future

        return false;
    }

    protected function checkBlogReadCountCondition($triggerValue, $blogId = null)
    {
        if ($blogId) {
            // Check for a specific blog
            $blog = Blog::where('id', $blogId)->where('read_count', '>=', $triggerValue)->first();
            if ($blog) {
                return [
                    'title' => 'Read Count Threshold Met for a Specific Blog',
                    'body' => 'The read count threshold, as requested by you, has been reached for a specific VenueBoost blog: ' . $blog->name,
                    'blog' => $blog->name,
                    'trigger_value' => $triggerValue
                ];
            }
        } else {
            // Check for all blogs
            $blogs = Blog::where('read_count', '>=', $triggerValue)->get();
            if ($blogs->isNotEmpty()) {
                $blogNames = $blogs->pluck('name')->toArray();
                return [
                    'title' => 'Read Count Threshold Met for All Blogs',
                    'body' => 'The read count threshold, as requested by you, has been reached for all VenueBoost blogs: ' . implode(', ', $blogNames),
                    'blogs' => $blogNames,
                    'trigger_value' => $triggerValue
                ];
            }
        }

        return false;
    }

    protected function sendNotificationToAllUsersWithToken($data, $config, $blogId = null)
    {
        $userIds = FirebaseUserToken::pluck('user_id')->unique();

        foreach ($userIds as $userId) {
            // Check if notification has already been sent
            $alreadySent = NotificationSchedule::where('user_id', $userId)
                ->where('notification_configuration_id', $config->id)
                ->when($blogId, function ($query) use ($blogId) {
                    return $query->where('blog_id', $blogId);
                }, function ($query) {
                    return $query->whereNull('blog_id');
                })
                ->where('is_sent', true)
                ->exists();

            if (!$alreadySent) {
                // Retrieve all tokens for the user
                $tokens = FirebaseUserToken::where('user_id', $userId)->pluck('firebase_token')->all();

                // Send notification to all tokens
                foreach ($tokens as $token) {
                    $this->recordAndSendNotification($userId, $config, $data, $blogId, $token);
                }
            }
        }
    }

    protected function recordAndSendNotification($userId, $config, $data, $blogId, $token)
    {
        try {
            $notification = [
                'title' => $data['title'],
                'body' => $data['body']
            ];

            $response = $this->firebaseService->sendNotification($notification, [$token]);

            NotificationSchedule::create([
                'user_id' => $userId,
                'notification_configuration_id' => $config->id,
                'blog_id' => $blogId,
                'send_at' => now(),
                'is_sent' => true
            ]);
        } catch (\Exception $e) {
            Log::error("Failed to send notification: " . $e->getMessage());
        }
    }

}
