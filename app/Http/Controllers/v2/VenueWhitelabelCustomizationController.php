<?php

namespace App\Http\Controllers\v2;

use App\Http\Controllers\Controller;
use App\Mail\NewContactForm;
use App\Models\GolfAvailability;
use App\Models\IndustryBrandCustomizationElement;
use App\Models\OpeningHour;
use App\Models\Restaurant;
use App\Models\VenueContactForm;
use App\Models\VenueSubscriber;
use App\Models\VenueWhitelabelCustomization;
use App\Models\VenueWhiteLabelInformation;
use App\Services\VenueService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use stdClass;

class VenueWhitelabelCustomizationController extends Controller
{
    // Fetch all customizations
    private $venueService;

    public function __construct(VenueService $venueService)
    {
        $this->venueService = $venueService;
    }

    public function get(): \Illuminate\Http\JsonResponse
    {
        $apiCallVenueShortCode = request()->get('venue_short_code');
        if (!$apiCallVenueShortCode) {
            return response()->json(['error' => 'Venue short code is required'], 400);
        }

        $venue = auth()->user()->restaurants->where('short_code', $apiCallVenueShortCode)->first();
        if (!$venue) {
            return response()->json(['error' => 'Venue not found'], 404);
        }

        $customization = VenueWhitelabelCustomization::where('venue_id', $venue->id)->first();
        $subscribers = VenueSubscriber::where('venue_id', $venue->id)->get();
        $contacts = VenueContactForm::where('venue_id', $venue->id)->get();

        $managedOpeningHours = OpeningHour::where('restaurant_id', $venue->id)->where('used_in_white_label', true)->get();

        if ($customization) {
            $customization->booking_sites = json_decode($customization->booking_sites);
            $customization->header_links = json_decode($customization->header_links);
            $customization->subscribers = $subscribers;
            $customization->contacts = $contacts;
            $customization->availability = $managedOpeningHours;
        }

        return response()->json($customization);
    }

    public function emailSubscribe(Request $request): \Illuminate\Http\JsonResponse
    {
        $apiCallVenueAppKey = request()->get('venue_app_key');
        if (!$apiCallVenueAppKey) {
            return response()->json(['error' => 'Venue app key is required'], 400);
        }

        $venue = Restaurant::where('app_key', $apiCallVenueAppKey)->first();
        if (!$venue) {
            return response()->json(['error' => 'Venue not found'], 404);
        }

        // validator
        $validator = Validator::make($request->all(), [
            // unique email for each venue
            'email' => 'required|email|unique:venue_subscribers,email,NULL,id,venue_id,' . $venue->id,
        ]);

        if ($validator->fails()) {

            return response()->json(['error' => 'Already subscribed to this venue witb this email'], 400);
        }

        VenueSubscriber::create([
            'email' => $request->email,
            'venue_id' => $venue->id,
            'subscribed' => true,
        ]);


        return response()->json([
            'message' => 'Subscribed successfully'
        ]);

    }

