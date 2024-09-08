<?php

namespace App\Console\Commands;

use App\Models\FineTuningJob;
use App\Models\Restaurant;
use App\Models\VenueIndustry;
use Illuminate\Console\Command;
use App\Models\PromptsResponses;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

class TrainOpenAIModelRetail extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'openai:admin-chatbot-train:retail';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Fine-tunes the OpenAI model for our admin chatbot in the food industry.';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {

        $foodIndustryId = VenueIndustry::where('short_name', 'food')->first()->id;

        $restaurants = Restaurant::where('venue_industry', $foodIndustryId)->get();

        foreach ($restaurants as $restaurant) {
            // Get the last fine-tuning job for this restaurant
            $lastJob = FineTuningJob::where('venue_id', $restaurant->id)
                ->orderBy('created_at', 'desc')
                ->first();

            $lastJobCreatedAt = $lastJob ? $lastJob->created_at : null;


            $promptsResponses = PromptsResponses::where('venue_id', $restaurant->id)
                ->whereNotNull('prompt')
                ->whereNotNull('response')
                ->when($lastJobCreatedAt, function ($query) use ($lastJobCreatedAt) {
                    $query->where('created_at', '>', $lastJobCreatedAt);
                })
                ->get();

            $formattedChats = [];

            foreach ($promptsResponses as $item) {
                // Ensure both prompt and response are not empty
                if (!empty($item->prompt) && !empty($item->response)) {
                    // Prepare the data in the desired JSON format
                    $chatData = [
                        "messages" => [
                            ["role" => "user", "content" => $item->prompt],
                            ["role" => "assistant", "content" => $item->response],
                        ],
                    ];

                    $formattedChats[] = json_encode($chatData);
                }
            }

            $timestamp = now()->format('Y-m-d-His'); // Get the current timestamp in the desired format
            $fileName = 'restaurant_' . $restaurant->id . '_fine_tune_admin_chat' . $timestamp . '.jsonl';
            // Use Laravel's Storage to save the file
            Storage::disk('local')->put($fileName, implode("\n", $formattedChats));

            // Send the file to OpenAI for fine-tuning using Laravel's HTTP client
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . env('OPENAI_API_KEY'),
            ])->attach('file', Storage::disk('local')->path($fileName))
                ->post('https://api.openai.com/v1/files', [
                    'purpose' => 'fine-tune',
                ]);

            // TODO contact OpenAI support to get the file ID
            //  curl https://api.openai.com/v1/fine_tuning/jobs \
            //  -H "Content-Type: application/json" \
            //  -H "Authorization: Bearer $OPENAI_API_KEY" \
            //  -d '{
            //  "training_file": "TRAINING_FILE_ID",
            //  "model": "gpt-3.5-turbo-0613"
            //  }'


            // Save the job ID and associate it with the restaurant in your database
            // generate random unique job id
            $jobId = uniqid();

            // Create a new record in the fine_tuning_jobs table
            FineTuningJob::create([
                'venue_id' => $restaurant->id,
                'job_id' => $jobId,
            ]);

        }

    }
}
