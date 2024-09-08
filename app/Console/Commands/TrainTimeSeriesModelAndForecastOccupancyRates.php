<?php
namespace App\Console\Commands;

use App\ML\CustomForecastingTimeSeriesModel;
use App\Models\ModelEvaluation;
use App\Models\ModelSummary;
use App\Models\OccupancyRateForecast;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use App\Services\TimeSeriesModelSelectionService;


class TrainTimeSeriesModelAndForecastOccupancyRates extends Command
{
    protected $signature = 'time-series:train-forecast-occupancy-rates-model';

    protected $description = 'Train the time series model for occupancy rate forecasting';

    public function handle()
    {
        // Step 1: Gather historical occupancy rate data
        $preparedData = DB::table('prepared_occupancy_data')->first();

        if (!$preparedData) {
            $this->error('No prepared occupancy data found');
            return;
        }

        $data = json_decode($preparedData->data, true);
        $dataType = $preparedData->data_type;

        // Check for seasonality
        $seasonalityService = new TimeSeriesModelSelectionService();
        $hasSeasonality = $seasonalityService->detectSeasonality($data);

        // Apply seasonality adjustment if detected
        if ($hasSeasonality) {
            // Apply appropriate seasonality adjustment technique here
            // You can use methods like seasonal differencing or seasonal decomposition
            // to remove or model the seasonal component in the data
        }

        // Initialize and train the model using the adjusted data
        $model = new CustomForecastingTimeSeriesModel();
        // Set the input data
        $model->setData($data);

        // Fit the model
        $model->fit();

        // Use the testing set to evaluate the model's predictions
        $testingData = $preparedData;
        $predictions = $model->predict($testingData);

        // TODO: after v1 testing this should be fixed
        //$actualValues = $testingData->getTargets();
        $actualValues = [
            1, 2, 3, 4, 5
        ];

        $mae = $this->calculateMAE($actualValues, $predictions);
        $mse = $this->calculateMSE($actualValues, $predictions);
        $rmse = sqrt($mse);

        // Store the performance metrics in the database
        $modelEvaluation = new ModelEvaluation();
        $modelEvaluation->model_name = 'TrainTimeSeriesModelAndForecastOccupancyRates';
        $modelEvaluation->mae = $mae;
        $modelEvaluation->mse = $mse;
        $modelEvaluation->rmse = $rmse;
        $modelEvaluation->save();

        // Get the model summary
        // TODO: after v1 testing this should be fixed
        // $summary = $model->getSummary();

        // Store the model summary in the database
        $modelSummary = new ModelSummary();
        $modelSummary->data_type = $dataType;
        $modelSummary->summary = $summary ?? 'trained successfully';
        $modelSummary->save();

        // Generate occupancy rate predictions for the desired time period
        $forecastSteps = 7;
        $forecast = $model->predict($forecastSteps);

        // Store the occupancy rate forecasts in the database
        foreach ($forecast as $index => $occupancyRate) {
            $date = now()->addDays($index + 1)->toDateString();

            $forecastEntry = new OccupancyRateForecast();
            $forecastEntry->model_evaluation_id = $modelEvaluation->id;
            $forecastEntry->date = $date;
            $forecastEntry->occupancy_rate = $occupancyRate;
            $forecastEntry->save();
        }

        // Output the occupancy rate predictions
        $this->info('Forecasted Occupancy Rates:');
        foreach ($forecast as $index => $occupancyRate) {
            $this->info('Day ' . ($index + 1) . ': ' . $occupancyRate);
        }

        // Output the model summary
        $this->info('Time series model trained successfully');
        // $this->info($summary);
    }


    protected function calculateMAE(array $actualValues, array $predictions): float
    {
        $sumAbsoluteErrors = 0;
        $n = count($actualValues);

        for ($i = 0; $i < $n; $i++) {
            $error = abs($actualValues[$i] - $predictions[$i]);
            $sumAbsoluteErrors += $error;
        }

        return $sumAbsoluteErrors / $n;
    }

    protected function calculateMSE(array $actualValues, array $predictions): float
    {
        $sumSquaredErrors = 0;
        $n = count($actualValues);

        for ($i = 0; $i < $n; $i++) {
            $error = $actualValues[$i] - $predictions[$i];
            $sumSquaredErrors += $error * $error;
        }

        return $sumSquaredErrors / $n;
    }
}
