<?php

namespace App\Http\Controllers\v1;

use App\Http\Controllers\Controller;
use App\Models\AutomaticReply;
use App\Models\Template;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

/**
 * @OA\Info(
 *   title="Template API",
 *   version="1.0",
 *   description="This API allows use Template Related API for Venue Boost"
 * )
 */

/**
 * @OA\Tag(
 *   name="Template API",
 *   description="Operations related to Template"
 * )
 */

class TemplateController extends Controller
{

    public function index(): JsonResponse
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

        $templates = Template::withTrashed()
            ->where('venue_id', $venue->id)
            ->whereNull('deleted_at')
            ->get();

        return response()->json($templates, 200);

    }
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
            'name' => 'required|string',
            'type' => 'required|in:SMS,Email',
            'description' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()], 400);
        }

        // Create the template
        $template = Template::create([
            'name' => $request->get('name'),
            'type' => $request->get('type'),
            'description' => $request->get('description'),
            'venue_id' => $venue->id
        ]);

        return response()->json(['message' => 'Template created successfully', 'data' => $template], 200);
    }

    public function destroy($id): JsonResponse
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

        $template = Template::where('id', $id)->where('venue_id', $venue->id)->first();

        if (!$template) {
            return response()->json(['message' => 'The requested template does not exist'], 404);
        }
        $template->delete();
        return response()->json(['message' => 'Successfully deleted the template'], 200);
    }

    public function update(Request $request): JsonResponse
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
            'id' => 'required|exists:templates,id',
            'name' => 'required|string',
            'type' => 'required|in:SMS,Email',
            'description' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()], 400);
        }

        $template = Template::where('id', $request->id)->where('venue_id', $venue->id)->first();


        if (!$template) {
            return response()->json(['error' => 'Templates not found'], 404);
        }

        // Update the template attributes
        $template->name = $request->name;
        $template->type = $request->type;
        $template->description = $request->description;
        $template->save();


        return response()->json(['message' => 'Template updated successfully', 'data' => $template], 200);
    }

    public function createAutomaticReply(Request $request): JsonResponse
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
            'reply_type' => 'required|in:pre-arrival,in-place,post-reservation,during-stay,post-stay,booking-confirmation',
            'template_id' => 'required|exists:templates,id',
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()], 400);
        }

        // Get the selected template
        $template = Template::find($request->input('template_id'));

        if (!$template) {
            return response()->json(['error' => 'Template not found'], 404);
        }

        // Extract tags from the template description using regular expressions
        preg_match_all('/\[([^\]]+)\]/', $template->description, $matches);

        // Serialize and store the extracted tags
        $serializedTags = serialize($matches[1]);

        $automaticReply = AutomaticReply::create([
            'reply_type' => $request->input('reply_type'),
            'template_id' => $request->input('template_id'),
            'tags' => $serializedTags,
            'venue_id' => $venue->id,
        ]);

        return response()->json([
            'message' => 'Automatic reply created successfully',
            'data' => $automaticReply,
        ], 201);
    }

    public function updateAutomaticReplyTemplate(Request $request): JsonResponse
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
            'id' => 'required|exists:automatic_replies,id',
            'template_id' => 'required|exists:templates,id',
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()], 400);
        }

        $automaticReply = AutomaticReply::find($request->input('id'));

        if (!$automaticReply) {
            return response()->json(['error' => 'Automatic reply not found'], 404);
        }

        // Get the selected template
        $template = Template::findOrFail($request->input('template_id'));

        // Extract tags from the template description using regular expressions
        preg_match_all('/\[([^\]]+)\]/', $template->description, $matches);

        // Serialize and store the extracted tags
        $serializedTags = serialize($matches[1]);

        $automaticReply->template_id = $request->input('template_id');
        $automaticReply->tags = $serializedTags;
        $automaticReply->save();

        return response()->json([
            'message' => 'Automatic reply template updated successfully',
            'data' => $automaticReply,
        ], 200);
    }

    public function listAutomaticReplies(): JsonResponse
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

        $automaticReplies = AutomaticReply::where('venue_id', $venue->id)->with('template')->get();

        return response()->json($automaticReplies, 200);
    }



}
