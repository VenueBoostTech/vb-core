<?php

namespace App\Http\Controllers\v1;

use App\Http\Controllers\Controller;
use App\Models\EmployeeDevice;
use App\Models\EmployeeDeviceToken;
use Illuminate\Http\Request;

class FCMController extends Controller
{
    //

    public function updateToken(Request $request)
    {
        $request->validate([
            'device_type' => 'required|in:ios,android',
            'device_id' => 'required|string',
            'token' => 'required|string',
        ]);

        $employee = $request->user();

        $device = EmployeeDevice::updateOrCreate(
            ['employee_id' => $employee->id, 'device_id' => $request->device_id],
            ['device_type' => $request->device_type]
        );

        EmployeeDeviceToken::updateOrCreate(
            ['employee_device_id' => $device->id],
            ['token' => $request->token]
        );

        return response()->json(['message' => 'Token updated successfully']);
    }
}
