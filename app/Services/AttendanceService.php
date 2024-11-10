<?php

namespace App\Services;

use App\Models\AttendanceRecord;
use App\Models\Employee;
use App\Models\Restaurant;
use Carbon\Carbon;

class AttendanceService
{
    public function checkIn(Employee $employee, Restaurant $venue, array $data): array
    {
        // Check if already checked in
        $existingRecord = AttendanceRecord::where('employee_id', $employee->id)
            ->whereDate('scanned_at', now())
            ->where('scan_type', 'check_in')
            ->first();

        if ($existingRecord) {
            return [
                'success' => false,
                'message' => 'Already checked in today'
            ];
        }

        // Create check-in record
        $record = AttendanceRecord::create([
            'employee_id' => $employee->id,
            'venue_id' => $venue->id,
            'scanned_at' => now(),
            'scan_type' => 'check_in',
            'scan_method' => $data['scan_method'],
            'nfc_card_id' => $data['nfc_card_id'] ?? null,
            'qr_code' => $data['qr_code'] ?? null,
            'latitude' => $data['latitude'],
            'longitude' => $data['longitude'],
            'is_within_geofence' => $this->isWithinGeofence($venue, $data['latitude'], $data['longitude'])
        ]);

        return [
            'success' => true,
            'message' => 'Checked in successfully',
            'record' => $record,
            'warnings' => $this->getWarnings($record)
        ];
    }

    public function checkOut(Employee $employee, Restaurant $venue, array $data): array
    {
        // Find check-in record
        $checkInRecord = AttendanceRecord::where('employee_id', $employee->id)
            ->whereDate('scanned_at', now())
            ->where('scan_type', 'check_in')
            ->latest()
            ->first();

        if (!$checkInRecord) {
            return [
                'success' => false,
                'message' => 'No check-in record found for today'
            ];
        }

        // Check if already checked out
        $existingCheckOut = AttendanceRecord::where('employee_id', $employee->id)
            ->whereDate('scanned_at', now())
            ->where('scan_type', 'check_out')
            ->exists();

        if ($existingCheckOut) {
            return [
                'success' => false,
                'message' => 'Already checked out today'
            ];
        }

        // Create check-out record
        $record = AttendanceRecord::create([
            'employee_id' => $employee->id,
            'venue_id' => $venue->id,
            'scanned_at' => now(),
            'scan_type' => 'check_out',
            'scan_method' => $data['scan_method'],
            'nfc_card_id' => $data['nfc_card_id'] ?? null,
            'qr_code' => $data['qr_code'] ?? null,
            'latitude' => $data['latitude'],
            'longitude' => $data['longitude'],
            'is_within_geofence' => $this->isWithinGeofence($venue, $data['latitude'], $data['longitude'])
        ]);

        return [
            'success' => true,
            'message' => 'Checked out successfully',
            'record' => $record,
            'warnings' => $this->getWarnings($record)
        ];
    }

    public function getAttendanceStatus(Employee $employee, ?Carbon $date = null): array
    {
        $date = $date ?? now();

        $lastRecord = AttendanceRecord::where('employee_id', $employee->id)
            ->whereDate('scanned_at', $date->toDateString())
            ->latest()
            ->first();

        $currentShift = $this->getCurrentShift($employee, $date);

        $isCheckedIn = $lastRecord && $lastRecord->scan_type === 'check_in';

        return [
            'is_checked_in' => $isCheckedIn,
            'last_record' => $lastRecord,
            'current_shift' => $currentShift,
            'can_check_in' => !$isCheckedIn,
            'can_check_out' => $isCheckedIn
        ];
    }

    private function getCurrentShift(Employee $employee, Carbon $date)
    {
        return $employee->shifts()
            ->whereRaw("FIND_IN_SET(?, days_of_week) > 0", [$date->dayOfWeek])
            ->first();
    }

    private function isWithinGeofence(Restaurant $venue, $latitude, $longitude): bool
    {
        // Implement your geofence logic here
        return true;
    }

    private function getWarnings(AttendanceRecord $record): array
    {
        $warnings = [];

        if (!$record->is_within_geofence) {
            $warnings[] = 'Location outside designated area';
        }

        // Add more warnings as needed

        return $warnings;
    }
}
