<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Http\Controllers\v3\Synchronization\AlphaSyncController; // Change to AlphaSyncController
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class BbAlphaSyncInventoryCommand extends Command
{
    protected $signature = 'bb-inventory-alpha:sync';
    protected $description = 'Sync ByBest Inventory with Alpha API';

    protected $syncController;

    public function __construct(AlphaSyncController $syncController) // Update constructor
    {
        parent::__construct();
        $this->syncController = $syncController;
    }

    public function handle()
    {
        Log::info('bb-inventory-alpha:sync command started.');
        $this->info('Starting bb inventory with alpha sync...');

        $venueId = 58;
        $syncDate = Carbon::now()->subWeek()->format('Y-m-d');
        $request = new Request([
            'sync_date' => $syncDate,
            'bypass' => 'true', // Enable bypass mode
            'venue_id' => $venueId, // Pass the venue ID for testing
        ]);


        // Sync SKU
        $this->info('Syncing SKUs...');
        $skuResponse = $this->syncController->syncSkuAlpha($request); // Updated method
        $this->info('SKU sync response: ' . json_encode($skuResponse->getData()));

        // Sync Price
        $this->info('Syncing prices...');
        $priceResponse = $this->syncController->syncPriceAlpha($request); // Updated method
        $this->info('Price sync response: ' . json_encode($priceResponse->getData()));

        // Sync Stock
        $this->info('Syncing stock...');
        $stockResponse = $this->syncController->syncStockAlpha($request); // Updated method
        $this->info('Stock sync response: ' . json_encode($stockResponse->getData()));

        // Calculate Stock for Single Products
        $this->info('Calculating stock for single products...');
        $singleStockRequest = new Request(
            [
                'sync_date' => $syncDate,
                'type' => 'single',
                'bypass' => 'true', // Enable bypass mode
                'venue_id' => $venueId, // Pass the venue ID for testing
            ]
        );
        $singleStockResponse = $this->syncController->calculateStock($singleStockRequest); // Updated method
        $this->info('Single product stock calculation response: ' . json_encode($singleStockResponse->getData()));

        // Calculate Stock for Variant Products
        $this->info('Calculating stock for variant products...');
        $variantStockRequest = new Request(
            ['sync_date' => $syncDate,
                'type' => 'variants',
                'bypass' => 'true', // Enable bypass mode
                'venue_id' => $venueId, // Pass the venue ID for testing
            ]);
        $variantStockResponse = $this->syncController->calculateStock($variantStockRequest); // Updated method
        $this->info('Variant product stock calculation response: ' . json_encode($variantStockResponse->getData()));

        $this->info('Bb inventory sync with alpha completed.');
    }
}
