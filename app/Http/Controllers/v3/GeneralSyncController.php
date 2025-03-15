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
    // Helper function to validate dates
    private function validateDate($date, $type = 'regular', $fallbackDate = null) {
        // Early return if empty and it's a birthday (should be null)
        if ($type === 'birthday' && (empty($date) || $date === '0000-00-00 00:00:00')) {
            return null;
        }

        // For regular dates, check if it's the zero date
        if ($date === '0000-00-00 00:00:00') {
            return $fallbackDate ?? now();
        }

        try {
            $dateObj = new \DateTime($date);

            // For birthdays, if it's a valid date, return it regardless of year
            if ($type === 'birthday' && $dateObj) {
                return $dateObj;
            }

            // For other dates, validate year is after 1970
            if ($dateObj->format('Y') > 1970) {
                return $dateObj;
            }

            // If date is invalid, return fallback or now()
            return $fallbackDate ?? now();
        } catch (\Exception $e) {
            if ($type === 'birthday') {
                return null;
            }
            return $fallbackDate ?? now();
        }
    }

    public function syncUsersFromBB(Request $request): \Illuminate\Http\JsonResponse
    {
        try {
            DB::beginTransaction();

            // 1. Get users to delete
            $usersToDelete = User::where(function($query) {
                $query->whereNotNull('old_platform_user_id')
                    ->orWhereHas('member', function($q) {
                        $q->whereNotNull('old_platform_member_code');
                    });
            })->pluck('id');

            // 2. Get customers to delete
            $customersToDelete = Customer::whereIn('user_id', $usersToDelete)->pluck('id');

            // 3. Delete chats first
            DB::table('chats')->whereIn('sender_id', $usersToDelete)
                ->orWhereIn('receiver_id', $usersToDelete)
                ->delete();

            // 4. Delete orders and related data
            $ordersToDelete = DB::table('orders')
                ->whereIn('customer_id', $customersToDelete)
                ->pluck('id');

            // Delete order_status_changes first to fix the foreign key constraint
            DB::table('order_status_changes')->whereIn('order_id', $ordersToDelete)->delete();

            // Then delete other order-related tables
            DB::table('order_coupons')->whereIn('order_id', $ordersToDelete)->delete();
            DB::table('order_discounts')->whereIn('order_id', $ordersToDelete)->delete();
            DB::table('order_products')->whereIn('order_id', $ordersToDelete)->delete();
            DB::table('order_deliveries')->whereIn('order_id', $ordersToDelete)->delete();
            DB::table('orders')->whereIn('id', $ordersToDelete)->delete();

            // 5. Delete addresses
            CustomerAddress::whereIn('customer_id', $customersToDelete)->delete();
            Address::whereIn('id', function($query) use ($customersToDelete) {
                $query->select('address_id')
                    ->from('customer_addresses')
                    ->whereIn('customer_id', $customersToDelete);
            })->delete();

            // 6. Finally delete customers, members and users
            Customer::whereIn('id', $customersToDelete)->delete();
            Member::whereIn('user_id', $usersToDelete)->delete();
            User::whereIn('id', $usersToDelete)->delete();

            DB::commit();

            // Initialize error arrays
            $duplicateErrors = [];
            $otherErrors = [];

            // SYNC PHASE
            $page = 1;
            $perPage = 100;
            $syncedCount = 0;
            $skippedCount = 0;
            $memberCount = 0;

            // Get first page to get total
            $response = Http::withHeaders([
                'X-App-Key' => 'sync.venueboost.io',
            ])->get('https://bybest.shop/api/V1/sync-for-vb', [
                'page' => $page,
                'per_page' => $perPage,
            ]);

            if (!$response->successful()) {
                throw new \Exception('Failed to fetch data from old system');
            }

            $totalUsers = $response->json('total');
            $totalPages = ceil($totalUsers / $perPage);

            while ($page <= $totalPages) {
                if ($page > 1) {
                    $response = Http::withHeaders([
                        'X-App-Key' => 'sync.venueboost.io',
                    ])->get('https://bybest.shop/api/V1/sync-for-vb', [
                        'page' => $page,
                        'per_page' => $perPage,
                    ]);

                    if (!$response->successful()) {
                        throw new \Exception("Failed to fetch data from old system on page $page");
                    }
                }

                $userData = $response->json('data');
                if (empty($userData)) {
                    \Log::warning("Empty data received on page $page");
                    break;
                }

                foreach ($userData as $oldUser) {
                    DB::beginTransaction();
                    try {
                        // Check if user exists by email
                        $existingUser = User::where('email', $oldUser['email'])->first();

                        if ($existingUser) {
                            $skippedCount++;
                            DB::commit();
                            continue;
                        }

                        // Create user
                        $user = $this->syncUser($oldUser);

                        // Create customer
                        $customer = $this->syncCustomer($user, $oldUser);

                        // Create member if applicable
                        if ($oldUser['bb_member_code'] !== '') {
                            $this->syncMember($user, $oldUser);
                            $memberCount++;
                        }

                        $syncedCount++;
                        DB::commit();

                    } catch (\Exception $e) {
                        DB::rollBack();
                        $errorData = [
                            'email' => $oldUser['email'] ?? 'unknown',
                            'old_user_id' => $oldUser['id'] ?? 'unknown',
                            'message' => $e->getMessage(),
                            'trace' => $e->getTraceAsString()
                        ];

                        if (str_contains($e->getMessage(), 'Duplicate entry')) {
                            $duplicateErrors[] = array_merge($errorData, ['type' => 'duplicate_constraint']);
                        } else {
                            $otherErrors[] = array_merge($errorData, ['type' => 'sync_error']);
                        }

                        \Log::error('Error syncing user', $errorData);
                    }
                }

                \Log::info("Processed page $page of $totalPages. Progress: " . round(($page/$totalPages) * 100, 2) . "%");
                $page++;
            }

            return response()->json([
                'message' => 'Sync completed successfully',
                'total_users' => $totalUsers,
                'total_pages' => $totalPages,
                'pages_processed' => $page - 1,
                'deleted_users' => count($usersToDelete),
                'synced_users' => $syncedCount,
                'skipped_users' => $skippedCount,
                'errors' => [
                    'duplicate_errors' => [
                        'count' => count($duplicateErrors),
                        'details' => $duplicateErrors
                    ],
                    'other_errors' => [
                        'count' => count($otherErrors),
                        'details' => $otherErrors
                    ]
                ],
                'synced_members' => $memberCount,
                'total_processed' => $syncedCount + $skippedCount + count($duplicateErrors) + count($otherErrors)
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            \Log::error('Sync failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return response()->json([
                'message' => 'Sync failed: ' . $e->getMessage(),
                'type' => 'system_error'
            ], 500);
        }
    }

    private function syncUser($oldUser)
    {
        $user = new User;
        $user->timestamps = false;

        $gender = $oldUser['gender_en'] ?? null;
        $status = $oldUser['status_id'] == 3 ? 1 : 0;

        // For created_at, use updated_at as fallback if created_at is zero
        $updatedAt = $this->validateDate($oldUser['updated_at']);
        $createdAt = $this->validateDate($oldUser['created_at'], 'regular', $updatedAt);

        // Other dates
        $deletedAt = !empty($oldUser['deleted_at']) ? $this->validateDate($oldUser['deleted_at']) : null;
        $emailVerifiedAt = !empty($oldUser['email_verified_at']) ? $this->validateDate($oldUser['email_verified_at']) : null;

        $user->fill([
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
            'deleted_at' => $deletedAt,
            'created_at' => $createdAt,
            'updated_at' => $updatedAt
        ]);

        $user->save();
        return $user;
    }

    private function syncCustomer($user, $oldUser)
    {
        $customer = new Customer;
        $customer->timestamps = false;

        // Use updated_at as fallback for created_at if needed
        $updatedAt = $this->validateDate($oldUser['updated_at']);
        $createdAt = $this->validateDate($oldUser['created_at'], 'regular', $updatedAt);

        $customer->fill([
            'user_id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'address' => '-',
            'phone' => $oldUser['phone_number'] ?? '-',
            'venue_id' => 58,
            'created_at' => $createdAt,
            'updated_at' => $updatedAt
        ]);

        $customer->save();
        return $customer;
    }

    private function syncMember($user, $oldUser)
    {
        $status = $oldUser['bb_member_status'] == 'Aktiv' ? 'accepted' : 'rejected';
        $member = new Member;
        $member->timestamps = false;

        // Use updated_at as fallback for created_at if needed
        $updatedAt = $this->validateDate($oldUser['updated_at']);
        $createdAt = $this->validateDate($oldUser['created_at'], 'regular', $updatedAt);

        // Birthday handling - will return null if invalid
        $birthday = !empty($oldUser['bb_member_birthday']) ?
            $this->validateDate($oldUser['bb_member_birthday'], 'birthday') : null;

        $member->fill([
            'user_id' => $user->id,
            'first_name' => $user->first_name,
            'last_name' => $user->last_name,
            'email' => $user->email,
            'phone_number' => $oldUser['bb_member_contact'] ?? '-',
            'birthday' => $birthday,
            'city' => $oldUser['bb_member_city'],
            'address' => $oldUser['bb_member_address'],
            'venue_id' => 58,
            'old_platform_member_code' => $oldUser['bb_member_code'],
            'accepted_at' => $status == 'accepted' ? now() : null,
            'rejected_at' => $status == 'rejected' ? now() : null,
            'is_rejected' => $status == 'rejected',
            'created_at' => $createdAt,
            'updated_at' => $updatedAt
        ]);

        $member->save();
        return $member;
    }

    public function countJobs(): \Illuminate\Http\JsonResponse
    {
        $jobs = DB::table('jobs')->count();
        $failedJobs = DB::table('failed_jobs')->count();

        return response()->json([
            'jobs' => $jobs,
            'failed_jobs' => $failedJobs
        ]);
    }

    public function listGroups(): \Illuminate\Http\JsonResponse
    {
        $groups = DB::table('groups')->get();
        return response()->json($groups);
    }
}
