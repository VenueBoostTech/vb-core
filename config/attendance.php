<?php

return [
    'allow_outside_shift' => env('ATTENDANCE_ALLOW_OUTSIDE_SHIFT', false),
    'geofence_radius' => env('ATTENDANCE_GEOFENCE_RADIUS', 100), // meters
    'early_checkin_limit' => env('ATTENDANCE_EARLY_CHECKIN_LIMIT', 30), // minutes
    'late_checkout_limit' => env('ATTENDANCE_LATE_CHECKOUT_LIMIT', 30), // minutes
];
