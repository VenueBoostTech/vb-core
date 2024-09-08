<?php

namespace App\Http\Controllers\AccountingFinance;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\AccountingFinance\Forecast;
use App\Models\AccountingFinance\Company;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Http\JsonResponse;
use Carbon\Carbon;

class ForecastingController extends Controller
{
    /**
     * Display a listing of the regular forecasts.
     *
     * @return JsonResponse
     */
    public function index(): JsonResponse
    {
        $company = Company::where('user_id', Auth::user()->id)->first();

        if (!$company) {
            return response()->json(['message' => 'Company not found'], 404);
        }

        $forecasts = Forecast::where('company_id', $company->id)
            ->where('type', 'regular')
            ->get();
        return response()->json($forecasts);
    }

    /**
     * Generate a new forecast based on historical data.
     *
     * @param  Request  $request
     * @return JsonResponse
     */
    public function generateForecast(Request $request): JsonResponse
    {
        $data = $request->all();

        // Define the validation rules
        $rules = [
            'name' => 'required|string',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after:start_date',
            'historical_data' => 'required|array',
            'historical_data.*.date' => 'required|date',
            'historical_data.*.value' => 'required|numeric',
        ];

        // Validate the request data
        $validator = Validator::make($data, $rules);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        // Retrieve the company associated with the authenticated user
        $company = Company::where('user_id', Auth::user()->id)->first();

        if (!$company) {
            return response()->json(['message' => 'Company not found'], 404);
        }

        $forecastData = $this->linearForecast(
            $data['historical_data'],
            $data['start_date'],
            $data['end_date']
        );

        $forecast = new Forecast();
        $forecast->name = $data['name'];
        $forecast->company_id = $company->id;
        $forecast->start_date = $data['start_date'];
        $forecast->end_date = $data['end_date'];
        $forecast->data = $forecastData;
        $forecast->type = 'regular';
        $forecast->save();

        return response()->json($forecast, 201);
    }

    /**
     * Display a listing of the scenario forecasts.
     *
     * @return JsonResponse
     */
    public function getScenarios(): JsonResponse
    {
        $company = Company::where('user_id', Auth::user()->id)->first();

        if (!$company) {
            return response()->json(['message' => 'Company not found'], 404);
        }

        $scenarios = Forecast::where('company_id', $company->id)
            ->where('type', 'scenario')
            ->get();
        return response()->json($scenarios);
    }

    /**
     * Create a new scenario forecast.
     *
     * @param  Request  $request
     * @return JsonResponse
     */
    public function createScenario(Request $request): JsonResponse
    {
        $data = $request->all();

        // Define the validation rules
        $rules = [
            'name' => 'required|string',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after:start_date',
            'data' => 'required|array',
        ];

        // Validate the request data
        $validator = Validator::make($data, $rules);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        // Retrieve the company associated with the authenticated user
        $company = Company::where('user_id', Auth::user()->id)->first();

        if (!$company) {
            return response()->json(['message' => 'Company not found'], 404);
        }

        $scenario = new Forecast();
        $scenario->company_id = $company->id;
        $scenario->name = $data['name'];
        $scenario->start_date = $data['start_date'];
        $scenario->end_date = $data['end_date'];
        $scenario->data = $data['data'];
        $scenario->type = 'scenario';
        $scenario->save();

        return response()->json($scenario, 201);
    }

    /**
     * Perform linear forecasting based on historical data.
     *
     * @param  array  $historicalData
     * @param  string  $startDate
     * @param  string  $endDate
     * @return array
     */
    private function linearForecast(array $historicalData, string $startDate, string $endDate): array
    {
        // Sort historical data by date
        usort($historicalData, function($a, $b) {
            return strtotime($a['date']) - strtotime($b['date']);
        });

        // Calculate slope and intercept
        $n = count($historicalData);
        $sumX = $sumY = $sumXY = $sumX2 = 0;

        foreach ($historicalData as $index => $data) {
            $x = $index;
            $y = $data['value'];
            $sumX += $x;
            $sumY += $y;
            $sumXY += $x * $y;
            $sumX2 += $x * $x;
        }

        $slope = ($n * $sumXY - $sumX * $sumY) / ($n * $sumX2 - $sumX * $sumX);
        $intercept = ($sumY - $slope * $sumX) / $n;

        // Generate forecast
        $forecast = [];
        $currentDate = Carbon::parse($startDate);
        $endDate = Carbon::parse($endDate);
        $daysSinceStart = $currentDate->diffInDays(Carbon::parse($historicalData[0]['date']));

        while ($currentDate <= $endDate) {
            $forecastValue = $slope * $daysSinceStart + $intercept;
            $forecast[] = [
                'date' => $currentDate->toDateString(),
                'value' => round($forecastValue, 2)
            ];
            $currentDate->addDay();
            $daysSinceStart++;
        }

        return $forecast;
    }
}
