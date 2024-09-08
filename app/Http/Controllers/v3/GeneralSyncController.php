<?php

namespace App\Http\Controllers\v3;

use App\Http\Controllers\Controller;

use App\Models\CustomerAddress;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use App\Models\User;
use App\Models\Customer;
use App\Models\Member;
use App\Models\Address;
use App\Models\City;
use App\Models\Country;
use Illuminate\Support\Facades\DB;

class GeneralSyncController extends Controller
{
    public function syncUsersFromBB(Request $request): \Illuminate\Http\JsonResponse
    {
        $page = 1;
        $perPage = 100;
        $skippedUsers = 0;
        $skippedCustomers = 0;
        $syncedUsers = 0;

        do {
            $response = Http::withHeaders([
                'X-App-Key' => 'sync.venueboost.io',
            ])->get('https://bybest.shop/api/V1/sync-for-vb', [
                'page' => $page,
                'per_page' => $perPage,
            ]);

            if ($response->successful()) {
                $userData = $response->json('data');

                foreach ($userData as $oldUser) {
                    DB::transaction(function () use ($oldUser, &$skippedUsers, &$skippedCustomers, &$syncedUsers) {
                        // Check if user exists
                        $existingUser = User::where('email', $oldUser['email'])->first();
                        if ($existingUser) {
                            $skippedUsers++;
                            $user = $existingUser;
                        } else {
                            $user = $this->syncUser($oldUser);
                            $syncedUsers++;
                        }

                        // Sync Customer
                        $existingCustomer = Customer::where('email', $user->email)->first();
                        if ($existingCustomer) {
                            $skippedCustomers++;
                            $customer = $existingCustomer;
                        } else {
                            $customer = $this->syncCustomer($user, $oldUser);
                        }

                        // Sync Address
                        $this->syncAddress($customer, $oldUser);

                        // Sync Member
                        if ($oldUser['bb_member_code']) {
                            $this->syncMember($user, $oldUser);
                        }
                    });
                }

                $page++;
            } else {
                return response()->json(['message' => 'Failed to fetch data from old system'], 500);
            }
        } while (count($userData) == $perPage);

        return response()->json([
            'message' => 'Sync completed successfully',
            'synced_users' => $syncedUsers,
            'skipped_users' => $skippedUsers,
            'skipped_customers' => $skippedCustomers
        ]);
    }

    private function syncUser($oldUser)
    {
        $gender = $oldUser['gender_en'] ?? null;
        $status = $oldUser['status_id'] == 3 ? 1 : 0;

        // Validate and sanitize email_verified_at
        $emailVerifiedAt = null;
        if (!empty($oldUser['email_verified_at'])) {
            try {
                $emailVerifiedAt = new \DateTime($oldUser['email_verified_at']);
                if ($emailVerifiedAt->format('Y') < 1970) {
                    $emailVerifiedAt = null;
                }
            } catch (\Exception $e) {
                // Invalid date, keep it null
            }
        }

        // Check if user with this email already exists
        $existingUser = User::where('email', $oldUser['email'])->first();
        if ($existingUser) {
            // If user exists, update the old_platform_user_id and return
            $existingUser->update(['old_platform_user_id' => $oldUser['id']]);
            return $existingUser;
        }

        // If user doesn't exist, create a new one
        return User::create([
            'old_platform_user_id' => $oldUser['id'],
            'first_name' => $oldUser['name'],
            'last_name' => $oldUser['surname'],
            'name' => $oldUser['name'] . ' ' . $oldUser['surname'],
            'email' => $oldUser['email'],
            'username' => $oldUser['username'],
            'password' => $oldUser['password'],
            'country_code' => 'AL',
            'gender' => $gender,
            'profile_photo_path' => $oldUser['profile_photo_path'],
            'enduser' => true,
            'email_verified_at' => $emailVerifiedAt,
            'old_platform_registration_type' => $oldUser['registrationType'],
            'company_name' => $oldUser['company_name'] ?? null,
            'company_vat' => $oldUser['company_vat'] ?? null,
            'status' => $status,
            'deleted_at' => $oldUser['deleted_at'],
            'created_at' => $oldUser['created_at'],
        ]);
    }

    private function syncCustomer($user, $oldUser)
    {
        // Check if a customer with this email already exists
        $existingCustomer = Customer::where('email', $user->email)->first();

        if ($existingCustomer) {
            // If customer exists, update the user_id if it's different and return
            if ($existingCustomer->user_id !== $user->id) {
                $existingCustomer->update(['user_id' => $user->id]);
            }
            return $existingCustomer;
        }

        // If customer doesn't exist, create a new one
        return Customer::create([
            'user_id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'address' => '-',
            'phone' => $oldUser['phone_number'] ?? '-',
            'venue_id' => 58, // or whatever default venue_id you want to use
        ]);
    }

    private function syncAddress($customer, $oldUser)
    {
        try {
            $country = Country::where('name', 'Albania')->first();

            // Assuming the city name in the old system matches the 'name' field in the new system
            $city = City::where('name', $oldUser['city_en'])->first();

            // If city is not found, try to find it in the name_translations
            if (!$city) {
                $city = City::whereRaw("JSON_EXTRACT(name_translations, '$.en') = ?", [$oldUser['city_en']])->first();
            }

            $state = $city ? $city->state : null;

            // Provide default values if city or state is missing
            $cityName = $city ? $city->name : ($oldUser['city_en'] ?? '-');
            $stateName = $state ? $state->name : '-';

            // Create or update the address
            $address = Address::updateOrCreate(
                [
                    'customer_id' => $customer->id,
                ],
                [
                    'city' => $cityName,
                    'state' => $stateName,
                    'country' => $country ? $country->name : 'Albania',
                    'address_line1' => $oldUser['address'] ?? '-',
                    'postcode' => $oldUser['zip_code'] ?? '-',
                    'is_for_retail' => true,
                    'city_id' => $city ? $city->id : null,
                    'state_id' => $state ? $state->id : null,
                    'country_id' => $country ? $country->id : null,
                    'active' => true,
                ]
            );

            // Link the address to the customer
            CustomerAddress::updateOrCreate(
                ['customer_id' => $customer->id],
                ['address_id' => $address->id]
            );
        } catch (\Exception $e) {
            \Log::error('Error syncing address for customer ' . $customer->id . ': ' . $e->getMessage());
            \Log::error('Old user data: ' . json_encode($oldUser));
            // You might want to throw the exception here, or handle it in some other way
            // throw $e;
        }
    }

    private function syncMember($user, $oldUser)
    {
        $status = $oldUser['bb_member_status'] == 'Aktiv' ? 'accepted' : 'rejected';
        $statusDate = $status == 'accepted' ? 'accepted_at' : 'rejected_at';

        Member::updateOrCreate(
            ['user_id' => $user->id],
            [
                'first_name' => $user->first_name,
                'last_name' => $user->last_name,
                'email' => $user->email,
                'phone_number' => $oldUser['bb_member_contact'] ?? '-',
                'birthday' => $oldUser['bb_member_birthday'],
                'city' => $oldUser['bb_member_city'],
                'address' => $oldUser['bb_member_address'],
                'venue_id' => 10,
                'old_platform_member_code' => $oldUser['bb_member_code'],
                $statusDate => now(),
                'is_rejected' => $status == 'rejected',
            ]
        );
    }
}
