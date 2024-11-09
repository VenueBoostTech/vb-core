<?php

namespace App\Traits;

use Carbon\Carbon;
use App\Models\Schedule;

trait HasLeaveManagement
{
    public function hasLeaveOverlap(Carbon $startDate, Carbon $endDate): bool
    {
        return $this->leaveRequests()
            ->where(function($query) use ($startDate, $endDate) {
                $query->whereBetween('date', [$startDate, $endDate])
                    ->orWhereBetween('end_date', [$startDate, $endDate]);
            })
            ->exists();
    }

    public function calculateWorkingDays(Carbon $startDate, Carbon $endDate): int
    {
        $days = 0;
        $current = $startDate->copy();

        while ($current <= $endDate) {
            if (!$current->isWeekend()) {
                // Check if it's not a holiday (you can add holiday checking logic here)
                $days++;
            }
            $current->addDay();
        }

        return $days;
    }

    public function getRecentLeaveRequests(int $days = 30)
    {
        return $this->leaveRequests()
            ->with(['leaveType'])
            ->where('date', '>=', now()->subDays($days))
            ->orderBy('date', 'desc')
            ->get();
    }

    public function getPendingLeaveRequests()
    {
        return $this->leaveRequests()
            ->with(['leaveType'])
            ->where('date', '>=', now())
            ->orderBy('date', 'asc')
            ->get();
    }

    public function getLeaveHistory(int $year = null)
    {
        $query = $this->leaveRequests()
            ->with(['leaveType'])
            ->orderBy('date', 'desc');

        if ($year) {
            $query->whereYear('date', $year);
        }

        return $query->get()->groupBy(function($item) {
            return $item->date->format('F Y');
        });
    }
}
