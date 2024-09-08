<?php

namespace App\Console\Commands;

use App\Jobs\SendPostOnboardingSurveyFeedbackEmailJob;
use Carbon\Carbon;
use Illuminate\Console\Command;

class SendPostOnboardingSurveyFeedbackEmail extends Command
{

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'post-onboarding:set-survey-feedback-email';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Send post onboarding survey feedback email to all users who have completed onboarding 3-5 days after they have completed onboarding';

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

        // get all venues that at
        // venue_customized_experience.post_onboarding_survey_email_sent_at is nulla and potential_venue_leads.onboarded_completed_at is not null and potential_venue_leads.onboarded_completed_at is between 3 and 5 days ago
        $venues = \App\Models\VenueCustomizedExperience::whereNull('post_onboarding_survey_email_sent_at')
            ->whereHas('potentialVenueLead', function ($query) {
                $query->whereNotNull('onboarded_completed_at')
                    ->whereBetween('onboarded_completed_at', [Carbon::now()->subDays(5), Carbon::now()->subDays(3)]);
            })->get();

        // for each venue, send the email

        // dispatch(new SendPostOnboardingSurveyFeedbackEmailJob(39));
        foreach ($venues as $venue) {
            dispatch(new SendPostOnboardingSurveyFeedbackEmailJob($venue));
        }

    }
}
