<?php

namespace App\Http\Controllers\v2;

use App\Enums\FeatureNaming;
use App\Http\Controllers\Controller;
use App\Mail\HygieneCheckAssignEmail;
use App\Models\ChecklistItem;
use App\Models\Feature;
use App\Models\HygieneCheck;
use App\Models\HygieneInspection;
use App\Models\HygieneStandardVendor;
use App\Rules\NumericRangeRule;
use App\Services\ApiUsageLogger;

use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Validator;


class HygieneStandardController extends Controller
{

    protected ApiUsageLogger $apiUsageLogger;

    public function __construct(ApiUsageLogger $apiUsageLogger)
    {
        $this->apiUsageLogger = $apiUsageLogger;
    }


    public function createCheck(Request $request): \Illuminate\Http\JsonResponse
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
            'item' => 'required|string',
            // validate email
            'assigned_to' => 'nullable|email',
            'remind_hours_before' => ['nullable', 'integer', new NumericRangeRule()],
            'check_date' => 'required|date',
            'type' => 'nullable|string',
            'checklist_items' => 'required|array',
            'checklist_items.*.item' => 'required|string'
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()], 400);
        }

        $hygieneCheck = HygieneCheck::create([
            'item' => $request['item'],
            'assigned_to' => $request['assigned_to'],
            'remind_hours_before' => $request['remind_hours_before'],
            'check_date' => $request['check_date'],
            'type' => $request['type'],
            'venue_id' => $venue->id
        ]);

        // Add checklist items
        foreach ($request['checklist_items'] as $checklistItem) {
            $hygieneCheck->checklistItems()->create([
                'item' => $checklistItem['item']
            ]);
        }

        $hygieneCheck->load('checklistItems');

        // if there is assigned_to, send email
        if ($request['assigned_to']) {
            Mail::to($request['assigned_to'])->send(new HygieneCheckAssignEmail($hygieneCheck, $venue));
        }

        // log api usage inside a try so that it doesn't break the api call
        try {
            $featureId = Feature::where('name', FeatureNaming::hygiene_standard)->where('feature_category', $venue->venueType->definition)->first()->id;
            $subFeatureId = DB::table('sub_features')->where('name', 'Hygiene Checks')->where('feature_id', $featureId)->first()->id;
            $this->apiUsageLogger->log($featureId, $venue->id, 'Create Hygiene Check - POST', $subFeatureId);
        } catch (\Exception $e) {
            // do nothing
        }

        return response()->json([
            'message' => 'Hygiene check created successfully',
            'data' => $hygieneCheck->load('checklistItems')
        ]);
    }

    public function editCheck(Request $request): \Illuminate\Http\JsonResponse
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
            'id' => 'required|exists:hygiene_checks,id',
            'item' => 'required|string',
            'assigned_to' => 'nullable|email',
            'remind_hours_before' => ['nullable', 'integer', new NumericRangeRule()],
            'check_date' => 'required|date',
            'type' => 'nullable|string',
            'status' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()], 400);
        }

        $hygieneCheck = HygieneCheck::where('id', $request['id'])->where('venue_id', $venue->id)->first();
        if (!$hygieneCheck) {
            return response()->json(['error' => 'Hygiene check not found'], 404);
        }

        $previousAssignedTo = $hygieneCheck->assigned_to;

        $hygieneCheck->update([
            'item' => $request['item'],
            'assigned_to' => $request['assigned_to'],
            'remind_hours_before' => $request['remind_hours_before'],
            'check_date' => $request['check_date'],
            'type' => $request['type'],
            'status' => $request['status']
        ]);

        // Retrieve current checklist items from the database
        $currentChecklistItems = $hygieneCheck->checklistItems()->get();

// Incoming checklist items from the request
        $incomingChecklistItems = collect($request->input('checklist_items', []));

