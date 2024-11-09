<?php

return [
    'annual_days' => env('ANNUAL_LEAVE_DAYS', 30),
    'minimum_notice_days' => env('LEAVE_MINIMUM_NOTICE_DAYS', 2),
    'maximum_duration_days' => env('LEAVE_MAXIMUM_DURATION_DAYS', 30),
];
