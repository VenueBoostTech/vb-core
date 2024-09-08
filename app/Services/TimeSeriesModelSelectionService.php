<?php

namespace App\Services;

class TimeSeriesModelSelectionService
{
    public function detectSeasonality(array $occupancyData): bool
    {
        // Perform seasonality detection here
        // You can use autocorrelation analysis, periodogram analysis, or seasonal decomposition of time series to detect seasonality.

        // Example: Perform autocorrelation analysis using the ACF (Autocorrelation Function)
        $acf = $this->acf($occupancyData);


        // Find the highest significant lag in the ACF
        $maxLag = count($occupancyData); // Set the maximum lag to consider
        $significantLags = [];

        for ($lag = 0; $lag <= $maxLag-1; $lag++) {
            if (abs($acf[$lag]) > 2 / sqrt(count($occupancyData))) {
                $significantLags[] = $lag;
            }
        }

        // Check if any significant lags are present, indicating potential seasonality
        if (count($significantLags) > 0) {
            return true;
        }

        return false;
    }

    private function acf(array $data): array
    {

        $mean = array_sum($data) / count($data);
        $acf = [];

        for ($k = 0; $k < count($data); $k++) {
            $numerator = 0;
            $denominator = 0;

            for ($i = 0; $i < count($data) - $k; $i++) {
                $numerator += ($data[$i]['reservations_count'] - $mean) * ($data[$i + $k]['reservations_count'] - $mean);
                $denominator += pow($data[$i]['reservations_count'] - $mean, 2);
            }

            $acf[$k] = $numerator / $denominator;
        }

        return $acf;
    }
}
