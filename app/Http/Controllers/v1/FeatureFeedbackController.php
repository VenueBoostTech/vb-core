<?php
namespace App\Http\Controllers\v1;
use App\Http\Controllers\Controller;
use App\Models\Discount;
use App\Models\FeatureFeedback;
use App\Models\LoyaltyProgram;
use App\Models\Promotion;
use App\Models\VenuePauseHistory;
use App\Rules\FeatureFeedbackQuestion;
use App\Rules\ValidPauseReason;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use function response;

/**
 * @OA\Info(
 *   title="Feature Feedback API",
 *   version="1.0",
 *   description="This API allows use Feature Feedback Related API for Venue Boost"
 * )
 */

/**
 * @OA\Tag(
 *   name="Feature Feedback API",
 *   description="Operations related to Feature Feedback"
 * )
 */


class FeatureFeedbackController extends Controller
{

    public function store(Request $request): JsonResponse
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

        // Validate the request
        $validator = Validator::make($request->all(), [
            'feature_name' => 'required|string',
            'question_1' => ['required', new FeatureFeedbackQuestion($request->input('feature_name'))],
            'question_2' => ['required', new FeatureFeedbackQuestion($request->input('feature_name'))],
            'question_3' => ['required', new FeatureFeedbackQuestion($request->input('feature_name'))],
            'question_1_answer' => 'required|boolean',
            'question_2_answer' => 'required|boolean',
            'question_3_answer' => 'required|boolean',
            'additional_info_question_1' => 'nullable|string',
            'additional_info_question_2' => 'nullable|string',
            'additional_info_question_3' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()], 400);
        }

        // Create the feature feedback
        $featureFeedback = FeatureFeedback::create([
            'feature_name' => $request->input('feature_name'),
            'question_1' => $request->input('question_1'),
            'question_2' => $request->input('question_2'),
            'question_3' => $request->input('question_3'),
            'question_1_answer' => $request->input('question_1_answer'),
            'question_2_answer' => $request->input('question_2_answer'),
            'question_3_answer' => $request->input('question_3_answer'),
            'additional_info_1' => $request->input('additional_info_question_1'),
            'additional_info_2' => $request->input('additional_info_question_2'),
            'additional_info_3' => $request->input('additional_info_question_3'),
            'venue_id' => $venue->id
        ]);

        return response()->json(['message' => 'Feedback submitted successfully', 'data' => $featureFeedback], 200);
    }

}
