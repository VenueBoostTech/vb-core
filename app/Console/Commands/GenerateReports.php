<?php

namespace App\Console\Commands;

use App\Models\Brand;
use App\Services\ReportGenerationService;
use Carbon\Carbon;
use Illuminate\Console\Command;

class GenerateReports extends Command
{
    protected $signature = 'reports:generate {--brand=} {--year=} {--month=}';
    protected $description = 'Generate daily overview and sales in LC reports for brands';

    private $reportService;

    public function __construct(ReportGenerationService $reportService)
    {
        parent::__construct();
        $this->reportService = $reportService;
    }

    public function handle()
    {
        $brandId = $this->option('brand');
        $year = $this->option('year') ?? Carbon::now()->year;
        $month = $this->option('month') ?? Carbon::now()->month;

        $brands = $brandId ? Brand::where('id', $brandId)->get() : Brand::all();

        foreach ($brands as $brand) {
            $this->info("Generating reports for {$brand->title}");

            $dailyOverviewReport = $this->reportService->generateDailyOverviewReport($brand, $year, $month);
            $dailySalesInLCReport = $this->reportService->generateDailySalesInLCReport($brand, $year, $month);

            $this->info("Daily Overview Report generated for {$brand->title}");
            $this->info("Daily Sales in LC Report generated for {$brand->title}");
        }

        $this->info("All reports generated successfully.");

        return 0;
    }
}