// Update and add new checklist items
        $incomingChecklistItems->each(function ($item) use ($hygieneCheck) {
            $checklistItem = $hygieneCheck->checklistItems()->updateOrCreate(
                ['id' => $item['id'] ?? null], // If ID is provided, update; otherwise, create new
                ['item' => $item['item']]
            );
        });

        // Delete removed checklist items
        $currentChecklistItemIds = $currentChecklistItems->pluck('id');
        $incomingChecklistItemIds = $incomingChecklistItems->pluck('id');
        $checklistItemsToDelete = $currentChecklistItemIds->diff($incomingChecklistItemIds);
        if ($checklistItemsToDelete->isNotEmpty()) {
            $hygieneCheck->checklistItems()->whereIn('id', $checklistItemsToDelete)->delete();
        }

        // Check if assigned email is new or changed, and send email
        if ($request['assigned_to'] && $request['assigned_to'] != $previousAssignedTo) {
            Mail::to($request['assigned_to'])->send(new HygieneCheckAssignEmail($hygieneCheck, $hygieneCheck->venue));
        }

        // log api usage inside a try so that it doesn't break the api call
        try {
            $featureId = Feature::where('name', FeatureNaming::hygiene_standard)->where('feature_category', $venue->venueType->definition)->first()->id;
            $subFeatureId = DB::table('sub_features')->where('name', 'Hygiene Checks')->where('feature_id', $featureId)->first()->id;
            $this->apiUsageLogger->log($featureId, $venue->id, 'Update Hygiene Check - PUT', $subFeatureId);
        } catch (\Exception $e) {
            // do nothing
        }

        return response()->json([
            'message' => 'Hygiene check updated successfully',
            'data' => $hygieneCheck->refresh()->load('checklistItems')
        ]);
    }

    public function deleteCheck($id): \Illuminate\Http\JsonResponse
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

        $hygieneCheck = HygieneCheck::where('id', $id)->where('venue_id', $venue->id)->first();


        if (!$hygieneCheck) {
            return response()->json(['error' => 'Hygiene check not found'], 404);
        }

        $hygieneCheck->delete();

        // log api usage inside a try so that it doesn't break the api call
        try {
            $featureId = Feature::where('name', FeatureNaming::hygiene_standard)->where('feature_category', $venue->venueType->definition)->first()->id;
            $subFeatureId = DB::table('sub_features')->where('name', 'Hygiene Checks')->where('feature_id', $featureId)->first()->id;
            $this->apiUsageLogger->log($featureId, $venue->id, 'Delete Hygiene Check - DELETE', $subFeatureId);
        } catch (\Exception $e) {
            // do nothing
        }

        return response()->json(['message' => 'Hygiene check deleted successfully']);
    }

    public function listChecks(Request $request): \Illuminate\Http\JsonResponse
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

        $hygieneChecks = HygieneCheck::where('venue_id', $venue->id)
            ->with('checklistItems')
            ->orderBy('id', 'desc')
            ->get();

        // log api usage inside a try so that it doesn't break the api call
        try {
            $featureId = Feature::where('name', FeatureNaming::hygiene_standard)->where('feature_category', $venue->venueType->definition)->first()->id;
            $subFeatureId = DB::table('sub_features')->where('name', 'Hygiene Checks')->where('feature_id', $featureId)->first()->id;
            $this->apiUsageLogger->log($featureId, $venue->id, 'Retrieve Hygiene Checks - GET', $subFeatureId);
        } catch (\Exception $e) {
            // do nothing
        }

        return response()->json([
            'message' => 'Hygiene checks retrieved successfully',
            'data' => $hygieneChecks
        ]);
    }

    public function general(Request $request): \Illuminate\Http\JsonResponse
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

        $hygieneChecks = HygieneCheck::where('venue_id', $venue->id)
            ->with('checklistItems')
            ->orderBy('id', 'desc')
            ->get();

        // get next upcoming check
        // should be in the future from now
        $nextUpcomingCheck = $hygieneChecks->filter(function ($check) {
            return $check->check_date > Carbon::now()->toDateString();
        })->first();

        // get the date in human readable format and hour
        $nextUpcomingCheckDate = $nextUpcomingCheck ? Carbon::parse($nextUpcomingCheck->check_date)->format('Y-m-d H:i') : null;

        // get next upcoming inspection

        // do the query with model for next upcoming inspection
        $nextUpcomingInspection = HygieneInspection::where('venue_id', $venue->id)
            ->where('inspection_date', '>', Carbon::now()->toDateString())
            ->orderBy('inspection_date', 'asc')
            ->first();


        // get the date in human readable format and hour
        $nextUpcomingInspectionDate = $nextUpcomingInspection ? Carbon::parse($nextUpcomingInspection->inspection_date)->format('Y-m-d H:i') : null;

        $beforeNowInspection = HygieneInspection::where('venue_id', $venue->id)
            ->where('inspection_date', '<', Carbon::now()->toDateString())
            ->orderBy('inspection_date', 'desc')
            ->first();

        // get status of the inspection
        // and make the first letter uppercase
        $inspectionStatus = $beforeNowInspection ? ucfirst($beforeNowInspection->inspection_result_status) : null;



        // log api usage inside a try so that it doesn't break the api call
        try {
            $featureId = Feature::where('name', FeatureNaming::hygiene_standard)->where('feature_category', $venue->venueType->definition)->first()->id;
            $subFeatureId = DB::table('sub_features')->where('name', 'Hygiene Checks')->where('feature_id', $featureId)->first()->id;
            $this->apiUsageLogger->log($featureId, $venue->id, 'Retrieve Hygiene Checks - GET', $subFeatureId);
        } catch (\Exception $e) {
            // do nothing
        }

        return response()->json([
            'message' => 'Hygiene checks retrieved successfully',
            'data' => $hygieneChecks,
            'next_upcoming_check' => $nextUpcomingCheckDate,
            'next_upcoming_inspection' => $nextUpcomingInspectionDate,
            'inspection_status' => $inspectionStatus
        ]);
    }

    public function createVendor(Request $request): \Illuminate\Http\JsonResponse
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
            'name' => 'required|string',
            'type' => 'required|string',
            'contact_name' => 'nullable|string',
            'contact_email' => 'nullable|email',
            'contact_phone' => 'nullable|string',
            'address' => 'nullable|string',
            'hygiene_rating' => 'nullable|string',
            'compliance_certified' => 'nullable|boolean',
            'certification_details' => 'nullable|string',
            'notes' => 'nullable|string'
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()], 400);
        }

        $hygieneStandardVendor = HygieneStandardVendor::create([
            'name' => $request['name'],
            'type' => $request['type'],
            'contact_name' => $request['contact_name'],
            'contact_email' => $request['contact_email'],
            'contact_phone' => $request['contact_phone'],
            'address' => $request['address'],
            'hygiene_rating' => $request['hygiene_rating'],
            'compliance_certified' => $request['compliance_certified'],
            'certification_details' => $request['certification_details'],
            'notes' => $request['notes'],
            'venue_id' => $venue->id
        ]);



        // log api usage inside a try so that it doesn't break the api call
        try {
            $featureId = Feature::where('name', FeatureNaming::hygiene_standard)->where('feature_category', $venue->venueType->definition)->first()->id;
            $subFeatureId = DB::table('sub_features')->where('name', 'Hygiene Vendor Management')->where('feature_id', $featureId)->first()->id;
            $this->apiUsageLogger->log($featureId, $venue->id, 'Create Hygiene Vendor - POST', $subFeatureId);
        } catch (\Exception $e) {
            // do nothing
        }

        return response()->json([
            'message' => 'Hygiene vendor created successfully',
            'data' => $hygieneStandardVendor
        ]);
    }

    public function editVendor(Request $request): \Illuminate\Http\JsonResponse
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

        $id = $request->get('id');

        $hygieneStandardVendor = HygieneStandardVendor::where('id', $id )->where('venue_id', $venue->id)->first();
        if (!$hygieneStandardVendor) {
            return response()->json(['error' => 'Hygiene vendor not found'], 404);
        }

        $validator = Validator::make($request->all(), [
            'id' => 'required|exists:hygiene_standards_vendors,id',
            'name' => 'sometimes|required|string',
            'type' => 'sometimes|required|string',
            'contact_name' => 'nullable|string',
            'contact_email' => 'nullable|email',
            'contact_phone' => 'nullable|string',
            'address' => 'nullable|string',
            'hygiene_rating' => 'nullable|string',
            'compliance_certified' => 'nullable|boolean',
            'certification_details' => 'nullable|string',
            'notes' => 'nullable|string'
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()], 400);
        }

        $hygieneStandardVendor->update($request->all());

        // log api usage inside a try so that it doesn't break the api call
        try {
            $featureId = Feature::where('name', FeatureNaming::hygiene_standard)->where('feature_category', $venue->venueType->definition)->first()->id;
            $subFeatureId = DB::table('sub_features')->where('name', 'Hygiene Vendor Management')->where('feature_id', $featureId)->first()->id;
            $this->apiUsageLogger->log($featureId, $venue->id, 'Update Hygiene Vendor - PUT', $subFeatureId);
        } catch (\Exception $e) {
            // handle exception if needed
        }

        return response()->json([
            'message' => 'Hygiene vendor updated successfully',
            'data' => $hygieneStandardVendor
        ]);
    }

    public function deleteVendor($id): \Illuminate\Http\JsonResponse
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

        $hygieneStandardVendor = HygieneStandardVendor::where('id', $id)->where('venue_id', $venue->id)->first();


        if (!$hygieneStandardVendor) {
            return response()->json(['error' => 'Hygiene vendor not found'], 404);
        }

        $hygieneStandardVendor->delete();

        // log api usage inside a try so that it doesn't break the api call
        try {
            $featureId = Feature::where('name', FeatureNaming::hygiene_standard)->where('feature_category', $venue->venueType->definition)->first()->id;
            $subFeatureId = DB::table('sub_features')->where('name', 'Hygiene Vendor Management')->where('feature_id', $featureId)->first()->id;
            $this->apiUsageLogger->log($featureId, $venue->id, 'Delete Hygiene Vendor - DELETE', $subFeatureId);
        } catch (\Exception $e) {
            // do nothing
        }

        return response()->json(['message' => 'Hygiene check deleted successfully']);
    }

    public function listVendors(Request $request): \Illuminate\Http\JsonResponse
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

        $hygieneChecks = HygieneStandardVendor::where('venue_id', $venue->id)
            ->orderBy('id', 'desc')
            ->get();

        // log api usage inside a try so that it doesn't break the api call
        try {
            $featureId = Feature::where('name', FeatureNaming::hygiene_standard)->where('feature_category', $venue->venueType->definition)->first()->id;
            $subFeatureId = DB::table('sub_features')->where('name', 'Hygiene Vendor Management')->where('feature_id', $featureId)->first()->id;
            $this->apiUsageLogger->log($featureId, $venue->id, 'Retrieve Hygiene Vendors - GET', $subFeatureId);
        } catch (\Exception $e) {
            // do nothing
        }

        return response()->json([
            'message' => 'Hygiene Vendors retrieved successfully',
            'data' => $hygieneChecks
        ]);
    }

    public function markChecklistItemCompleted($id, Request $request ): \Illuminate\Http\JsonResponse
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
            'hygiene_check_id' => 'required|exists:hygiene_checks,id',
            'is_completed' => 'required|boolean'
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()], 400);
        }


        $hygieneCheckId = $request->input('hygiene_check_id');
        $checklistItem = ChecklistItem::where('id', $id)
            ->where('hygiene_check_id', $hygieneCheckId)
            ->first();

        if (!$checklistItem) {
            return response()->json(['error' => 'Checklist item not found or does not belong to the specified hygiene check'], 404);
        }

        $checklistItem->update(['is_completed' => $request->input('is_completed')]);
        $hygieneCheck = $checklistItem->hygieneCheck;

        // Fetch all items again to ensure fresh data
        $allItems = $hygieneCheck->checklistItems()->get();
        $completedItemsCount = $allItems->where('is_completed', true)->count();
        $totalItemsCount = $allItems->count();

        if ($request->input('is_completed') === true) {
            // Update hygiene check status
            if ($totalItemsCount === 1 && $completedItemsCount === 1) {
                // Only one item in the checklist and it's completed
                $hygieneCheck->update(['status' => 'completed']);
            } elseif ($completedItemsCount === 1) {
                // First item completed, set status to 'in progress'
                $hygieneCheck->update(['status' => 'in progress']);
            } elseif ($completedItemsCount === $totalItemsCount) {
                // All items completed, set status to 'completed'
                $hygieneCheck->update(['status' => 'completed']);
            }
        }

        // log api usage inside a try so that it doesn't break the api call
        try {
            $featureId = Feature::where('name', FeatureNaming::hygiene_standard)->where('feature_category', $venue->venueType->definition)->first()->id;
            $subFeatureId = DB::table('sub_features')->where('name', 'Hygiene Checklist')->where('feature_id', $featureId)->first()->id;
            $this->apiUsageLogger->log($featureId, $venue->id, 'Update Hygiene Checklist Item - PUT', $subFeatureId);
        } catch (\Exception $e) {
            // do nothing
        }

        return response()->json(['message' => 'Checklist item marked as completed']);

    }

    public function deleteChecklistItem(Request $request, $id): \Illuminate\Http\JsonResponse
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
            'hygiene_check_id' => 'required|exists:hygiene_checks,id',
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()], 400);
        }

        $hygieneCheckId = $request->input('hygiene_check_id');
        $checklistItem = ChecklistItem::where('id', $id)
            ->where('hygiene_check_id', $hygieneCheckId)
            ->first();

        if (!$checklistItem) {
            return response()->json(['error' => 'Checklist item not found or does not belong to the specified hygiene check'], 404);
        }

        $checklistItem->delete();

        // log api usage inside a try so that it doesn't break the api call
        try {
            $featureId = Feature::where('name', FeatureNaming::hygiene_standard)->where('feature_category', $venue->venueType->definition)->first()->id;
            $subFeatureId = DB::table('sub_features')->where('name', 'Hygiene Checklist')->where('feature_id', $featureId)->first()->id;
            $this->apiUsageLogger->log($featureId, $venue->id, 'Delete Hygiene Checklist Item - DELETE', $subFeatureId);
        } catch (\Exception $e) {
            // do nothing
        }

        return response()->json(['message' => 'Checklist item deleted successfully']);
    }

    public function createInspection(Request $request): \Illuminate\Http\JsonResponse
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
            'inspection_date' => 'required|date',
            'inspector_name' => 'required|string',
            'observations' => 'nullable|string',
            'remind_me_before_log_date_hours' => ['nullable', 'integer', new NumericRangeRule()],
            'next_inspection_date' => 'nullable|date',
            'hygiene_check_id' => 'nullable|exists:hygiene_checks,id',

        ]);

        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()], 400);
        }

        $inspection = HygieneInspection::create([
            'inspection_date' => $request['inspection_date'],
            'inspector_name' => $request['inspector_name'],
            'observations' => $request['observations'] ?? 'no observations at this time',
            'remind_me_before_log_date_hours' => $request['remind_me_before_log_date_hours'],
            'next_inspection_date' => $request['next_inspection_date'],
            'hygiene_check_id' => $request['hygiene_check_id'],
            'venue_id' => $venue->id
        ]);



        // log api usage inside a try so that it doesn't break the api call
        try {
            $featureId = Feature::where('name', FeatureNaming::hygiene_standard)->where('feature_category', $venue->venueType->definition)->first()->id;
            $subFeatureId = DB::table('sub_features')->where('name', 'Hygiene Inspection')->where('feature_id', $featureId)->first()->id;
            $this->apiUsageLogger->log($featureId, $venue->id, 'Create Hygiene Inspection - POST', $subFeatureId);
        } catch (\Exception $e) {
            // do nothing
        }

        return response()->json([
            'message' => 'Hygiene inspection created successfully',
            'data' => $inspection->load('hygieneCheck')
        ]);
    }

    public function editInspection(Request $request): \Illuminate\Http\JsonResponse
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
            'id' => 'nullable|exists:hygiene_inspections,id',
            'inspection_date' => 'nullable|date',
            'inspector_name' => 'nullable|string',
            'observations' => 'nullable|string',
            'remind_me_before_log_date_hours' => ['nullable', 'integer', new NumericRangeRule()],
            'next_inspection_date' => 'nullable|date',
            'hygiene_check_id' => 'nullable|exists:hygiene_checks,id',
            'inspection_result_status' => 'nullable|string|in:pending,pass,fail',
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()], 400);
        }


        $inspectionId = $request->input('id');
        $inspection = HygieneInspection::where('id', $inspectionId)->where('venue_id', $venue->id)->first();
        if (!$inspection) {
            return response()->json(['error' => 'Hygiene inspection not found'], 404);
        }

        $inspection->update($validator->validated());

        // Log API usage
        try {
            $featureId = Feature::where('name', FeatureNaming::hygiene_standard)->where('feature_category', $venue->venueType->definition)->first()->id;
            $subFeatureId = DB::table('sub_features')->where('name', 'Hygiene Inspection')->where('feature_id', $featureId)->first()->id;
            $this->apiUsageLogger->log($featureId, $venue->id, 'Edit Hygiene Inspection - PUT', $subFeatureId);
        } catch (\Exception $e) {
            // Do nothing
        }

        return response()->json([
            'message' => 'Hygiene inspection updated successfully',
            'data' => $inspection
        ]);
    }


    public function deleteInspection($id): \Illuminate\Http\JsonResponse
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

        $hygieneInspection = HygieneInspection::where('id', $id)->where('venue_id', $venue->id)->first();


        if (!$hygieneInspection) {
            return response()->json(['error' => 'Hygiene inspection not found'], 404);
        }

        $hygieneInspection->delete();

        // log api usage inside a try so that it doesn't break the api call
        try {
            $featureId = Feature::where('name', FeatureNaming::hygiene_standard)->where('feature_category', $venue->venueType->definition)->first()->id;
            $subFeatureId = DB::table('sub_features')->where('name', 'Hygiene Inspection')->where('feature_id', $featureId)->first()->id;
            $this->apiUsageLogger->log($featureId, $venue->id, 'Delete Hygiene Inspection - DELETE', $subFeatureId);
        } catch (\Exception $e) {
            // do nothing
        }

        return response()->json(['message' => 'Hygiene Inspection deleted successfully']);
    }

    public function listInspections(Request $request): \Illuminate\Http\JsonResponse
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

        $hygieneInspections = HygieneInspection::where('venue_id', $venue->id)
            ->with('hygieneCheck')
            ->orderBy('id', 'desc')
            ->get();

        // log api usage inside a try so that it doesn't break the api call
        try {
            $featureId = Feature::where('name', FeatureNaming::hygiene_standard)->where('feature_category', $venue->venueType->definition)->first()->id;
            $subFeatureId = DB::table('sub_features')->where('name', 'Hygiene Inspection')->where('feature_id', $featureId)->first()->id;
            $this->apiUsageLogger->log($featureId, $venue->id, 'Retrieve Hygiene Inspection - GET', $subFeatureId);
        } catch (\Exception $e) {
            // do nothing
        }

        return response()->json([
            'message' => 'Hygiene inspections retrieved successfully',
            'data' => $hygieneInspections
        ]);
    }

}
