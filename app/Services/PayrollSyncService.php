<?php

namespace App\Services;

use App\Models\PayrollSyncLog;
use App\Services\PayrollIntegration\Contracts\PayrollServiceInterface;
use Illuminate\Support\Facades\Log;

class PayrollSyncService
{
    protected PayrollServiceInterface $provider;

    public function __construct(PayrollServiceInterface $payrollService)
    {
        $this->provider = $payrollService;
    }

    public function syncEmployeeData(array $employeeData): array
    {
        try {
            $formattedData = $this->formatEmployeeData($employeeData);
            $result = $this->provider->syncEmployeeData($formattedData);
            $this->logSync('employee', $formattedData, $result);
            return $result;
        } catch (\Exception $e) {
            $this->logSync('employee', $employeeData, null, $e->getMessage());
            throw $e;
        }
    }

    public function syncTimesheets(array $timesheets): array
    {
        try {
            $formattedData = $this->formatTimesheetData($timesheets);
            $result = $this->provider->syncTimesheet($formattedData);
            $this->logSync('timesheet', $formattedData, $result);
            return $result;
        } catch (\Exception $e) {
            $this->logSync('timesheet', $timesheets, null, $e->getMessage());
            throw $e;
        }
    }

    public function processPayout(array $payrollData): array
    {
        try {
            $formattedData = $this->formatPayrollData($payrollData);
            $result = $this->provider->processPayout($formattedData);
            $this->logSync('payout', $formattedData, $result);
            return $result;
        } catch (\Exception $e) {
            $this->logSync('payout', $payrollData, null, $e->getMessage());
            throw $e;
        }
    }

    public function syncAttendance(array $attendanceData): array
    {
        try {
            $formattedData = $this->formatAttendanceData($attendanceData);
            $result = $this->provider->syncAttendance($formattedData);
            $this->logSync('attendance', $formattedData, $result);
            return $result;
        } catch (\Exception $e) {
            $this->logSync('attendance', $attendanceData, null, $e->getMessage());
            throw $e;
        }
    }

    protected function formatEmployeeData(array $data): array
    {
        // Placeholder for data formatting
        return $data;
    }

    protected function formatTimesheetData(array $data): array
    {
        // Placeholder for data formatting
        return $data;
    }

    protected function formatPayrollData(array $data): array
    {
        // Placeholder for data formatting
        return $data;
    }

    protected function formatAttendanceData(array $data): array
    {
        // Placeholder for data formatting
        return $data;
    }

    protected function logSync(string $type, array $payload, ?array $response, ?string $error = null): void
    {
        PayrollSyncLog::create([
            'venue_id' => auth()->user()->venue_id,
            'provider' => get_class($this->provider),
            'sync_type' => $type,
            'payload' => $payload,
            'response' => $response,
            'status' => $error ? 'failed' : 'success',
            'error_message' => $error
        ]);

        if ($error) {
            Log::error("Payroll sync failed for {$type}", [
                'provider' => get_class($this->provider),
                'error' => $error,
                'payload' => $payload
            ]);
        }
    }
}
