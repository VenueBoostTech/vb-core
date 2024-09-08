<?php

namespace App\Http\Controllers\v1\SNPlatform;
use App\Http\Controllers\Controller;

class ReadAndWriteController extends Controller
{
    public function rawEndUser()
    {

        // read if user exists in our SN Boost DB
        // if exists, return true
        // if doesn't exist, check if exists in SN Platform DB
        // if exists, create user in our SN Boost DB and return true
        // if doesn't exist, return false
    }

    public function rRestaurant()
    {

        // read if restaurant exists in our SN Boost DB
        // if exists, return true
        // if doesn't exist, return false
    }
}