    public function brandContactConfigurations(): \Illuminate\Http\JsonResponse
    {
        $apiCallVenueAppKey = request()->get('venue_app_key');
        if (!$apiCallVenueAppKey) {
            return response()->json(['error' => 'Venue app key is required'], 400);
        }

        $venue = Restaurant::where('app_key', $apiCallVenueAppKey)->first();
        if (!$venue) {
            return response()->json(['error' => 'Venue not found'], 404);
        }

        // get brand profile
        $brandProfile =  IndustryBrandCustomizationElement::with(['venueBrandProfileCustomizations' => function ($query) use ($venue) {
            $query->where('venue_id', $venue->id);
        }])
            ->where('industry_id', $venue->venue_industry)
            ->get();

        // if industry is food and beverage return only specific items from brand profile
        // only those with element_name = food and beverage
        // 1. FindATimeButton
        // 2. AllButtons
        // 3. BookNowButton
        // 4. Footer
        // 5. Tags
        // 6. SubscribeBtn
        // 7. TimeSuggested
        // 8. ContactFormLeftBlock
        // 9. ContactFormBtn
        // 10. ContactFormTopLabel

        if ($venue->venueType->definition === 'food') {
            $brandProfile = $brandProfile->filter(function ($item) {
                // Filter directly on collection
                // Return only items that have element_name in the array
                // directly on the collection not in venueBrandProfileCustomizations

                return in_array($item->element_name, [
                    'FindATimeButton',
                    'AllButtons',
                    'BookNowButton',
                    'Footer',
                    'OutOfStock',
                    'Tags',
                    'SubscribeBtn',
                    'TimeSuggested',
                    'ContactFormLeftBlock',
                    'ContactFormBtn',
                    'ContactFormTopLabel'
                ]);
            })->values(); // Use values() to reset the keys and get a re-indexed array
        }


        // if industry is retail return only specific items from brand profile
        // only those with element_name =
        // 1. AllButtons
        // 2. CartPlusButton
        // 3. Footer
        // 4. Tags
        // 5. OutOfStock
        // 6. SubscribeBtn
        // 7. YourCart
        // 8. AddToCart
        // 9. ContactFormLeftBlock
        // 10. ContactFormBtn
        // 11. ContactFormTopLabel

        if ($venue->venueType->definition === 'retail') {
            $brandProfile = $brandProfile->filter(function ($item) {
                // Filter directly on collection
                // Return only items that have element_name in the array
                // directly on the collection not in venueBrandProfileCustomizations

                return in_array($item->element_name, [
                    'AllButtons',
                    'CartPlusButton',
                    'Footer',
                    'Tags',
                    'OutOfStock',
                    'SubscribeBtn',
                    'YourCart',
                    'AddToCart',
                    'ContactFormLeftBlock',
                    'ContactFormBtn',
                    'ContactFormTopLabel',
                ]);
            })->values(); // Use values() to reset the keys and get a re-indexed array
        }

        // if industry is accommodation return only specific items from brand profile
        // only those with element_name =
        // 1. AllButtons
        // 2. Footer
        // 3. ContactFormLeftBlock
        // 4. ContactFormBtn
        // 5. ContactFormTopLabel
        if ($venue->venueType->definition === 'accommodation') {
            $brandProfile = $brandProfile->filter(function ($item) {
                // Filter directly on collection
                // Return only items that have element_name in the array
                // directly on the collection not in venueBrandProfileCustomizations
                return in_array($item->element_name, [
                    'AllButtons',
                    'Footer',
                    'ContactFormLeftBlock',
                    'ContactFormBtn',
                    'ContactFormTopLabel',
                ]);
            })->values(); // Use values() to reset the keys and get a re-indexed array
        }

        // if industry is sport_entertainment return only specific items from brand profile
        // only those with sport_entertainment =
        // 1. AllButtons
        // 2. Tags
        // 3. Footer
        // 4. ContactFormLeftBlock
        // 5. ContactFormBtn
        // 6. ContactFormTopLabel
        // 7. SubscribeBtn
        if ($venue->venueType->definition === 'sport_entertainment') {
            $brandProfile = $brandProfile->filter(function ($item) {
                // Filter directly on collection
                // Return only items that have element_name in the array
                // directly on the collection not in venueBrandProfileCustomizations
                return in_array($item->element_name, [
                    'AllButtons',
                    'Tags',
                    'Footer',
                    'ContactFormLeftBlock',
                    'ContactFormBtn',
                    'ContactFormTopLabel',
                    'SubscribeBtn',
                ]);
            })->values(); // Use values() to reset the keys and get a re-indexed array
        }

        $customizationBrand = VenueWhitelabelCustomization::where('venue_id', $venue->id)->first();
        $customizationBrandInformation = new StdClass;

        if($customizationBrand) {
            $customizationBrandInformation->contact_page_main_title_string = $customizationBrand->contact_page_main_title_string;
            $customizationBrandInformation->contact_page_toplabel_string = $customizationBrand->contact_page_toplabel_string;
            $customizationBrandInformation->contact_page_address_string = $customizationBrand->contact_page_address_string;
            $customizationBrandInformation->contact_page_phone_string = $customizationBrand->contact_page_phone_string;
            $customizationBrandInformation->contact_page_email_string = $customizationBrand->contact_page_email_string;
            $customizationBrandInformation->contact_page_open_hours_string = $customizationBrand->contact_page_open_hours_string;
            $customizationBrandInformation->contact_page_form_subtitle_string = $customizationBrand->contact_page_form_subtitle_string;
            $customizationBrandInformation->contact_page_form_submit_btn_txt = $customizationBrand->contact_page_form_submit_btn_txt;
            $customizationBrandInformation->contact_page_fullname_label_string = $customizationBrand->contact_page_fullname_label_string;
            $customizationBrandInformation->contact_page_phone_number_label_string = $customizationBrand->contact_page_phone_number_label_string;
            $customizationBrandInformation->contact_page_phone_number_show = $customizationBrand->contact_page_phone_number_show;
            $customizationBrandInformation->contact_page_email_label_string = $customizationBrand->contact_page_email_label_string;
            $customizationBrandInformation->contact_page_subject_label_string = $customizationBrand->contact_page_subject_label_string;
            $customizationBrandInformation->contact_page_subject_show = $customizationBrand->contact_page_subject_show;
            $customizationBrandInformation->contact_page_content_label_strin = $customizationBrand->contact_page_content_label_strin;
            $customizationBrandInformation->contact_page_content_show = $customizationBrand->contact_page_content_show;
            $customizationBrandInformation->contact_page_enable = $customizationBrand->contact_page_enable;
            $customizationBrandInformation->contact_page_opening_hours_enable = $customizationBrand->contact_page_opening_hours_enable;
            $customizationBrandInformation->contact_page_address_value = $customizationBrand->contact_page_address_value;
            $customizationBrandInformation->contact_page_email_value = $customizationBrand->contact_page_email_value;
            $customizationBrandInformation->contact_page_phone_value = $customizationBrand->contact_page_phone_value;
            $customizationBrandInformation->vt_link = $customizationBrand->vt_link;

            $venue = Restaurant::find($venue->id);

            $openingHour =  OpeningHour::where('restaurant_id', $venue->id)->where('used_in_white_label', true)->first();
            if($venue->venueType->name === 'Golf Venue') {
                $managedOpeningHours = GolfAvailability::where('golf_id', $venue->id)->first();
                $customizationBrandInformation->contact_page_opening_hours_value = $managedOpeningHours?->formattedOpeningHours();
            } else {
                $customizationBrandInformation->contact_page_opening_hours_value = $openingHour?->formattedOpeningHours();
            }

            $customizationBrandInformation->openingHours =  $venue->openingHours;
            $customizationBrandInformation->brandProfile = $brandProfile;

        }

        $venueWhiteLabelInformation = new StdClass;
        $retrievedWhiteLabelOverview = new StdClass();
        $retrievedWhiteLabelOverview->venue_name = $venue->name;
        $retrievedWhiteLabelOverview->cover = $venue->cover ? Storage::disk('s3')->temporaryUrl($venue->cover, '+5 minutes') : null;
        $retrievedWhiteLabelOverview->logo = $venue->logo ? Storage::disk('s3')->temporaryUrl($venue->logo, '+5 minutes') : null;
        $venueWhiteLabelInformation->brand_profile = $brandProfile;
        $venueWhiteLabelInformation->overview = $retrievedWhiteLabelOverview;

        return response()->json([
            'message' => 'Venue contact configurations',
            'data' => $customizationBrandInformation,
            'venue' => $venueWhiteLabelInformation,
        ]);
    }

