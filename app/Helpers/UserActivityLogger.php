<?php

namespace App\Helpers;

use App\Models\UserActivityLog;
use Illuminate\Support\Facades\Request;

class UserActivityLogger
{
    public static function log($userId, $action, $details = null)
    {
        UserActivityLog::create([
            'user_id' => $userId,
            'action' => $action,
            'os' => self::getOS(),
            'ip' => Request::ip(),
            'details' => $details,
        ]);
    }

    private static function getOS()
    {
        $userAgent = Request::header('User-Agent');

        if (preg_match('/linux/i', $userAgent)) {
            return 'Linux';
        } elseif (preg_match('/macintosh|mac os x/i', $userAgent)) {
            return 'Mac';
        } elseif (preg_match('/windows|win32/i', $userAgent)) {
            return 'Windows';
        }

        return 'Unknown OS';
    }
}
