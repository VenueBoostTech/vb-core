<?php

namespace App\Http\Controllers\v1\ML;
use App\Http\Controllers\Controller;
use App\Models\ModelEvaluation;
use App\Models\ModelSummary;
use App\Models\OccupancyRateForecast;
use Illuminate\Support\Facades\DB;
use function response;

/**
 * @OA\Info(
 *   title="OccupancyRatesForecast API",
 *   version="1.0",
 *   description="This API allows use Occupancy Rates Forecast (Time Series Analysis) Related API for Venue Boost"
 * )
 */

/**
 * @OA\Tag(
 *   name="OccupancyRatesForecast",
 *   description="Operations related to OccupancyRatesForecast"
 * )
 */
class OccupancyRatesForecastController extends Controller
{
    /**
     * @OA\Get(
     *     path="/ml/tsa-occupancy-rates-forecast/model-evaluations",
     *     summary="Get Model Evaluations",
     *     tags={"OccupancyRatesForecast"},
     *     @OA\Response(
     *         response=200,
     *         description="Successful operation",
     *     ),
     * )
     */
    public function getModelEvaluations(): \Illuminate\Http\JsonResponse
    {
        $modelEvaluations = ModelEvaluation::all();

        return response()->json($modelEvaluations);
    }

    /**
     * @OA\Get(
     *     path="/ml/tsa-occupancy-rates-forecast/model-summaries",
     *     summary="Get Model Summaries",
     *     tags={"OccupancyRatesForecast"},
     *     @OA\Response(
     *         response=200,
     *         description="Successful operation",
     *     ),
     * )
     */
    public function getModelSummaries(): \Illuminate\Http\JsonResponse
    {
        $modelSummaries = ModelSummary::all();

        return response()->json($modelSummaries);
    }

    /**
     * @OA\Get(
     *     path="/ml/tsa-occupancy-rates-forecast/occupancy-rate-forecasts",
     *     summary="Get Occupancy Rate Forecasts",
     *     tags={"OccupancyRatesForecast"},
     *     @OA\Response(
     *         response=200,
     *         description="Successful operation",
     *     ),
     * )
     */
    public function getOccupancyRateForecasts(): \Illuminate\Http\JsonResponse
    {
        $occupancyRateForecasts = OccupancyRateForecast::all();

        return response()->json($occupancyRateForecasts);
    }

    /**
     * @OA\Get(
     *     path="/ml/tsa-occupancy-rates-forecast/prepared-occupancy-data",
     *     summary="Get Prepared Occupancy Data",
     *     tags={"OccupancyRatesForecast"},
     *     @OA\Response(
     *         response=200,
     *         description="Successful operation",
     *     ),
     * )
     */
    public function getPreparedOccupancyData(): \Illuminate\Http\JsonResponse
    {
        $preparedOccupancyData = DB::table('prepared_occupancy_data')->get();

        return response()->json($preparedOccupancyData);
    }
}
