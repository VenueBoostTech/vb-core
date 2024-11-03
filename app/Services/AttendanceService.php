<?php

namespace App\Services;

use App\Models\AttendanceRecord;
use App\Models\Employee;
use App\Models\Restaurant;
use App\Models\Shift;
use Carbon\Carbon;
use Illuminate\Support\Str;

class AttendanceService
{
    public function recordAttendance(Employee $employee, Restaurant $venue, array $data)
    {
        $now = Carbon::now();
        $shift = $this->getCurrentShift($employee, $venue, $now);

        if (!$shift) {
            return ['message' => 'No shift scheduled for now.'];
        }

        $lastRecord = AttendanceRecord::where('employee_id', $employee->id)
            ->whereDate('scanned_at', $now->toDateString())
            ->latest()
            ->first();

        if (!$lastRecord || $lastRecord->scan_type === 'check_out') {
            return $this->checkIn($employee, $venue, $data, $shift, $now);
        } else {
            return $this->checkOut($lastRecord, $data, $shift, $now);
        }
    }

    private function checkIn(Employee $employee, Restaurant $venue, array $data, Shift $shift, Carbon $now)
    {
        $isLate = $now->format('H:i:s') > $shift->start_time;

        AttendanceRecord::create([
            'employee_id' => $employee->id,
            'venue_id' => $venue->id,
            'scanned_at' => $now,
            'scan_type' => 'check_in',
            'scan_method' => $data['scan_method'],
            'nfc_card_id' => $data['nfc_card_id'] ?? null,
            'qr_code' => $data['qr_code'] ?? null,
            'latitude' => $data['latitude'],
            'longitude' => $data['longitude'],
            'is_within_geofence' => $this->isWithinGeofence($venue, $data['latitude'], $data['longitude']),
        ]);

        return ['message' => 'Checked in successfully. ' . ($isLate ? 'You are late.' : 'You are on time.')];
    }

    private function checkOut(AttendanceRecord $lastRecord, array $data, Shift $shift, Carbon $now)
    {
        $isEarly = $now->format('H:i:s') < $shift->end_time;

        AttendanceRecord::create([
            'employee_id' => $lastRecord->employee_id,
            'venue_id' => $lastRecord->venue_id,
            'scanned_at' => $now,
            'scan_type' => 'check_out',
            'scan_method' => $data['scan_method'],
            'nfc_card_id' => $data['nfc_card_id'] ?? null,
            'qr_code' => $data['qr_code'] ?? null,
            'latitude' => $data['latitude'],
            'longitude' => $data['longitude'],
            'is_within_geofence' => $this->isWithinGeofence($lastRecord->venue, $data['latitude'], $data['longitude']),
        ]);

        return ['message' => 'Checked out successfully. ' . ($isEarly ? 'You are leaving early.' : '')];
    }

    private function getCurrentShift(Employee $employee, Restaurant $venue, Carbon $now)
    {
        return Shift::where('employee_id', $employee->id)
            ->where('venue_id', $venue->id)
            ->whereRaw("FIND_IN_SET(?, days_of_week) > 0", [$now->dayOfWeek])
            ->where('start_time', '<=', $now->format('H:i:s'))
            ->where('end_time', '>', $now->format('H:i:s'))
            ->first();
    }

    private function isWithinGeofence(Restaurant $venue, $latitude, $longitude)
    {
        return true;
    }

    public function generateQRCode(Restaurant $venue)
    {
        $qrCode = Str::random(20);
        $venue->update(['qr_code' => $qrCode]);
        return $qrCode;
    }
}
