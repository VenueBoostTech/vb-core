<?php

namespace App\Services\PayrollIntegration\Contracts;
interface PayrollServiceInterface
{
    public function syncEmployeeData(array $data): array;

    public function syncTimesheet(array $timesheetData): array;

    public function processPayout(array $payrollData): array;

    public function syncAttendance(array $attendanceData): array;
}
