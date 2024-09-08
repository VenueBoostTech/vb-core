<?php

namespace App\Http\Controllers\v1\AI\Admin;

use App\Models\FineTuningJob;
use App\Models\PromptsResponses;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use OpenAI\Laravel\Facades\OpenAI;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Validator;

class VBAssistantController extends Controller
{
    public function ask(Request $request): \Illuminate\Http\JsonResponse
    {

        if (!auth()->user()->restaurants->count()) {
            return response()->json(['error' => 'User not eligible for making this API call'], 400);
        }

        $apiCallVenueShortCode = request()->get('venue_short_code');
        if (!$apiCallVenueShortCode) {
            return response()->json(['error' => 'Venue short code is required'], 400);
        }

        $venue = auth()->user()->restaurants->where('short_code', $apiCallVenueShortCode)->first();
        if (!$venue) {
            return response()->json(['error' => 'Venue not found'], 404);
        }

        $validator = Validator::make($request->all(), [
            'question' => 'required|string',
            'ask_for' => [
                'required',
                'string',
                Rule::in([
                    'gym_description',
                    'additional_gym_information',
                    'additional_bowling_alley_information',
                    'bowling_alley_description',
                    'additional_golf_course_information',
                    'golf_course_description',
                    'updated_guest_interaction_content',
                    'updated_guest_access_content',
                    'updated_rental_unit_about_content',
                    'updated_rental_unit_space_content',
                    'guest_access_content',
                    'rental_unit_about_content',
                    'rental_unit_space_content',
                    'additional_store_settings_information',
                    'store_settings_description',
                    'long_product_description',
                    'short_product_description',
                    'product_category_description',
                    'loyalty_program_description',
                    'user_card_note',
                    'campaign_description',
                    'campaign_title',
                    'promotion_description',
                    'promotion_title',
                    'housekeeping_task_details',
                    'product_description',
                    'email_sms_template',
                ]),
            ],
        ]);


        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()], 400);
        }


        // Define the conversation with the system and user messages
        $conversation = [
            [
                'role' => 'system',
                'content' => 'You are a helpful assistant.',
            ],
            [
                'role' => 'user',
                'content' => $request->get('question'),
            ],
        ];


        // Get the "ask_for" value from your request
        $askFor = $request->get('ask_for');

        // Use if-else statements to replace the "content" based on "ask_for"
        if ($askFor === 'gym_description') {
            $conversation[0]['content'] = "I can help you generate descriptions for your gym.";
        } elseif ($askFor === 'additional_gym_information') {
            $conversation[0]['content'] = "I can assist with generating additional information for your gym.";
        } elseif ($askFor === 'additional_bowling_alley_information') {
            $conversation[0]['content'] = "I can assist you in generating additional information for your bowling alley.";
        } elseif ($askFor === 'bowling_alley_description') {
            $conversation[0]['content'] = "I can help you generate descriptions for your bowling alley.";
        } elseif ($askFor === 'additional_golf_course_information') {
            $conversation[0]['content'] = "I can help you with additional information for your golf course.";
        } elseif ($askFor === 'golf_course_description') {
            $conversation[0]['content'] = "I can help you generate descriptions for your golf course.";
        } elseif ($askFor === 'updated_guest_interaction_content') {
            $conversation[0]['content'] = "I can assist with content related to guest interaction for your rental units.";
        } elseif ($askFor === 'updated_guest_access_content') {
            $conversation[0]['content'] = "I can generate content related to guest access for your rental units.";
        } elseif ($askFor === 'updated_rental_unit_about_content') {
            $conversation[0]['content'] = "I can assist you with content regarding the about section of your rental units.";
        } elseif ($askFor === 'updated_rental_unit_space_content') {
            $conversation[0]['content'] = "I can help you with content related to the space of your rental units.";
        } elseif ($askFor === 'guest_access_content') {
            $conversation[0]['content'] = "I can assist with content related to guest access for your rental units.";
        } elseif ($askFor === 'rental_unit_about_content') {
            $conversation[0]['content'] = "I can generate content regarding the about section of your rental units.";
        } elseif ($askFor === 'rental_unit_space_content') {
            $conversation[0]['content'] = "I can help you with content related to the space of your rental units.";
        } elseif ($askFor === 'additional_store_settings_information') {
            $conversation[0]['content'] = "I can assist you in generating additional information for your store settings.";
        } elseif ($askFor === 'store_settings_description') {
            $conversation[0]['content'] = "I can help you manage store settings by generating descriptions.";
        } elseif ($askFor === 'long_product_description') {
            $conversation[0]['content'] = "I can generate long product descriptions for you.";
        } elseif ($askFor === 'short_product_description') {
            $conversation[0]['content'] = "I can help you create/update short product descriptions.";
        } elseif ($askFor === 'product_category_description') {
            $conversation[0]['content'] = "I can assist you in generating category descriptions for your products.";
        } elseif ($askFor === 'loyalty_program_description') {
            $conversation[0]['content'] = "I can help you create program descriptions for your loyalty programs.";
        } elseif ($askFor === 'user_card_note') {
            $conversation[0]['content'] = "I can assist you in creating user card notes.";
        } elseif ($askFor === 'campaign_description') {
            $conversation[0]['content'] = "I can generate campaign descriptions for you.";
        } elseif ($askFor === 'campaign_title') {
            $conversation[0]['content'] = "I can help you create campaign titles.";
        } elseif ($askFor === 'promotion_description') {
            $conversation[0]['content'] = "I can generate promotion descriptions for you.";
        } elseif ($askFor === 'promotion_title') {
            $conversation[0]['content'] = "I can create promotion titles for you.";
        } elseif ($askFor === 'housekeeping_task_details') {
            $conversation[0]['content'] = "I can help you generate details for housekeeping tasks.";
        } elseif ($askFor === 'product_description') {
            $conversation[0]['content'] = "I can assist you in creating/update product descriptions for your restaurant.";
        } elseif ($askFor === 'email_sms_template') {
            $conversation[0]['content'] = "I can help you generate content for your Email/SMS Templates.";
        }

        $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . env('OPENAI_API_KEY'),
            ])->post('https://api.openai.com/v1/chat/completions', [
            'model' => 'gpt-4',
            'messages' => $conversation,
            'temperature' => 1,
            'max_tokens' => 256,
            'top_p'  => 1,
            'frequency_penalty' => 0,
            'presence_penalty' => 0,
        ]);

        if ($response->successful()) {
            $data = $response->json();
            $assistantReply = $data['choices'][0]['message']['content'];

            // trim the response remove special characters

            $response = trim(preg_replace('/\s+/', ' ', $assistantReply));

            // Store the response in the database
            $promptsResponse = new PromptsResponses;
            $promptsResponse->prompt = $request->question;
            $promptsResponse->response = $response;
            $promptsResponse->for = 'vb-ai-assistant';
            $promptsResponse->industry = $venue->VenueType->definition === 'sport_entertainment' ? 'Sport & Entertainment' : $venue->VenueType->definition;
            $promptsResponse->venue_id = $venue->id;
            $promptsResponse->save();
            // Use $assistantReply as needed
            return response()->json(['assistantReply' => $assistantReply]);
        } else {
            // Handle the API request failure
            return response()->json(['error' => 'VB Assistant not responding. Try again in a bit.'], 500);
        }

    }

}
