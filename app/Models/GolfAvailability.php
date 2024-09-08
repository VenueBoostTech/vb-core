<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class GolfAvailability extends Model
{
    use HasFactory;

    protected $table = 'golf_availability';
    protected $fillable = [
        'golf_id',
        'day_of_week',
        'open_time',
        'close_time',
    ];

    public function golf(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Restaurant::class);
    }

    public function formattedOpeningHours(): string
    {
        // Get the formatted open and close times
        $openTime = date('h:i A', strtotime($this->open_time));
        $closeTime = date('h:i A', strtotime($this->close_time));

        // Check if the opening hours are the same for all days of the week
        $sameHoursForAllDays = OpeningHour::where([
                ['open_time', '=', $this->open_time],
                ['close_time', '=', $this->close_time]
            ])->count() == 7;

        if ($sameHoursForAllDays) {
            // If opening hours are the same for all days, return range of days (Mon - Sun)
            return "Mon - Sun {$openTime} - {$closeTime}";
        } else {
            // Check if Saturday and Sunday have different opening hours or are off
            $isSaturdayDifferent = OpeningHour::where('day_of_week', 6)->where('open_time', '!=', $this->open_time)->orWhere('close_time', '!=', $this->close_time)->exists();
            $isSundayDifferent = OpeningHour::where('day_of_week', 0)->where('open_time', '!=', $this->open_time)->orWhere('close_time', '!=', $this->close_time)->exists();

            // Format the day of the week accordingly
            $dayOfWeek = date('D', strtotime("Sunday +{$this->day_of_week} days"));
            if ($isSaturdayDifferent && $isSundayDifferent) {
                // If both Saturday and Sunday have different opening hours, return individual opening hours
                return "{$dayOfWeek} {$openTime} - {$closeTime}";
            } elseif ($isSaturdayDifferent) {
                // If only Saturday has different opening hours, indicate it separately
                return "Mon - Fri {$openTime} - {$closeTime}, Sat {$openTime} - {$closeTime}";
            } elseif ($isSundayDifferent) {
                // If only Sunday has different opening hours, indicate it separately
                return "Mon - Fri {$openTime} - {$closeTime}, Sun {$openTime} - {$closeTime}";
            } else {
                // Otherwise, return day of the week followed by open and close times
                return "{$dayOfWeek} {$openTime} - {$closeTime}";
            }
        }
    }
}
