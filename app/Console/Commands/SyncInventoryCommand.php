<?php

namespace App\Console\Commands;

use App\Http\Controllers\v3\InventorySyncController;
use Illuminate\Console\Command;

class SyncInventoryCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'inventory:sync {start_page} {end_page}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sync inventory for a range of pages';


    public function handle(InventorySyncController $controller)
    {
        $startPage = $this->argument('start_page');
        $endPage = $this->argument('end_page');

        $this->info("Starting sync for pages {$startPage} to {$endPage}");

        $result = $controller->syncRange($startPage, $endPage);

        $this->info("Sync completed. {$result['products_processed']} products processed.");
    }
}
