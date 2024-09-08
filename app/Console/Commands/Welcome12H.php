<?php

namespace App\Console\Commands;

use App\Enums\EmailType;
use App\Jobs\Welcome12HJob;
use App\Models\Restaurant;

use Illuminate\Console\Command;

class Welcome12H extends Command
{

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'welcome:12-h';

    /**
     * The console command description.
     *
     * @var string
     */
    // 12 hours after registration we send this email to remind the venue
    // to complete as much as possible of the integrating in our platform process
    protected $description = 'Send email to remind the venue to complete as much as possible of the integrating in our platform process';

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
        $venues = Restaurant::where('created_at', '<=', now()->subHours(12))
            ->whereDoesntHave('emailConfigurations', function ($query) {
                $query->where('type', EmailType::WELCOME_12_H);
            })
            ->get();


        foreach ($venues as $venue) {
            dispatch(new Welcome12HJob($venue));
        }
    }
}
