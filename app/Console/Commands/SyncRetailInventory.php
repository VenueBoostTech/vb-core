<?php

namespace App\Console\Commands;

use App\Http\Controllers\v1\ProductsController;
use Illuminate\Console\Command;

class SyncRetailInventory extends Command
{
    protected $signature = 'inventory:sync-retail';
    protected $description = 'Sync retail inventory based on warehouse changes';

    public function __construct()
    {
        parent::__construct();
    }

    public function handle()
    {
        $controller = new ProductsController();
        $controller->syncRetailInventory();
        $this->info('Retail inventory synced successfully');
    }
}

