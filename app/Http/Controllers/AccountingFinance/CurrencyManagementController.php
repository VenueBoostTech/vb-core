<?php

namespace App\Http\Controllers\AccountingFinance;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Validator;
use Illuminate\Http\JsonResponse;

class CurrencyManagementController extends Controller
{
    private $apiKey;
    private $baseUrl;

    public function __construct()
    {
        $this->apiKey = config('services.exchange_rate_api.key');
        $this->baseUrl = 'https://v6.exchangerate-api.com/v6/' . $this->apiKey;
    }

    public function getExchangeRates(): JsonResponse
    {
        $rates = Cache::remember('exchange_rates', 3600, function () {
            $response = Http::get($this->baseUrl . '/latest/USD');
            return $response->json()['conversion_rates'];
        });

        return response()->json(['exchange_rates' => $rates]);
    }

    public function convertCurrency(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'from' => 'required|string|size:3',
            'to' => 'required|string|size:3',
            'amount' => 'required|numeric|min:0',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $from = strtoupper($request->from);
        $to = strtoupper($request->to);
        $amount = $request->amount;

        $rates = Cache::remember('exchange_rates', 3600, function () {
            $response = Http::get($this->baseUrl . '/latest/USD');
            return $response->json()['conversion_rates'];
        });

        if (!isset($rates[$from]) || !isset($rates[$to])) {
            return response()->json(['message' => 'Invalid currency code'], 400);
        }

        $usdAmount = $amount / $rates[$from];
        $convertedAmount = $usdAmount * $rates[$to];

        return response()->json([
            'from' => $from,
            'to' => $to,
            'amount' => $amount,
            'converted_amount' => round($convertedAmount, 2),
            'exchange_rate' => $rates[$to] / $rates[$from],
        ]);
    }
}
