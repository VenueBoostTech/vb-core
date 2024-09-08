<?php

namespace App\Console\Commands;

use App\Jobs\NotifyGuestsForWaitlistJob;
use App\Jobs\SyncPricingPlansJob;
use App\Models\Reservation;
use App\Models\Waitlist;
use Carbon\Carbon;
use Illuminate\Console\Command;

class SyncPricingPlans extends Command
{

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'pricing-plans:sync';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sync pricing plans from stripe into our database';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */

    public function handle()
    {
        dispatch(new SyncPricingPlansJob());
    }
}
