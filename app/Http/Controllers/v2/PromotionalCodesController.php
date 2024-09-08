<?php

namespace App\Http\Controllers\v2;

use App\Http\Controllers\Controller;
use App\Models\ApiApp;
use App\Models\PromoCodeType;
use App\Models\PromotionalCode;
use App\Models\PromotionalCodePhoto;
use App\Models\WcIntegration;
use App\Services\MondayAutomationsService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class PromotionalCodesController extends Controller
{

    protected $mondayAutomationService;

    public function __construct(MondayAutomationsService $mondayAutomationService)
    {
        $this->mondayAutomationService = $mondayAutomationService;
    }

    public function create(Request $request): \Illuminate\Http\JsonResponse
    {

        $validator = Validator::make($request->all(), [
            'title' => 'required|string',
            'description' => 'nullable|string',
            'usage' => 'required|integer',
            'start' => 'required|date',
            'end' => 'required|date',
            'for' => 'required|in:food,entertainment,accommodation,retail,all',
            'code' => 'nullable|unique:promotional_codes,code',
            'type' => [
                'required',
                Rule::in(['nr_subscription_free_months', 'discount_percentage', 'discount_fix'])
            ],
            'created_by' => 'nullable|string',
            'first_payment' => 'boolean',
            'nr_of_months' => 'required_if:first_payment,false|integer',
            'all_plans' => 'boolean',
            'plan_id' => 'required_if:all_plans,false|integer',
            'percentage_discount_value' => 'required_if:type,discount_percentage|numeric',
            'fixed_discount_value' => 'required_if:type,discount_fix|numeric',
            'country_code' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()], 400);
        }

        // TODO: when connecting with plan service, check if plan exists based on plan_id
        $attributes = [
            'nr_of_months' => $request->nr_of_months,
            'percentage_discount_value' => $request->percentage_discount_value,
            'plan_id' => $request->plan_id,
            'all_plans' => $request->all_plans,
            'fixed_discount_value' => $request->fixed_discount_value,
            'country_code' => $request->country_code,
            'first_payment' => $request->first_payment,
        ];

        if ($request->first_payment) {
            unset($attributes['nr_of_months']);
        }

        if (!$request->all_plans) {
            unset($attributes['plan_id']);
        }

        // Create promo code type with attributes in JSON format
        $promoCodeType = PromoCodeType::create([
            'type' => $request->type,
            'attributes' => json_encode($attributes),
        ]);



        // Generate a random code if not provided
        $code = $request->input('code');
        if (!$code) {
            $code = $this->generateUniqueCode(); // Implement your code generation logic
        }

        $promotionalCode = new PromotionalCode();
        $promotionalCode->title = $request->title;
        $promotionalCode->description = $request->description;
        $promotionalCode->category_description = $request->category_description;
        $promotionalCode->usage = $request->usage;
        $promotionalCode->start = $request->start;
        $promotionalCode->end = $request->end;
        $promotionalCode->for = $request->for;
        $promotionalCode->code = $code;
        $promotionalCode->type = $promoCodeType->id;
        $promotionalCode->created_by = $request->created_by;
        $promotionalCode->save();


        try {
            $this->mondayAutomationService->promoCodeCreation($promotionalCode);
        } catch (\Exception $e) {
            \Sentry\captureException($e);
            // do nothing
        }

        if ($request->hasFile('banner') && $request->created_by === 'vb_backend') {

            $photoFile = $request->file('banner');

            $filename = Str::random(20) . '.' . $photoFile->getClientOriginalExtension();

            $promotionalCodeFolder = 'promotional_codes';
            // Upload photo to AWS S3
            $path = Storage::disk('s3')->putFileAs('venue_gallery_photos/' . $promotionalCodeFolder , $photoFile, $filename);


            // Save photo record in the database
            $photo = new PromotionalCodePhoto();
            $photo->promotional_code_id = $promotionalCode->id;
            $photo->image_path = $path;
            $photo->save();

            $promotionalCode->banner = $path;
            $promotionalCode->save();

            return response()->json(['message' => 'Promotional code created successfully with photo'], 201);
        } else {

            $promotionalCode->banner = $request->banner;

            return response()->json(['message' => 'Promotional code created successfully with banner link'], 201);
        }
    }

    public function listPromotionalCodes(): \Illuminate\Http\JsonResponse
    {
        $promotionalCodes = PromotionalCode::whereNull('deleted_at')
            ->with('promoCodeType')
            ->get();


        $promotionalCodes->each(function ($item) {
            if ($item->banner && $item->created_by === 'vb_backend') {
                $item->banner_url = Storage::disk('s3')->temporaryUrl($item->banner, now()->addMinutes(5));
            }
        });

        return response()->json(['promotionalCodes' => $promotionalCodes]);
    }

    private function generateUniqueCode(): string
    {
        $length = rand(6, 10); // Generate a random length between 6 and 10 characters
        $characters = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789'; // Allowed characters
        $code = '';

        for ($i = 0; $i < $length; $i++) {
            $code .= $characters[rand(0, strlen($characters) - 1)];
        }

        return $code;
    }

    // Method to soft delete a Promotional Code
    public function deletePromotionalCode($id): \Illuminate\Http\JsonResponse
    {

        $promotionalCode = PromotionalCode::find($id);

        if (!$promotionalCode) {
            return response()->json(['message' => 'Promotional code not found'], 404);
        }

        $promotionalCode->delete();

        return response()->json(['message' => 'Promotional code deleted successfully']);
    }

    public function update(Request $request): \Illuminate\Http\JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'id' => 'required|exists:promotional_codes,id',
            'title' => 'string',
            'description' => 'string',
            'usage' => 'integer',
            'start' => 'date',
            'end' => 'date',
            'for' => 'in:food,entertainment,accommodation,retail,all',
            'type' => [
                Rule::in(['nr_subscription_free_months', 'discount_percentage', 'discount_fix'])
            ],
            'created_by' => 'string',
            'first_payment' => 'boolean',
            'nr_of_months' => 'required_if:first_payment,false|integer',
            'all_plans' => 'boolean',
            'plan_id' => 'required_if:all_plans,false|integer',
            'percentage_discount_value' => 'required_if:type,discount_percentage|numeric',
            'fixed_discount_value' => 'required_if:type,discount_fix|numeric',
            'country_code' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()], 400);
        }

        $attributes = [
            'nr_of_months' => $request->nr_of_months,
            'percentage_discount_value' => $request->percentage_discount_value,
            'plan_id' => $request->plan_id,
            'all_plans' => $request->all_plans,
            'fixed_discount_value' => $request->fixed_discount_value,
            'country_code' => $request->country_code,
            'first_payment' => $request->first_payment,
        ];

        if ($request->first_payment) {
            unset($attributes['nr_of_months']);
        }

        if (!$request->all_plans) {
            unset($attributes['plan_id']);
        }

        $promotionalCode = PromotionalCode::find($request->id);
        $promoCodeType = PromoCodeType::find($promotionalCode->type);

        // Update promo code type with updated attributes in JSON format
        $promoCodeType->type = $request->type;
        $promoCodeType->attributes = json_encode($attributes);
        $promoCodeType->save();

        // Update promotional code details
        $promotionalCode->title = $request->title;
        $promotionalCode->description = $request->description;
        $promotionalCode->usage = $request->usage;
        $promotionalCode->start = $request->start;
        $promotionalCode->end = $request->end;
        $promotionalCode->for = $request->for;
        $promotionalCode->created_by = $request->created_by;
        $promotionalCode->save();

        if ($request->hasFile('banner') && $request->created_by === 'vb_backend') {
            $photoFile = $request->file('banner');
            $filename = Str::random(20) . '.' . $photoFile->getClientOriginalExtension();
            $promotionalCodeFolder = 'promotional_codes';

            // Upload photo to AWS S3
            $path = Storage::disk('s3')->putFileAs('venue_gallery_photos/' . $promotionalCodeFolder, $photoFile, $filename);

            // Update photo record in the database
            $photo = PromotionalCodePhoto::where('promotional_code_id', $promotionalCode->id)->first();
            if ($photo) {
                Storage::disk('s3')->delete($photo->image_path); // Delete the old image
                $photo->image_path = $path;
                $photo->save();
            }

            $promotionalCode->banner = $path;
            $promotionalCode->save();

            return response()->json(['message' => 'Promotional code updated successfully with new photo'], 200);
        } else {
            $promotionalCode->banner = $request->banner;
            $promotionalCode->save();

            return response()->json(['message' => 'Promotional code updated successfully with banner link'], 200);
        }
    }

}