    public function submitWhitelabelContact(Request $request): \Illuminate\Http\JsonResponse
    {
        $apiCallVenueAppKey = request()->get('venue_app_key');
        if (!$apiCallVenueAppKey) {
            return response()->json(['error' => 'Venue app key is required'], 400);
        }

        $venue = Restaurant::where('app_key', $apiCallVenueAppKey)->first();
        if (!$venue) {
            return response()->json(['error' => 'Venue not found'], 404);
        }

        // validator
        $validator = Validator::make($request->all(), [
            // unique email for each venue
            'full_name' => 'required|string|max:255',
            'email' => 'required|email',
            'phone' => 'nullable|string|max:255',
            'subject' => 'nullable|string|max:255',
            'email_content' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()], 400);
        }

        VenueContactForm::create([
            'full_name' => $request->full_name,
            'email' => $request->email,
            'phone' => $request->phone,
            'subject' => $request->subject,
            'content' => $request->email_content,
            'venue_id' => $venue->id,
        ]);

        $venueEmail = $venue->email;

        // Prepare the data for the email
        $data = [
            'venueName' => $venue->name,
            'fullName' => $request->full_name,
            'email' => $request->email,
            'phone' => $request->phone,
            'email_subject' => $request->subject,
            'content' => $request->email_content
        ];

