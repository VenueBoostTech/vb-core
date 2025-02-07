<?php

namespace App\Http\Controllers\v3\Whitelabel;

use App\Http\Controllers\Controller;
use App\Mail\NewMemberEmail;
use App\Models\Customer;
use App\Models\Member;
use App\Models\Restaurant;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use App\Mail\NewUserFromMemberWelcomeEmail;

class MemberController extends Controller
{
    /**
     * Register a member from the landing page.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function registerFromLandingPage(Request $request): JsonResponse
    {
        $apiCallVenueAppKey = request()->get('venue_app_key');
        if (!$apiCallVenueAppKey) {
            return response()->json(['error' => 'Venue app key is required'], 400);
        }

        $venue = Restaurant::where('app_key', $apiCallVenueAppKey)->first();
        if (!$venue) {
            return response()->json(['error' => 'Venue not found'], 404);
        }

        // Validate the request
        $validator = Validator::make($request->all(), [
            'full_name' => 'required|string|max:255',
            'email' => 'required|email|unique:members,email',
            'phone_number' => 'nullable|string|max:20',
            'preferred_brand_id' => 'nullable|exists:brands,id',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        // Split full name into first and last name
        $nameParts = explode(' ', $request->input('full_name'), 2);
        $firstName = $nameParts[0];
        $lastName = isset($nameParts[1]) ? $nameParts[1] : '';

        // Create the member
        $member = Member::create([
            'first_name' => $firstName,
            'last_name' => $lastName,
            'email' => $request->input('email'),
            'phone_number' => $request->input('phone_number') ?? '-',
            'preferred_brand_id' => $request->input('preferred_brand_id'),
            'registration_source' => 'landing_page',
            'venue_id' => $venue->id, // Associate with the venue
        ]);

        // load member with brand relationship

        $member->load('preferredBrand');

        // Send email notification to venue
        Mail::to($venue->email)->send(new NewMemberEmail($member, $venue, 'landing_page', $member->preferredBrand->title ?? null));

        return response()->json(['message' => 'Member registered successfully from landing page.', 'member' => $member], 201);
    }

    /**
     * Register a member from "My Club".
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function registerFromMyClub(Request $request): JsonResponse
    {
        $apiCallVenueAppKey = request()->get('venue_app_key');
        if (!$apiCallVenueAppKey) {
            return response()->json(['error' => 'Venue app key is required'], 400);
        }

        $venue = Restaurant::where('app_key', $apiCallVenueAppKey)->first();
        if (!$venue) {
            return response()->json(['error' => 'Venue not found'], 404);
        }

        // Validate the request
        $validator = Validator::make($request->all(), [
            'first_name' => 'required|string|max:255',
            'last_name' => 'required|string|max:255',
            'email' => 'required|email|unique:members,email',
            'city' => 'nullable|string|max:255',
            'address' => 'nullable|string|max:255',
            'birthday' => 'nullable|date',
            'phone_number' => 'nullable|string|max:20',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        // Create the member
        $member = Member::create([
            'first_name' => $request->input('first_name'),
            'last_name' => $request->input('last_name'),
            'email' => $request->input('email'),
            'phone_number' => $request->input('phone_number') ?? '-',
            'city' => $request->input('city'),
            'address' => $request->input('address'),
            'birthday' => $request->input('birthday'),
            'registration_source' => 'from_my_club',
            'venue_id' => $venue->id, // Associate with the venue
        ]);

        // Send email notification to venue
        Mail::to($venue->email)->send(new NewMemberEmail($member, $venue, 'from_my_club'));

        return response()->json(['message' => 'Member registered successfully from My Club.', 'member' => $member], 201);
    }

    public function listMembers(Request $request): JsonResponse
    {
        if (!auth()->user()->restaurants->count()) {
            return response()->json(['error' => 'User not eligible for making this API call'], 400);
        }

        $apiCallVenueShortCode = $request->get('venue_short_code');
        if (!$apiCallVenueShortCode) {
            return response()->json(['error' => 'Venue short code is required'], 400);
        }

        $venue = auth()->user()->restaurants->where('short_code', $apiCallVenueShortCode)->first();
        if (!$venue) {
            return response()->json(['error' => 'Venue not found'], 404);
        }

        $perPage = $request->get('per_page', 15);
        $page = $request->get('page', 1);

        $members = Member::where('venue_id', $venue->id)
            ->with('preferredBrand')
            ->orderBy('created_at', 'desc')
            ->paginate($perPage);

        $formattedMembers = $members->map(function ($member) {
            return [
                'id' => $member->id,
                'first_name' => $member->first_name,
                'last_name' => $member->last_name,
                'email' => $member->email,
                'phone_number' => $member->phone_number,
                'birthday' => $member->birthday ? $member->birthday->format('F d, Y') : null,
                'birthday1' => $member->birthday,
                'city' => $member->city,
                'address' => $member->address,
                'preferred_brand' => $member->preferredBrand ? $member->preferredBrand->title : null,
                'accept_terms' => $member->accept_terms,
                'registration_source' => $member->registration_source,
                'approval_status' => $this->getApprovalStatus($member),
                'old_platform_member_code'=> $member->old_platform_member_code,
                'applied_at' => $member->created_at->format('F d, Y h:i A'),
            ];
        });

        return response()->json([
            'data' => $formattedMembers,
            'current_page' => $members->currentPage(),
            'last_page' => $members->lastPage(),
            'per_page' => $members->perPage(),
            'total' => $members->total()
        ], 200);
    }

    public function listMembersOS(Request $request): JsonResponse
    {
        // Ensure the venue short code is provided
        $apiCallVenueShortCode = $request->get('venue_short_code');
        if (!$apiCallVenueShortCode) {
            return response()->json(['error' => 'Venue short code is required'], 400);
        }

        $venue = Restaurant::where('short_code', $apiCallVenueShortCode)->first();
        if (!$venue) {
            return response()->json(['error' => 'Venue not found'], 404);
        }

        // Retrieve pagination and filtering parameters
        $perPage = $request->get('per_page', 15);
        $page = $request->get('page', 1);
        $registrationSource = $request->get('registration_source');
        $search = $request->get('search');
        $status = $request->get('status'); // expected values: 'approved', 'rejected', 'pending', or 'all'

        // Build the base query for members belonging to the venue
        $baseQuery = Member::where('venue_id', $venue->id);
        $baseQuery->where('created_at', '>=', '2025-02-04 00:00:00');

        // Filter by registration source if provided and valid
        if ($registrationSource && in_array($registrationSource, ['from_my_club', 'landing_page'])) {
            $baseQuery->where('registration_source', $registrationSource);
        }

        // Apply search filtering (searching in first_name, last_name, or email)
        if ($search) {
            $baseQuery->where(function ($query) use ($search) {
                $query->where('first_name', 'LIKE', "%{$search}%")
                    ->orWhere('last_name', 'LIKE', "%{$search}%")
                    ->orWhere('email', 'LIKE', "%{$search}%");
            });
        }

        // Apply status filtering if provided and not "all"
        if ($status && $status !== 'all') {
            if ($status === 'approved') {
                $baseQuery->whereNotNull('accepted_at');
            } elseif ($status === 'rejected') {
                $baseQuery->whereNotNull('rejected_at');
            } elseif ($status === 'pending') {
                $baseQuery->whereNull('accepted_at')->whereNull('rejected_at');
            }
        }

        // Clone the query for metrics calculation
        $metricsQuery = clone $baseQuery;

        // Time references for metrics
        $now = now();
        $sevenDaysAgo = $now->copy()->subDays(7);
        $monthAgo = $now->copy()->subMonth();

        // Calculate metrics based on the filtered results
        $metrics = [
            'totalRegistrations' => (clone $baseQuery)->count(),
            'approvedUsers'      => (clone $baseQuery)->whereNotNull('accepted_at')->count(),
            'pendingUsers'       => (clone $baseQuery)->whereNull('accepted_at')->whereNull('rejected_at')->count(),
            'rejectedUsers'      => (clone $baseQuery)->whereNotNull('rejected_at')->count(),
            'recentSignups'      => (clone $baseQuery)->whereNotNull('accepted_at')
                ->where(function($query) use ($sevenDaysAgo, $now) {
                    $query->whereDate('accepted_at', '>=', $sevenDaysAgo)
                        ->whereDate('accepted_at', '<=', $now);
                })->count(),
            'trends'             => [
                'monthly' => $this->calculateMonthlyTrend($venue->id),
                'weekly'  => $this->calculateWeeklyTrend($venue->id),
                'recent'  => $this->calculateRecentTrend($venue->id)
            ]
        ];

        // Build the main query including eager loading and order by creation date
        $membersQuery = $baseQuery->with('preferredBrand')->orderBy('created_at', 'desc');

        // Paginate the results
        $members = $membersQuery->paginate($perPage);

        // Format the member data as needed
        $formattedMembers = $members->map(function ($member) {
            return [
                'id'                   => $member->id,
                'first_name'           => $member->first_name,
                'last_name'            => $member->last_name,
                'email'                => $member->email,
                'phone_number'         => $member->phone_number,
                'birthday'             => $member->birthday ? $member->birthday->format('F d, Y') : null,
                'birthday1'            => $member->birthday,
                'city'                 => $member->city,
                'address'              => $member->address,
                'preferred_brand'      => $member->preferredBrand ? $member->preferredBrand->title : null,
                'accept_terms'         => $member->accept_terms,
                'registration_source'  => $member->registration_source,
                'approval_status'      => $this->getApprovalStatus($member),
                'old_platform_member_code'=> $member->old_platform_member_code,
                'applied_at'           => $member->created_at->format('F d, Y h:i A'),
                'accepted_at'           => $member->accepted_at,
            ];
        });

        return response()->json([
            'data'         => $formattedMembers,
            'current_page' => $members->currentPage(),
            'last_page'    => $members->lastPage(),
            'per_page'     => $members->perPage(),
            'total'        => $members->total(),
            'metrics'      => $metrics
        ], 200);
    }

    public function exportMembersOS(Request $request)
    {
        // Validate required venue short code
        $apiCallVenueShortCode = $request->get('venue_short_code');
        if (!$apiCallVenueShortCode) {
            return response()->json(['error' => 'Venue short code is required'], 400);
        }

        // Find the venue by short code
        $venue = Restaurant::where('short_code', $apiCallVenueShortCode)->first();
        if (!$venue) {
            return response()->json(['error' => 'Venue not found'], 404);
        }

        // Optional filter by registration source (from_my_club or landing_page)
        $registrationSource = $request->get('registration_source');

        // Build the query similar to listMembersOS
        $membersQuery = Member::where('venue_id', $venue->id)
            ->where('created_at', '>=', '2025-02-04 00:00:00')  // Add February 4th filter
            ->with('preferredBrand');

        if ($registrationSource && in_array($registrationSource, ['from_my_club', 'landing_page'])) {
            $membersQuery->where('registration_source', $registrationSource);
        }

        $members = $membersQuery->orderBy('created_at', 'desc')->get();

        // Define CSV headers (adjust as needed)
        $headers = [
            'ID',
            'First Name',
            'Last Name',
            'Email',
            'Phone Number',
            'Birthday',
            'City',
            'Address',
            'Preferred Brand',
            'Registration Source',
            'Approval Status',
            'Applied At'
        ];

        // Create a callback to stream the CSV data
        $callback = function() use ($members, $headers) {
            $file = fopen('php://output', 'w');
            // Write CSV header row
            fputcsv($file, $headers);
            foreach ($members as $member) {
                $data = [
                    $member->id,
                    $member->first_name,
                    $member->last_name,
                    $member->email,
                    $member->phone_number,
                    $member->birthday ? $member->birthday->format('F d, Y') : '',
                    $member->city,
                    $member->address,
                    $member->preferredBrand ? $member->preferredBrand->title : '',
                    $member->registration_source,
                    $this->getApprovalStatus($member),
                    $member->created_at->format('F d, Y h:i A'),
                ];
                fputcsv($file, $data);
            }
            fclose($file);
        };

        // Define a filename using the current timestamp
        $fileName = 'members_export_' . now()->format('Ymd_His') . '.csv';

        // Return the CSV as a streamed download
        return response()->streamDownload($callback, $fileName, ['Content-Type' => 'text/csv']);
    }

    private function getApprovalStatus(Member $member): string
    {
        if ($member->accepted_at) {
            return 'approved';
        } elseif ($member->rejected_at) {
            return 'rejected';
        } else {
            return 'pending';
        }
    }


    public function acceptMember(Request $request): JsonResponse
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

        $member = Member::where('id', $request->input('member_id'))
            ->where('venue_id', $venue->id)
            ->first();

        if (!$member) {
            return response()->json(['error' => 'Member not found'], 404);
        }

        // Generate a random password
        $password = Str::random(8);

        // fixed below code
        $user = User::where('email', $member->email)->first();

        if (!$user) {
            $user = User::create([
                'name' => $member->first_name . ' ' . $member->last_name,
                'email' => $member->email,
                'password' => Hash::make($password),
                'country_code' => 'AL',
                'enduser' => true
            ]);
        }
        $customer = Customer::where('user_id', $user->id)->first();
        if(!$customer){
            $customer = Customer::create([
                'user_id' => $user->id,
                'venue_id' => $venue->id,
                'name' => $user->name,
                'email' => $user->email,
                'phone' => $member->phone_number ?? '-',
                'address' => $member->address ?? '-',
            ]);
        }
        // Create a new customer


        // Send email to new user
        Mail::to($member->email)->send(new NewUserFromMemberWelcomeEmail($user, $password));

        // Update the member
        $member->user_id = $user->id;
        $member->accepted_at = now();
        if($member->old_platform_member_code == null){
            $member->old_platform_member_code = str_pad(random_int(0, 9999999999), 13, '0', STR_PAD_LEFT);
        }
        $member->is_rejected = false;
        $member->rejection_reason = null;
        $member->rejected_at = null;
        $member->save();

        $user->old_platform_member_code = $member->old_platform_member_code;
        return response()->json(['message' => 'Member accepted and user created successfully',
            'data' => [
                'user' => $user,
                'customer' => $customer
            ]
            ], 200);
    }

    public function rejectMember(Request $request): JsonResponse
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

        $member = Member::where('id', $request->input('member_id'))
            ->where('venue_id', $venue->id)
            ->first();

        if (!$member) {
            return response()->json(['error' => 'Member not found'], 404);
        }

        // Update the member
        $member->is_rejected = true;
        $member->rejected_at = now();
        $member->rejection_reason = $request->input('rejection_reason'); // You may want to add this to your request validation
        $member->accepted_at = null;
        $member->user_id = null; // Remove association with user if any
        $member->save();

        return response()->json(['message' => 'Member rejected successfully'], 200);
    }


    public function acceptMemberOS(Request $request): JsonResponse
    {
        $apiCallVenueShortCode = $request->get('venue_short_code');
        if (!$apiCallVenueShortCode) {
            return response()->json(['error' => 'Venue short code is required'], 400);
        }

        $venue = Restaurant::where('short_code', $apiCallVenueShortCode)->first();
        if (!$venue) {
            return response()->json(['error' => 'Venue not found'], 404);
        }

        $member = Member::where('id', $request->input('member_id'))
            ->where('venue_id', $venue->id)
            ->first();

        if (!$member) {
            return response()->json(['error' => 'Member not found'], 404);
        }

        // Generate a random password
        $password = Str::random(8);

        // fixed below code
        $user = User::where('email', $member->email)->first();

        if (!$user) {
            $user = User::create([
                'name' => $member->first_name . ' ' . $member->last_name,
                'email' => $member->email,
                'password' => Hash::make($password),
                'country_code' => 'AL',
                'enduser' => true
            ]);
        }
        $customer = Customer::where('user_id', $user->id)->first();
        if(!$customer){
            $customer = Customer::create([
                'user_id' => $user->id,
                'venue_id' => $venue->id,
                'name' => $user->name,
                'email' => $user->email,
                'phone' => $member->phone_number ?? '-',
                'address' => $member->address ?? '-',
            ]);
        }
        // Create a new customer


        // Send email to new user
        Mail::to($member->email)->send(new NewUserFromMemberWelcomeEmail($user, $password));

        // Update the member
        $member->user_id = $user->id;
        $member->accepted_at = now();
        if($member->old_platform_member_code == null){
            $member->old_platform_member_code = str_pad(random_int(0, 9999999999), 13, '0', STR_PAD_LEFT);
        }
        $member->is_rejected = false;
        $member->rejection_reason = null;
        $member->rejected_at = null;
        $member->save();

        $user->old_platform_member_code = $member->old_platform_member_code;
        return response()->json(['message' => 'Member accepted and user created successfully',
            'data' => [
                'user' => $user,
                'customer' => $customer
            ]
        ], 200);
    }

    public function rejectMemberOS(Request $request): JsonResponse
    {
        $apiCallVenueShortCode = $request->get('venue_short_code');
        if (!$apiCallVenueShortCode) {
            return response()->json(['error' => 'Venue short code is required'], 400);
        }

        $venue = Restaurant::where('short_code', $apiCallVenueShortCode)->first();
        if (!$venue) {
            return response()->json(['error' => 'Venue not found'], 404);
        }

        $member = Member::where('id', $request->input('member_id'))
            ->where('venue_id', $venue->id)
            ->first();

        if (!$member) {
            return response()->json(['error' => 'Member not found'], 404);
        }

        // Update the member
        $member->is_rejected = true;
        $member->rejected_at = now();
        $member->rejection_reason = $request->input('rejection_reason'); // You may want to add this to your request validation
        $member->accepted_at = null;
        $member->user_id = null; // Remove association with user if any
        $member->save();

        return response()->json(['message' => 'Member rejected successfully'], 200);
    }

    private function calculateMonthlyTrend($venueId): float
    {
        $now = now();
        $monthAgo = $now->copy()->subMonth();
        $twoMonthsAgo = $now->copy()->subMonths(2);

        $thisMonthCount = Member::where('venue_id', $venueId)
            ->where('accepted_at', '>=', $monthAgo)
            ->count();

        $lastMonthCount = Member::where('venue_id', $venueId)
            ->whereBetween('accepted_at', [$twoMonthsAgo, $monthAgo])
            ->count();

        if ($lastMonthCount > 0) {
            return round((($thisMonthCount - $lastMonthCount) / $lastMonthCount) * 100, 1);
        }

        return $thisMonthCount > 0 ? 100 : 0;
    }

    private function calculateWeeklyTrend($venueId): float
    {
        $now = now();
        $weekAgo = $now->copy()->subWeek();
        $twoWeeksAgo = $now->copy()->subWeeks(2);

        $thisWeekCount = Member::where('venue_id', $venueId)
            ->where('accepted_at', '>=', $weekAgo)
            ->count();

        $lastWeekCount = Member::where('venue_id', $venueId)
            ->whereBetween('accepted_at', [$twoWeeksAgo, $weekAgo])
            ->count();

        if ($lastWeekCount > 0) {
            return round((($thisWeekCount - $lastWeekCount) / $lastWeekCount) * 100, 1);
        }

        return $thisWeekCount > 0 ? 100 : 0;
    }

    private function calculateRecentTrend($venueId): float
    {
        $now = now();
        $sevenDaysAgo = $now->copy()->subDays(7);
        $fourteenDaysAgo = $now->copy()->subDays(14);

        $lastWeekCount = Member::where('venue_id', $venueId)
            ->where('accepted_at', '>=', $sevenDaysAgo)
            ->count();

        $previousWeekCount = Member::where('venue_id', $venueId)
            ->whereBetween('accepted_at', [$fourteenDaysAgo, $sevenDaysAgo])
            ->count();

        if ($previousWeekCount > 0) {
            return round((($lastWeekCount - $previousWeekCount) / $previousWeekCount) * 100, 1);
        }

        return $lastWeekCount > 0 ? 100 : 0;
    }

}
