<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Kreait\Firebase\Factory;
use Kreait\Firebase\Messaging\CloudMessage;
use Kreait\Firebase\Messaging\Notification;

class FirebaseService
{
    protected $messaging;

    public function __construct()
    {
        $serviceAccountPath = storage_path('app/public/firebase_credentials.json');
        $factory = (new Factory)->withServiceAccount($serviceAccountPath);
        $this->messaging = $factory->createMessaging();
    }

    public function sendNotification($title, $body, $token, $data = [])
    {
        $message = CloudMessage::withTarget('token', $token)
            ->withNotification(Notification::create($title, $body))
            ->withData($data);

        try {
            $response = $this->messaging->send($message);
            return $response;
        } catch (\Throwable $e) {
            throw $e;
        }

    }

    public function validateToken($token)
    {
        $message = CloudMessage::withTarget('token', $token)
            ->withNotification(Notification::create('Test', 'This is a test message'))
            ->withData(['key' => 'value']);

        try {
            $response = $this->messaging->send($message, true);
            return ['valid' => true];
        } catch (\Throwable $e) {
            return ['valid' => false, 'error' => $e->getMessage()];
        }
    }

    public static function getFirebaseInstance($project)
    {
        $config = config("firebase.projects.$project");

        if (!$config || !isset($config['credentials']['file'])) {
            throw new \Exception("Firebase project [$project] configuration is missing or invalid.");
        }

        return (new Factory)->withServiceAccount($config['credentials']['file']);
    }
}