        try {
            // Notify venue
            Mail::to($venueEmail)->send(new NewContactForm(
                $data['venueName'],
                $data['fullName'],
                $data['email'],
                $data['phone'],
                $data['email_subject'],
                $data['content'],
            ));
        } catch (\Exception $e) {

            return response()->json(['error' => 'Failed to send email'], 500);
        }




        return response()->json([
            'message' => 'Contact form submitted successfully'
        ]);

    }


    public function update(Request $request ): \Illuminate\Http\JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'booking_sites' => 'array',
            'booking_sites.*.name' => 'required_with:booking_sites|string|max:255',
            'booking_sites.*.url' => 'required_with:booking_sites|url',
            'header_links' => 'array',
            'header_links.*.name' => 'required_with:header_links|string|max:255',
            'header_links.*.section_id' => 'nullable|integer',
            'facebook_link' => 'url|string|max:255|nullable',
            'twitter_link' => 'url|string|max:255|nullable',
            'instagram_link' => 'url|string|max:255|nullable',
            'pinterest_link' => 'url|string|max:255|nullable',
            'linkedin_link' => 'url|string|max:255|nullable',
            'call_us_text' => 'string|max:255|nullable',
            'support_phone' => 'string|max:255|nullable',
            'tiktok_link' => 'url|string|max:255|nullable',
            'subscribe_label_text' => 'string|max:255|nullable',
            'social_media_label_text' => 'string|max:255|nullable',
            'show_newsletter' => 'boolean|nullable',
            'contact_page_main_title_string' => 'string|max:255|nullable',
            'contact_page_toplabel_string' => 'string|max:255|nullable',
            'contact_page_address_string' => 'string|max:255|nullable',
            'contact_page_phone_string' => 'string|max:255|nullable',
            'contact_page_email_string' => 'string|max:255|nullable',
            'contact_page_open_hours_string' => 'string|max:255|nullable',
            'contact_page_form_subtitle_string' => 'string|max:255|nullable',
            'contact_page_form_submit_btn_txt' => 'string|max:255|nullable',
            'contact_page_fullname_label_string' => 'string|max:255|nullable',
            'contact_page_phone_number_label_string' => 'string|max:255|nullable',
            'contact_page_phone_number_show' => 'boolean|nullable',
            'contact_page_email_label_string' => 'string|max:255|nullable',
            'contact_page_subject_label_string' => 'string|max:255|nullable',
            'contact_page_subject_show' => 'boolean|nullable',
            'contact_page_content_label_string' => 'string|max:255|nullable',
            'contact_page_content_show' => 'boolean|nullable',
            'contact_page_enable' => 'boolean|nullable',
            'contact_page_opening_hours_enable' => 'boolean|nullable',
            'contact_page_address_value' => 'string|max:255|nullable',
            'contact_page_email_value' => 'email|max:255|nullable',
            'contact_page_phone_value' => 'string|max:255|nullable',
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()], 400);
        }

        $venue = $this->venueService->adminAuthCheck();
        $whitelabelInformation = VenueWhitelabelInformation::where('venue_id', $venue->id)->first();

        if (!$whitelabelInformation) {
           // create it with just venue_id
            $whitelabelInformation = VenueWhiteLabelInformation::create([
                'venue_id' => $venue->id
            ]);
        }

        $customization = VenueWhitelabelCustomization::where('venue_id', $venue->id)->first();

        if (!$customization) {
            $customization = VenueWhitelabelCustomization::create([
                'venue_id' => $venue->id,
                'v_wl_information_id' => $whitelabelInformation->id,
                'booking_sites' => $request->has('booking_sites') ? json_encode($request->booking_sites) : null,
                'header_links' => $request->has('header_links') ? json_encode($request->header_links) : null,
                'facebook_link' => $request->input('facebook_link'),
                'twitter_link' => $request->input('twitter_link'),
                'instagram_link' => $request->input('instagram_link'),
                'pinterest_link' => $request->input('pinterest_link'),
                'linkedin_link' => $request->input('linkedin_link'),
                'call_us_text' => $request->input('call_us_text'),
                'tiktok_link' => $request->input('tiktok_link'),
                'social_media_label_text' => $request->input('social_media_label_text'),
                'subscribe_label_text' => $request->input('subscribe_label_text'),
                'support_phone' => $request->input('support_phone'),
                'show_newsletter' => $request->input('show_newsletter'),
                'contact_page_main_title_string' => $request->input('contact_page_main_title_string'),
                'contact_page_toplabel_string' => $request->input('contact_page_toplabel_string'),
                'contact_page_address_string' => $request->input('contact_page_address_string'),
                'contact_page_phone_string' => $request->input('contact_page_phone_string'),
                'contact_page_email_string' => $request->input('contact_page_email_string'),
                'contact_page_open_hours_string' => $request->input('contact_page_open_hours_string'),
                'contact_page_form_subtitle_string' => $request->input('contact_page_form_subtitle_string'),
                'contact_page_form_submit_btn_txt' => $request->input('contact_page_form_submit_btn_txt'),
                'contact_page_fullname_label_string' => $request->input('contact_page_fullname_label_string'),
                'contact_page_phone_number_label_string' => $request->input('contact_page_phone_number_label_string'),
                'contact_page_phone_number_show' => $request->input('contact_page_phone_number_show'),
                'contact_page_email_label_string' => $request->input('contact_page_email_label_string'),
                'contact_page_subject_label_string' => $request->input('contact_page_subject_label_string'),
                'contact_page_subject_show' => $request->input('contact_page_subject_show'),
                'contact_page_content_label_string' => $request->input('contact_page_content_label_string'),
                'contact_page_content_show' => $request->input('contact_page_content_show'),
                'contact_page_enable' => $request->input('contact_page_enable'),
                'contact_page_opening_hours_enable' => $request->input('contact_page_opening_hours_enable'),
                'contact_page_address_value' => $request->input('contact_page_address_value'),
                'contact_page_email_value' => $request->input('contact_page_email_value'),
                'contact_page_phone_value' => $request->input('contact_page_phone_value'),
                'contact_page_opening_hours_value' => $request->input('contact_page_opening_hours_value'),
                'vt_link' => $request->input('vt_link'),
            ]);
        }
        else {
            if ($request->has('booking_sites')) {
                $customization->booking_sites = json_encode($request->booking_sites);
            }
            if ($request->has('header_links')) {
                $customization->header_links = json_encode($request->header_links);
            }

            $customization->facebook_link = $request->has('facebook_link') ? $request->facebook_link : $customization->facebook_link;
            $customization->twitter_link = $request->has('twitter_link') ? $request->twitter_link : $customization->twitter_link;
            $customization->instagram_link = $request->has('instagram_link') ? $request->instagram_link : $customization->instagram_link;
            $customization->tiktok_link = $request->has('tiktok_link') ? $request->tiktok_link : $customization->tiktok_link;
            $customization->subscribe_label_text = $request->has('subscribe_label_text') ? $request->subscribe_label_text : $customization->subscribe_label_text;
            $customization->social_media_label_text = $request->has('social_media_label_text') ? $request->social_media_label_text : $customization->social_media_label_text;
            $customization->linkedin_link = $request->has('linkedin_link') ? $request->linkedin_link : $customization->linkedin_link;
            $customization->pinterest_link = $request->has('pinterest_link') ? $request->pinterest_link : $customization->pinterest_link;
            $customization->call_us_text = $request->has('call_us_text') ? $request->call_us_text : $customization->call_us_text;
            $customization->support_phone = $request->has('support_phone') ? $request->support_phone : $customization->support_phone;
            $customization->show_newsletter = $request->has('show_newsletter') ? $request->show_newsletter : $customization->show_newsletter;
            $customization->contact_page_main_title_string = $request->has('contact_page_main_title_string') ? $request->contact_page_main_title_string : $customization->contact_page_main_title_string;
         $customization->contact_page_toplabel_string = $request->has('contact_page_toplabel_string') ? $request->contact_page_toplabel_string : $customization->contact_page_toplabel_string;
         $customization->contact_page_address_string = $request->has('contact_page_address_string') ? $request->contact_page_address_string : $customization->contact_page_address_string;
         $customization->contact_page_phone_string = $request->has('contact_page_phone_string') ? $request->contact_page_phone_string : $customization->contact_page_phone_string;
         $customization->contact_page_email_string = $request->has('contact_page_email_string') ? $request->contact_page_email_string : $customization->contact_page_email_string;
         $customization->contact_page_open_hours_string = $request->has('contact_page_open_hours_string') ? $request->contact_page_open_hours_string : $customization->contact_page_open_hours_string;
         $customization->contact_page_form_subtitle_string = $request->has('contact_page_form_subtitle_string') ? $request->contact_page_form_subtitle_string : $customization->contact_page_form_subtitle_string;
         $customization->contact_page_form_submit_btn_txt = $request->has('contact_page_form_submit_btn_txt') ? $request->contact_page_form_submit_btn_txt : $customization->contact_page_form_submit_btn_txt;
         $customization->contact_page_fullname_label_string = $request->has('contact_page_fullname_label_string') ? $request->contact_page_fullname_label_string : $customization->contact_page_fullname_label_string;
         $customization->contact_page_phone_number_label_string = $request->has('contact_page_phone_number_label_string') ? $request->contact_page_phone_number_label_string : $customization->contact_page_phone_number_label_string;
         $customization->contact_page_phone_number_show = $request->has('contact_page_phone_number_show') ? $request->contact_page_phone_number_show : $customization->contact_page_phone_number_show;
         $customization->contact_page_email_label_string = $request->has('contact_page_email_label_string') ? $request->contact_page_email_label_string : $customization->contact_page_email_label_string;
         $customization->contact_page_subject_label_string = $request->has('contact_page_subject_label_string') ? $request->contact_page_subject_label_string : $customization->contact_page_subject_label_string;
         $customization->contact_page_subject_show = $request->has('contact_page_subject_show') ? $request->contact_page_subject_show : $customization->contact_page_subject_show;
         $customization->contact_page_content_label_string = $request->has('contact_page_content_label_string') ? $request->contact_page_content_label_string : $customization->contact_page_content_label_string;
         $customization->contact_page_content_show = $request->has('contact_page_content_show') ? $request->contact_page_content_show : $customization->contact_page_content_show;
         $customization->contact_page_enable = $request->has('contact_page_enable') ? $request->contact_page_enable : $customization->contact_page_enable;
         $customization->contact_page_opening_hours_enable = $request->has('contact_page_opening_hours_enable') ? $request->contact_page_opening_hours_enable : $customization->contact_page_opening_hours_enable;
         $customization->contact_page_address_value = $request->has('contact_page_address_value') ? $request->contact_page_address_value : $customization->contact_page_address_value;
         $customization->contact_page_email_value = $request->has('contact_page_email_value') ? $request->contact_page_email_value : $customization->contact_page_email_value;
         $customization->contact_page_phone_value = $request->has('contact_page_phone_value') ? $request->contact_page_phone_value : $customization->contact_page_phone_value;
         $customization->contact_page_opening_hours_value = $request->has('contact_page_opening_hours_value') ? $request->contact_page_opening_hours_value : $customization->contact_page_opening_hours_value;
         $customization->vt_link = $request->has('vt_link') ? $request->vt_link : $customization->vt_link;

            $customization->save();
        }

        return response()->json([
            'message' => 'Customization updated successfully', 'data' => $customization
        ]);
    }
}
