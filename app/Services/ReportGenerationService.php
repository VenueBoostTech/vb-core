<?php

namespace App\Services;

use App\Models\Brand;
use App\Models\Order;
use App\Models\Product;
use App\Models\StoreInventory;
use App\Models\PhysicalStore;
use App\Models\DailyOverviewReport;
use App\Models\DailySalesLcReport;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class ReportGenerationService
{
    public function generateDailyOverviewReport(Brand $brand, $year, $month, $storeId = null)
    {
        $startDate = Carbon::create($year, $month, 1)->startOfMonth();
        $endDate = $startDate->copy()->endOfMonth();

        $report = [
            'brand' => $brand->title,
            'month' => $startDate->format('F Y'),
            'data' => []
        ];

        for ($date = $startDate; $date <= $endDate; $date->addDay()) {
            $currentDate = $date->format('Y-m-d');
            $lastYearDate = $date->copy()->subYear()->format('Y-m-d');

            $query = Order::whereHas('orderProducts.product', function ($query) use ($brand) {
                $query->where('brand_id', $brand->id);
            })->whereDate('created_at', $currentDate);

            $lastYearQuery = Order::whereHas('orderProducts.product', function ($query) use ($brand) {
                $query->where('brand_id', $brand->id);
            })->whereDate('created_at', $lastYearDate);

            if ($storeId) {
                $query->where('physical_store_id', $storeId);
                $lastYearQuery->where('physical_store_id', $storeId);
            }

            $currentYearSales = $query->sum('total_amount');
            $lastYearSales = $lastYearQuery->sum('total_amount');
            $index = $lastYearSales > 0 ? ($currentYearSales / $lastYearSales) : null;

            DailyOverviewReport::updateOrCreate(
                [
                    'brand_id' => $brand->id,
                    'store_id' => $storeId,
                    'report_date' => $currentDate,
                ],
                [
                    'year' => $year,
                    'month' => $month,
                    'current_year_sales' => $currentYearSales,
                    'last_year_sales' => $lastYearSales,
                    'index' => $index,
                ]
            );

            $report['data'][] = [
                'date' => $date->format('d'),
                'currentYear' => $currentYearSales,
                'lastYear' => $lastYearSales,
                'index' => $index
            ];
        }

        return $report;
    }

    public function generateDailySalesInLCReport(Brand $brand, $year, $month)
    {
        $startDate = Carbon::create($year, $month, 1)->startOfMonth();
        $endDate = $startDate->copy()->endOfMonth();

        $report = [
            'brand' => $brand->title,
            'month' => $startDate->format('F Y'),
            'data' => []
        ];

        for ($date = $startDate; $date <= $endDate; $date->addDay()) {
            $currentDate = $date->format('Y-m-d');

            $query = Order::whereHas('orderProducts.product', function ($query) use ($brand) {
                $query->where('brand_id', $brand->id);
            })->whereDate('orders.created_at', $currentDate);

            $dailySales = $query->sum('total_amount');
            $orderCount = $query->count();
            $productCount = $query->join('order_products', 'orders.id', '=', 'order_products.order_id')
                ->sum('order_products.product_quantity');

            $ppt = $orderCount > 0 ? ($productCount / $orderCount) : 0;
            $vpt = $orderCount > 0 ? ($dailySales / $orderCount) : 0;
            $ppp = $productCount > 0 ? ($dailySales / $productCount) : 0;

            $report['data'][] = [
                'day' => $date->format('d'),
                'dailySales' => $dailySales,
                'tickets' => $orderCount,
                'quantity' => $productCount,
                'ppt' => $ppt,
                'vpt' => $vpt,
                'ppp' => $ppp
            ];
        }

        $report['total'] = array_sum(array_column($report['data'], 'dailySales'));
        $report['totalTickets'] = array_sum(array_column($report['data'], 'tickets'));
        $report['totalQuantity'] = array_sum(array_column($report['data'], 'quantity'));
        $report['averagePPT'] = $report['totalTickets'] > 0 ? ($report['totalQuantity'] / $report['totalTickets']) : 0;
        $report['averageVPT'] = $report['totalTickets'] > 0 ? ($report['total'] / $report['totalTickets']) : 0;
        $report['averagePPP'] = $report['totalQuantity'] > 0 ? ($report['total'] / $report['totalQuantity']) : 0;

        return $report;
    }

    public function calculateInventory(Brand $brand)
    {
        return StoreInventory::whereHas('product', function ($query) use ($brand) {
            $query->where('brand_id', $brand->id);
        })->sum('quantity');
    }

    public function getInventoryByStore(Brand $brand)
    {
        $inventoryByStore = StoreInventory::whereHas('product', function ($query) use ($brand) {
            $query->where('brand_id', $brand->id);
        })
            ->select('physical_store_id', DB::raw('SUM(quantity) as total_stock'))
            ->groupBy('physical_store_id')
            ->get();

        $result = [];
        foreach ($inventoryByStore as $inventory) {
            $store = PhysicalStore::find($inventory->physical_store_id);
            $result[] = [
                'store_name' => $store ? $store->name : 'Unknown Store',
                'total_stock' => $inventory->total_stock
            ];
        }

        return $result;
    }

    public function generateInventoryTurnoverReport(Brand $brand, $startDate, $endDate)
    {
        $stores = PhysicalStore::whereHas('storeInventories.product', function ($query) use ($brand) {
            $query->where('brand_id', $brand->id);
        })->get();

        $report = [];

        foreach ($stores as $store) {
            $avgInventory = StoreInventory::whereHas('product', function ($query) use ($brand) {
                $query->where('brand_id', $brand->id);
            })
                ->where('physical_store_id', $store->id)
                ->whereBetween('created_at', [$startDate, $endDate])
                ->avg('quantity');

            $costOfGoodsSold = Order::whereHas('orderProducts.product', function ($query) use ($brand) {
                $query->where('brand_id', $brand->id);
            })
                ->where('physical_store_id', $store->id)
                ->whereBetween('created_at', [$startDate, $endDate])
                ->sum('total_amount');

            $inventoryTurnover = $avgInventory > 0 ? $costOfGoodsSold / $avgInventory : 0;
            $daysToSellInventory = $inventoryTurnover > 0 ? 365 / $inventoryTurnover : 0;

            $report[] = [
                'store_id' => $store->id,
                'store_name' => $store->name,
                'average_inventory' => round($avgInventory, 2),
                'cost_of_goods_sold' => round($costOfGoodsSold, 2),
                'inventory_turnover' => round($inventoryTurnover, 2),
                'days_to_sell_inventory' => round($daysToSellInventory, 0)
            ];
        }

        return $report;
    }
}
