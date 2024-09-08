<?php

namespace App\Http\Controllers;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Foundation\Bus\DispatchesJobs;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Routing\Controller as BaseController;

/**
 * @OA\Info(
 *   title="Venue Boost API",
 *   version="1.0",
 *   description="This is a Venue Boost API v1"
 * )
 */
class Controller extends BaseController
{
    use AuthorizesRequests, DispatchesJobs, ValidatesRequests;
    public static $LIMIT_NUM_REFERRAL = 100;

    public static function generateReferralCode($restaurant_name): string
    {
        $name = null;
        if($restaurant_name) {
            $words = explode(" ", trim($restaurant_name));
            if (count($words) > 0) {
                $name = strtoupper($words[0]);
            }
        }

        $code = null;
        if ($name && $name != "") {
            $permitted_chars = '0123456789';
            $code = $name.substr(str_shuffle($permitted_chars), 0, 5);
        }
        else {
            $permitted_chars = '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ';
            $code = substr(str_shuffle($permitted_chars), 0, 7);
        }

        return $code;
    }

    public static function generateRandomString($length = 10): string
    {
        $permittedChars = '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz';
        return substr(str_shuffle($permittedChars), 0, $length);
    }
}
