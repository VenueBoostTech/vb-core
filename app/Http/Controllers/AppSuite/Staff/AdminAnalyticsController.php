<?php

namespace App\Http\Controllers\AppSuite\Staff;

use App\Http\Controllers\Controller;
use App\Models\AppClient;
use App\Models\Service;
use App\Models\ServiceRequest;
use App\Services\VenueService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AdminAnalyticsController extends Controller
{
    protected VenueService $venueService;

    public function __construct(VenueService $venueService)
    {
        $this->venueService = $venueService;
    }

    public function services(Request $request): JsonResponse
    {
        $venue = $this->venueService->adminAuthCheck();
        if ($venue instanceof JsonResponse) return $venue;

        [$startDate, $endDate] = $this->getDateRange($request);

        try {
            // Base query for services
            $servicesQuery = Service::where('services.venue_id', $venue->id);
            $serviceRequestsQuery = ServiceRequest::where('service_requests.venue_id', $venue->id)
                ->whereBetween('created_at', [$startDate, $endDate]);

            // Calculate stats
            $stats = [
                'total_services' => $servicesQuery->count(),
                'active_clients' => AppClient::where('app_clients.venue_id', $venue->id)
                    ->whereHas('serviceRequests', function ($q) use ($startDate, $endDate) {
                        $q->whereBetween('created_at', [$startDate, $endDate]);
                    })->count(),
                'avg_duration' => round($serviceRequestsQuery
                    ->join('services', 'service_requests.service_id', '=', 'services.id')
                    ->avg('services.duration') ?? 0, 1),
                'growth_rate' => $this->calculateServiceGrowthRate($venue->id, $startDate, $endDate),
                'monthly_comparison' => $this->getMonthlyServiceComparison($venue->id)
            ];

            // Service Usage Over Time
            $serviceUsage = $serviceRequestsQuery
                ->selectRaw('DATE_FORMAT(created_at, "%Y-%m") as month, COUNT(*) as count')
                ->groupBy('month')
                ->orderBy('month')
                ->get()
                ->map(fn($item) => [
                    'month' => Carbon::createFromFormat('Y-m', $item->month)->format('M Y'),
                    'count' => $item->count
                ]);

            // Category Distribution
            $categoryDistribution = $servicesQuery
                ->with('category:id,name')
                ->get()
                ->groupBy('category_id')
                ->map(function($services, $categoryId) {
                    $category = $services->first()->category;
                    return [
                        'category' => $category ? $category->name : 'Uncategorized',
                        'count' => $services->count()
                    ];
                })
                ->values();

            // Revenue by Service
            $revenueByService = $serviceRequestsQuery
                ->join('services', 'service_requests.service_id', '=', 'services.id')
                ->selectRaw('services.name, COUNT(*) as bookings, SUM(services.base_price) as revenue')
                ->groupBy('services.name')
                ->orderByDesc('revenue')
                ->take(5)
                ->get();

            $charts = [
                'service_usage' => [
                    'labels' => $serviceUsage->pluck('month'),
                    'datasets' => [[
                        'label' => 'Service Bookings',
                        'data' => $serviceUsage->pluck('count'),
                        'backgroundColor' => '#3b82f6',
                        'borderColor' => '#3b82f6',
                        'tension' => 0.4
                    ]]
                ],
                'category_distribution' => [
                    'labels' => $categoryDistribution->pluck('category'),
                    'datasets' => [[
                        'data' => $categoryDistribution->pluck('count'),
                        'backgroundColor' => $this->getChartColors(count($categoryDistribution))
                    ]]
                ],
                'revenue_by_service' => [
                    'labels' => $revenueByService->pluck('name'),
                    'datasets' => [[
                        'label' => 'Revenue',
                        'data' => $revenueByService->pluck('revenue'),
                        'backgroundColor' => '#10b981'
                    ]]
                ]
            ];

            return response()->json([
                'stats' => $stats,
                'charts' => $charts
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to fetch analytics data: ' . $e->getMessage()
            ], 500);
        }
    }

    private function calculateServiceGrowthRate(int $venueId, Carbon $startDate, Carbon $endDate): float
    {
        $currentPeriod = ServiceRequest::where('service_requests.venue_id', $venueId)
            ->whereBetween('created_at', [$startDate, $endDate])
            ->count();

        $previousStartDate = (clone $startDate)->subMonth();
        $previousEndDate = (clone $endDate)->subMonth();

        $previousPeriod = ServiceRequest::where('service_requests.venue_id', $venueId)
            ->whereBetween('created_at', [$previousStartDate, $previousEndDate])
            ->count();

        if ($previousPeriod === 0) return 0;

        return round((($currentPeriod - $previousPeriod) / $previousPeriod) * 100, 1);
    }

    private function getMonthlyServiceComparison(int $venueId): array
    {
        $currentMonth = ServiceRequest::where('service_requests.venue_id', $venueId)
            ->whereMonth('created_at', now()->month)
            ->count();

        $lastMonth = ServiceRequest::where('service_requests.venue_id', $venueId)
            ->whereMonth('created_at', now()->subMonth()->month)
            ->count();

        $change = $lastMonth > 0
            ? round((($currentMonth - $lastMonth) / $lastMonth) * 100, 1)
            : 0;

        return [
            'current' => $currentMonth,
            'previous' => $lastMonth,
            'change' => $change
        ];
    }

    private function getChartColors(int $count): array
    {
        $baseColors = [
            '#3b82f6', '#10b981', '#6366f1', '#f59e0b', '#ef4444',
            '#8b5cf6', '#ec4899', '#14b8a6', '#f97316', '#06b6d4'
        ];

        return array_slice($baseColors, 0, $count);
    }

    private function getDateRange(Request $request): array
    {
        $endDate = now();
        $startDate = now()->startOfMonth();

        if ($request->filled(['date_from', 'date_to'])) {
            $startDate = Carbon::parse($request->date_from);
            $endDate = Carbon::parse($request->date_to);
        } else if ($request->filled('period')) {
            switch ($request->period) {
                case 'thisMonth':
                    $startDate = now()->startOfMonth();
                    break;
                case 'lastMonth':
                    $startDate = now()->subMonth()->startOfMonth();
                    $endDate = now()->subMonth()->endOfMonth();
                    break;
                case 'thisYear':
                    $startDate = now()->startOfYear();
                    break;
            }
        }

        return [$startDate, $endDate];
    }

    public function clients(Request $request): JsonResponse
    {
        $venue = $this->venueService->adminAuthCheck();
        if ($venue instanceof JsonResponse) return $venue;

        [$startDate, $endDate] = $this->getDateRange($request);

        try {
            // Base queries
            $clientsQuery = AppClient::where('app_clients.venue_id', $venue->id);
            $currentPeriodClients = clone $clientsQuery;

            // Stats calculations
            $totalClients = $clientsQuery->count();

            $newClients = $currentPeriodClients
                ->whereBetween('created_at', [$startDate, $endDate])
                ->count();

            // Active clients (those with service requests in period)
            $activeClients = $clientsQuery
                ->whereHas('serviceRequests', function($q) use ($startDate, $endDate) {
                    $q->whereBetween('created_at', [$startDate, $endDate]);
                })
                ->count();

            $stats = [
                'total_clients' => $totalClients,
                'new_clients' => $newClients,
                'active_clients' => $activeClients,
                'churn_rate' => $this->calculateChurnRate($venue->id, $startDate, $endDate),
                'retention_rate' => $this->calculateRetentionRate($venue->id, $startDate, $endDate),
                'monthly_comparison' => $this->getClientMonthlyComparison($venue->id)
            ];

            // Client Growth Trend
            $clientGrowth = $clientsQuery
                ->whereBetween('created_at', [$startDate, $endDate])
                ->selectRaw('DATE_FORMAT(created_at, "%Y-%m") as month, COUNT(*) as count')
                ->groupBy('month')
                ->orderBy('month')
                ->get()
                ->map(fn($item) => [
                    'month' => Carbon::createFromFormat('Y-m', $item->month)->format('M Y'),
                    'count' => $item->count
                ]);

            // Client Types Distribution
            $clientTypes = $clientsQuery
                ->selectRaw('type, COUNT(*) as count')
                ->groupBy('type')
                ->get()
                ->map(fn($item) => [
                    'type' => $item->type ?: 'Unknown',
                    'count' => $item->count
                ]);

            // Booking Frequency
            $bookingFrequency = DB::table('service_requests')
                ->select('client_id')
                ->where('service_requests.venue_id', $venue->id)
                ->whereBetween('created_at', [$startDate, $endDate])
                ->groupBy('client_id')
                ->selectRaw('COUNT(*) as booking_count, COUNT(DISTINCT client_id) as client_count')
                ->groupBy('booking_count')
                ->orderBy('booking_count')
                ->get()
                ->map(fn($item) => [
                    'frequency' => "{$item->booking_count} bookings",
                    'clients' => $item->client_count
                ]);

            // Client Acquisition Over Time
            $acquisitionTrend = $clientsQuery
                ->whereBetween('created_at', [$startDate, $endDate])
                ->selectRaw('DATE_FORMAT(created_at, "%Y-%m") as month, COUNT(*) as new_clients')
                ->groupBy('month')
                ->orderBy('month')
                ->get()
                ->map(fn($item) => [
                    'month' => Carbon::createFromFormat('Y-m', $item->month)->format('M Y'),
                    'count' => $item->new_clients
                ]);

            $charts = [
                'client_growth' => [
                    'labels' => $clientGrowth->pluck('month'),
                    'datasets' => [[
                        'label' => 'New Clients',
                        'data' => $clientGrowth->pluck('count'),
                        'backgroundColor' => '#3b82f6',
                        'borderColor' => '#3b82f6',
                        'tension' => 0.4
                    ]]
                ],
                'client_types' => [
                    'labels' => $clientTypes->pluck('type'),
                    'datasets' => [[
                        'data' => $clientTypes->pluck('count'),
                        'backgroundColor' => $this->getChartColors($clientTypes->count())
                    ]]
                ],
                'booking_frequency' => [
                    'labels' => $bookingFrequency->pluck('frequency'),
                    'datasets' => [[
                        'label' => 'Number of Clients',
                        'data' => $bookingFrequency->pluck('clients'),
                        'backgroundColor' => '#10b981'
                    ]]
                ],
                'acquisition_trend' => [
                    'labels' => $acquisitionTrend->pluck('month'),
                    'datasets' => [[
                        'label' => 'New Clients per Month',
                        'data' => $acquisitionTrend->pluck('count'),
                        'backgroundColor' => '#6366f1',
                        'borderColor' => '#6366f1',
                        'tension' => 0.4
                    ]]
                ]
            ];

            return response()->json([
                'stats' => $stats,
                'charts' => $charts
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to fetch client analytics: ' . $e->getMessage()
            ], 500);
        }
    }

    private function calculateChurnRate(int $venueId, Carbon $startDate, Carbon $endDate): float
    {
        // Get clients who were active in previous period
        $previousPeriodStart = (clone $startDate)->subMonth();
        $previousPeriodEnd = (clone $endDate)->subMonth();

        $previouslyActiveClients = AppClient::where('app_clients.venue_id', $venueId)
            ->whereHas('serviceRequests', function($q) use ($previousPeriodStart, $previousPeriodEnd) {
                $q->whereBetween('created_at', [$previousPeriodStart, $previousPeriodEnd]);
            })
            ->count();

        if ($previouslyActiveClients === 0) return 0;

        // Count how many of those clients are not active in current period
        $churnedClients = AppClient::where('app_clients.venue_id', $venueId)
            ->whereHas('serviceRequests', function($q) use ($previousPeriodStart, $previousPeriodEnd) {
                $q->whereBetween('created_at', [$previousPeriodStart, $previousPeriodEnd]);
            })
            ->whereDoesntHave('serviceRequests', function($q) use ($startDate, $endDate) {
                $q->whereBetween('created_at', [$startDate, $endDate]);
            })
            ->count();

        return round(($churnedClients / $previouslyActiveClients) * 100, 1);
    }

    private function calculateRetentionRate(int $venueId, Carbon $startDate, Carbon $endDate): float
    {
        return 100 - $this->calculateChurnRate($venueId, $startDate, $endDate);
    }

    private function getClientMonthlyComparison(int $venueId): array
    {
        $currentMonth = AppClient::where('app_clients.venue_id', $venueId)
            ->whereMonth('created_at', now()->month)
            ->count();

        $lastMonth = AppClient::where('app_clients.venue_id', $venueId)
            ->whereMonth('created_at', now()->subMonth()->month)
            ->count();

        $change = $lastMonth > 0
            ? round((($currentMonth - $lastMonth) / $lastMonth) * 100, 1)
            : 0;

        return [
            'current' => $currentMonth,
            'previous' => $lastMonth,
            'change' => $change
        ];
    }

// Add export functionality
    public function exportClients(Request $request): StreamedResponse
    {
        $venue = $this->venueService->adminAuthCheck();
        if ($venue instanceof JsonResponse) return $venue;

        [$startDate, $endDate] = $this->getDateRange($request);

        return response()->streamDownload(function () use ($venue, $startDate, $endDate) {
            $file = fopen('php://output', 'w');

            // Headers
            fputcsv($file, ['Client Analytics Report', '', '']);
            fputcsv($file, ['Period:', $startDate->format('Y-m-d'), $endDate->format('Y-m-d')]);
            fputcsv($file, ['', '', '']);

            // Stats
            $clients = AppClient::where('app_clients.venue_id', $venue->id)
                ->withCount(['serviceRequests' => function($q) use ($startDate, $endDate) {
                    $q->whereBetween('created_at', [$startDate, $endDate]);
                }])
                ->get();

            fputcsv($file, ['Client Details', '', '']);
            fputcsv($file, ['Name', 'Email', 'Bookings in Period']);

            foreach ($clients as $client) {
                fputcsv($file, [
                    $client->name,
                    $client->email,
                    $client->service_requests_count
                ]);
            }

            fclose($file);
        }, 'client-analytics.csv');
    }

    public function revenue(Request $request): JsonResponse
    {
        $venue = $this->venueService->adminAuthCheck();
        if ($venue instanceof JsonResponse) return $venue;

        [$startDate, $endDate] = $this->getDateRange($request);

        try {
            // Base query for revenue calculations
            $revenueQuery = ServiceRequest::where('service_requests.venue_id', $venue->id)
                ->join('services', 'service_requests.service_id', '=', 'services.id')
                ->whereBetween('service_requests.created_at', [$startDate, $endDate]);

            // Calculate total revenue for current period
            $totalRevenue = $revenueQuery->sum('services.base_price');

            // Stats calculations
            $stats = [
                'total_revenue' => $totalRevenue,
                'avg_order_value' => $this->calculateAverageOrderValue($venue->id, $startDate, $endDate),
                'revenue_growth' => $this->calculateRevenueGrowth($venue->id, $startDate, $endDate),
                'projected_revenue' => $this->calculateProjectedRevenue($venue->id, $totalRevenue, $startDate, $endDate),
                'monthly_comparison' => $this->getRevenueMonthlyComparison($venue->id)
            ];

            // Revenue Trends (Monthly breakdown)
            $revenueTrends = DB::table('service_requests')
                ->join('services', 'service_requests.service_id', '=', 'services.id')
                ->where('service_requests.venue_id', $venue->id)
                ->whereBetween('service_requests.created_at', [$startDate, $endDate])
                ->selectRaw('
                DATE_FORMAT(service_requests.created_at, "%Y-%m") as month,
                SUM(services.base_price) as revenue,
                COUNT(*) as bookings
            ')
                ->groupBy('month')
                ->orderBy('month')
                ->get()
                ->map(fn($item) => [
                    'month' => Carbon::createFromFormat('Y-m', $item->month)->format('M Y'),
                    'revenue' => $item->revenue,
                    'bookings' => $item->bookings
                ]);

            // Revenue by Service Category
            $revenueByCategory = DB::table('service_requests')
                ->join('services', 'service_requests.service_id', '=', 'services.id')
                ->join('service_categories', 'services.category_id', '=', 'service_categories.id')
                ->where('service_requests.venue_id', $venue->id)
                ->whereBetween('service_requests.created_at', [$startDate, $endDate])
                ->selectRaw('
                service_categories.name as category,
                SUM(services.base_price) as revenue,
                COUNT(*) as bookings
            ')
                ->groupBy('service_categories.name')
                ->orderByDesc('revenue')
                ->get();

            // Top Performing Services
            $topServices = DB::table('service_requests')
                ->join('services', 'service_requests.service_id', '=', 'services.id')
                ->where('service_requests.venue_id', $venue->id)
                ->whereBetween('service_requests.created_at', [$startDate, $endDate])
                ->selectRaw('
                services.name as service,
                SUM(services.base_price) as revenue,
                COUNT(*) as bookings,
                AVG(services.base_price) as avg_price
            ')
                ->groupBy('services.id', 'services.name')
                ->orderByDesc('revenue')
                ->limit(5)
                ->get()
                // include $venue
                ->map(function($service) use ($venue, $startDate) {
                    $previousRevenue = $this->getPreviousServiceRevenue($venue->id, $service->service, $startDate);
                    $growth = $previousRevenue > 0 ?
                        (($service->revenue - $previousRevenue) / $previousRevenue) * 100 : 0;

                    return [
                        'name' => $service->service,
                        'revenue' => $service->revenue,
                        'bookings' => $service->bookings,
                        'avg_price' => $service->avg_price,
                        'growth' => round($growth, 1)
                    ];
                });

            // Format chart data
            $charts = [
                'revenue_trends' => [
                    'labels' => $revenueTrends->pluck('month'),
                    'datasets' => [
                        [
                            'label' => 'Revenue',
                            'data' => $revenueTrends->pluck('revenue'),
                            'backgroundColor' => '#3b82f6',
                            'borderColor' => '#3b82f6',
                            'type' => 'line'
                        ],
                        [
                            'label' => 'Bookings',
                            'data' => $revenueTrends->pluck('bookings'),
                            'backgroundColor' => '#10b981',
                            'borderColor' => '#10b981',
                            'type' => 'bar'
                        ]
                    ]
                ],
                'revenue_by_category' => [
                    'labels' => $revenueByCategory->pluck('category'),
                    'datasets' => [[
                        'data' => $revenueByCategory->pluck('revenue'),
                        'backgroundColor' => $this->getChartColors($revenueByCategory->count())
                    ]]
                ],
                'top_services' => $topServices
            ];

            return response()->json([
                'stats' => $stats,
                'charts' => $charts
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to fetch revenue analytics: ' . $e->getMessage()
            ], 500);
        }
    }

    private function calculateAverageOrderValue(int $venueId, Carbon $startDate, Carbon $endDate): float
    {
        return round(DB::table('service_requests')
            ->join('services', 'service_requests.service_id', '=', 'services.id')
            ->where('service_requests.venue_id', $venueId)
            ->whereBetween('service_requests.created_at', [$startDate, $endDate])
            ->avg('services.base_price') ?? 0, 2);
    }

    private function calculateRevenueGrowth(int $venueId, Carbon $startDate, Carbon $endDate): float
    {
        $currentRevenue = DB::table('service_requests')
            ->join('services', 'service_requests.service_id', '=', 'services.id')
            ->where('service_requests.venue_id', $venueId)
            ->whereBetween('service_requests.created_at', [$startDate, $endDate])
            ->sum('services.base_price');

        $previousStartDate = (clone $startDate)->subMonth();
        $previousEndDate = (clone $endDate)->subMonth();

        $previousRevenue = DB::table('service_requests')
            ->join('services', 'service_requests.service_id', '=', 'services.id')
            ->where('service_requests.venue_id', $venueId)
            ->whereBetween('service_requests.created_at', [$previousStartDate, $previousEndDate])
            ->sum('services.base_price');

        return $previousRevenue > 0 ?
            round((($currentRevenue - $previousRevenue) / $previousRevenue) * 100, 1) : 0;
    }

    private function calculateProjectedRevenue(int $venueId, float $currentRevenue, Carbon $startDate, Carbon $endDate): float
    {
        // Calculate daily average
        $daysDifference = $startDate->diffInDays($endDate) ?: 1;
        $dailyAverage = $currentRevenue / $daysDifference;

        // Project for the rest of the month
        $daysRemaining = now()->endOfMonth()->diffInDays(now());
        $projectedAdditional = $dailyAverage * $daysRemaining;

        return round($currentRevenue + $projectedAdditional, 2);
    }

    private function getRevenueMonthlyComparison(int $venueId): array
    {
        $currentMonth = DB::table('service_requests')
            ->join('services', 'service_requests.service_id', '=', 'services.id')
            ->where('service_requests.venue_id', $venueId)
            ->whereMonth('service_requests.created_at', now()->month)
            ->sum('services.base_price');

        $lastMonth = DB::table('service_requests')
            ->join('services', 'service_requests.service_id', '=', 'services.id')
            ->where('service_requests.venue_id', $venueId)
            ->whereMonth('service_requests.created_at', now()->subMonth()->month)
            ->sum('services.base_price');

        $change = $lastMonth > 0 ?
            round((($currentMonth - $lastMonth) / $lastMonth) * 100, 1) : 0;

        return [
            'current' => $currentMonth,
            'previous' => $lastMonth,
            'change' => $change
        ];
    }

    private function getPreviousServiceRevenue(int $venueId, string $serviceName, Carbon $currentStartDate): float
    {
        $previousStartDate = (clone $currentStartDate)->subMonth();
        $previousEndDate = (clone $previousStartDate)->endOfMonth();

        return DB::table('service_requests')
            ->join('services', 'service_requests.service_id', '=', 'services.id')
            ->where('service_requests.venue_id', $venueId)
            ->where('services.name', $serviceName)
            ->whereBetween('service_requests.created_at', [$previousStartDate, $previousEndDate])
            ->sum('services.base_price');
    }

    public function exportRevenue(Request $request): StreamedResponse
    {
        $venue = $this->venueService->adminAuthCheck();
        if ($venue instanceof JsonResponse) return $venue;

        [$startDate, $endDate] = $this->getDateRange($request);

        return response()->streamDownload(function () use ($venue, $startDate, $endDate) {
            $file = fopen('php://output', 'w');

            // Headers
            fputcsv($file, ['Revenue Analytics Report']);
            fputcsv($file, ['Period:', $startDate->format('Y-m-d'), 'to', $endDate->format('Y-m-d')]);
            fputcsv($file, []);

            // Revenue by Service
            $revenueByService = DB::table('service_requests')
                ->join('services', 'service_requests.service_id', '=', 'services.id')
                ->where('service_requests.venue_id', $venue->id)
                ->whereBetween('service_requests.created_at', [$startDate, $endDate])
                ->selectRaw('
                services.name,
                COUNT(*) as bookings,
                SUM(services.base_price) as revenue,
                AVG(services.base_price) as avg_price
            ')
                ->groupBy('services.id', 'services.name')
                ->orderByDesc('revenue')
                ->get();

            fputcsv($file, ['Service Revenue Breakdown']);
            fputcsv($file, ['Service', 'Bookings', 'Total Revenue', 'Average Price']);

            foreach ($revenueByService as $service) {
                fputcsv($file, [
                    $service->name,
                    $service->bookings,
                    number_format($service->revenue, 2),
                    number_format($service->avg_price, 2)
                ]);
            }

            fclose($file);
        }, 'revenue-analytics.csv');
    }

    public function exportServices(Request $request): StreamedResponse
    {
        $venue = $this->venueService->adminAuthCheck();
        if ($venue instanceof JsonResponse) return $venue;

        $data = $this->services($request)->getData(true);

        return response()->streamDownload(function () use ($data) {
            $file = fopen('php://output', 'w');

            // Stats
            fputcsv($file, ['Metrics']);
            foreach ($data['stats'] as $key => $value) {
                fputcsv($file, [ucfirst(str_replace('_', ' ', $key)), $value]);
            }

            fputcsv($file, []); // Empty line

            // Service Usage
            fputcsv($file, ['Service Usage']);
            fputcsv($file, ['Month', 'Count']);
            foreach ($data['charts']['service_usage']['labels'] as $i => $month) {
                fputcsv($file, [$month, $data['charts']['service_usage']['datasets'][0]['data'][$i]]);
            }

            fputcsv($file, []); // Empty line

            // Category Distribution
            fputcsv($file, ['Category Distribution']);
            fputcsv($file, ['Category', 'Count']);
            foreach ($data['charts']['category_distribution']['labels'] as $i => $category) {
                fputcsv($file, [$category, $data['charts']['category_distribution']['datasets'][0]['data'][$i]]);
            }

            fclose($file);
        }, 'services-analytics.csv');
    }

}

