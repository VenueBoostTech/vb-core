<?php
namespace App\Jobs;
use App\Mail\OnboardingVerifyEmail;
use App\Models\PotentialVenueLead;
use App\Services\MondayAutomationsService;
use Carbon\Carbon;
use Firebase\JWT\JWT;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;

class SyncChatbotLeadCreation implements ShouldQueue
{
    use InteractsWithQueue, Queueable, SerializesModels;
    protected $potentialUser;
    private MondayAutomationsService $mondayAutomationService;

    public function __construct($potentialUser)
    {
        $this->potentialUser = $potentialUser;
        $this->mondayAutomationService = new MondayAutomationsService();
    }
    public function handle()
    {
        $potentialVenueLeadNew = new PotentialVenueLead();
        $potentialVenueLeadNew->email = $this->potentialUser->email;
        $potentialVenueLeadNew->representative_first_name = $this->potentialUser->representative_first_name;
        $potentialVenueLeadNew->representative_last_name = $this->potentialUser->representative_last_name;
        $potentialVenueLeadNew->from_chatbot = true;

        $potentialVenueLeadNew->save();

        $created_at = Carbon::now();
        $expired_at = $created_at->addMinutes(240); // Add 240mins
        $serverName = 'VenueBoost';

        $data = [
            // 'iat' => $created_at->timestamp, // Issued at: time when the token was generated
            // 'nbf' => $created_at->timestamp, // Not before
            'iss' => $serverName, // Issuer
            'exp' => $expired_at->timestamp, // Expire,
            'id' => $potentialVenueLeadNew->id,
        ];

        $jwt_token = JWT::encode($data, env('JWT_SECRET'), 'HS256');
        $email_verify_link = 'https://venueboost.io' . "/onboarding/$jwt_token";

        Mail::to($potentialVenueLeadNew->email)->send(new OnboardingVerifyEmail($potentialVenueLeadNew->representative_first_name ?? null, $email_verify_link, false));

        try {
            $this->mondayAutomationService->automateLeadCreation($potentialVenueLeadNew);
        } catch (\Exception $e) {
            \Sentry\captureException($e);
            // do nothing
        }
    }
}
