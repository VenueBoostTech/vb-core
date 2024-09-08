<?php

namespace App\Console\Commands;

use App\Http\Controllers\v1\ProductsController;
use Illuminate\Console\Command;

class SyncWarehouseInventory extends Command
{
    protected $signature = 'inventory:sync-warehouse';
    protected $description = 'Sync warehouse inventory with external source';

    public function __construct()
    {
        parent::__construct();
    }

    public function handle()
    {
        $controller = new ProductsController();
        $controller->syncWarehouseInventory();
        $this->info('Warehouse inventory synced successfully');
    }
}

