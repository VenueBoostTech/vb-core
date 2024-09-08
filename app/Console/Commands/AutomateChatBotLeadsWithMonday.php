<?php

namespace App\Console\Commands;

use App\Jobs\SyncChatbotLeadCreation;
use App\Models\PotentialVenueLead;
use GuzzleHttp\Client;
use Illuminate\Console\Command;

class AutomateChatBotLeadsWithMonday extends Command
{

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'chatbot:lead-capture';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Automate chatbot leads with monday.com';

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

        $client = new Client();
        $baseUrl = env('CHATBOT_API_URL');
        $bearerToken = env('CHATBOT_DEVELOPER_ACCESS_TOKEN');

        // Set headers for the request
        $headers = [
            'Authorization' => 'Bearer ' . $bearerToken,
            'Content-Type' => 'application/json',
        ];


        // Get all segments
        $response = $client->request('GET', $baseUrl . '/users', [
            'headers' => $headers,
        ]);

        $users = json_decode($response->getBody()->getContents(), true);


        foreach ($users['data'] as $userData) {

            // Get all segments
            $responseUser = $client->request('GET', $baseUrl . '/users/' .$userData['id'], [
                'headers' => $headers,
            ]);

            $returnUser = json_decode($responseUser->getBody()->getContents(), true);

            // Proceed only if name is provided
            if (isset($returnUser['sessionAttributes']['default_name'])) {

                $returnName = $returnUser['sessionAttributes']['default_name'];
                $returnLastName= $returnUser['sessionAttributes']['Last_name'];
                $returnEmail = $returnUser['sessionAttributes']['default_email'];
                // Check if the email already exists in the database
                $exists = PotentialVenueLead::where('email', $returnEmail)->exists();


                // If email does not exist, dispatch the job
                if (!$exists) {

                    $potentialUser =  new \stdClass();
                    $potentialUser->representative_last_name = $returnLastName;
                    $potentialUser->representative_first_name = $returnName;
                    $potentialUser->email = $returnEmail;

                    dispatch(new SyncChatbotLeadCreation($potentialUser));
                }
            }
        }

    }
}
