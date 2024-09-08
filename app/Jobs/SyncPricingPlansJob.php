<?php
namespace App\Jobs;
use App\Http\Controllers\v1\PricingPlansController;
use App\Http\Controllers\v1\WaitlistController;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SyncPricingPlansJob implements ShouldQueue
{
    use InteractsWithQueue, Queueable, SerializesModels;
    public function __construct() { }
    public function handle()
    {
        PricingPlansController::syncStripeProducts();
    }
}
