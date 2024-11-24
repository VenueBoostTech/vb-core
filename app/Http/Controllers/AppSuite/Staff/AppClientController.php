<?php

namespace App\Http\Controllers\AppSuite\Staff;

use App\Http\Controllers\Controller;
use App\Models\Address;
use App\Models\AppClient;
use App\Models\City;
use App\Models\Country;
use App\Models\State;
use App\Models\User;
use App\Services\VenueService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

class AppClientController extends Controller
{
    protected VenueService $venueService;

    public function __construct(VenueService $venueService)
    {
        $this->venueService = $venueService;
    }

    public function listClients(Request $request): JsonResponse
    {
        $venue = $this->venueService->adminAuthCheck();
        if ($venue instanceof JsonResponse) return $venue;

        $perPage = $request->input('per_page', 15);
        $clients = AppClient::with(['address.city', 'address.state', 'address.country'])
            ->where('venue_id', $venue->id)
            ->orderBy('id', 'desc')
            ->paginate($perPage);

        // Transform the data to include full address
        $clients->getCollection()->transform(function ($client) {
            $client->full_address = $this->getFormattedAddress($client->address);
            return $client;
        });

        $paginatedData = [
            'data' => $clients->items(),
            'current_page' => $clients->currentPage(),
            'per_page' => $clients->perPage(),
            'total' => $clients->total(),
            'total_pages' => $clients->lastPage(),
        ];

        return response()->json(['clients' => $paginatedData], 200);
    }

    public function getClient($id): JsonResponse
    {
        $venue = $this->venueService->adminAuthCheck();
        if ($venue instanceof JsonResponse) return $venue;

        $client = AppClient::with(['address.city', 'address.state', 'address.country'])
            ->where('venue_id', $venue->id)
            ->findOrFail($id);

        $client->full_address = $this->getFormattedAddress($client->address);

        return response()->json($client);
    }

    public function createClient(Request $request): JsonResponse
    {
        $venue = $this->venueService->adminAuthCheck();
        if ($venue instanceof JsonResponse) return $venue;

        // Validate the incoming request
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'type' => 'required|in:company,homeowner',
            'contact_person' => 'nullable|string|max:255',
            'email' => 'nullable|email',
            'phone' => 'nullable|string|max:20',
            'address' => 'required|array',
            'address.address_line1' => 'required|string|max:255',
            'address.city_id' => 'required|exists:cities,id',
            'address.state_id' => 'required|exists:states,id',
            'address.country_id' => 'required|exists:countries,id',
            'address.postal_code' => 'required|string|max:20',
            'notes' => 'nullable|string',
        ]);

        try {
            // Start transaction
            DB::beginTransaction();

            // Create address
            $state = State::findOrFail($validated['address']['state_id']);
            $country = Country::findOrFail($validated['address']['country_id']);
            $city = City::findOrFail($validated['address']['city_id']);

            $address = Address::create([
                'address_line1' => $validated['address']['address_line1'],
                'city_id' => $city->id,
                'state_id' => $state->id,
                'country_id' => $country->id,
                'postcode' => $validated['address']['postal_code'],
                'state' => $state->name,
                'city' => $city->name,
                'country' => $country->name,
            ]);

            // Create client
            $client = AppClient::create(array_merge($validated, [
                'venue_id' => $venue->id,
                'address_id' => $address->id,
            ]));

            DB::commit();
            return response()->json($client->load('address'), 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['error' => 'Failed to create client: ' . $e->getMessage()], 500);
        }
    }

    public function updateClient(Request $request, $id): JsonResponse
    {
        $venue = $this->venueService->adminAuthCheck();
        if ($venue instanceof JsonResponse) return $venue;

        try {
            // Fetch the client and ensure it belongs to the venue
            $client = AppClient::where('venue_id', $venue->id)->findOrFail($id);

            // Validate the incoming request
            $validated = $request->validate([
                'name' => 'sometimes|required|string|max:255',
                'type' => 'sometimes|required|in:company,homeowner',
                'contact_person' => 'nullable|string|max:255',
                'email' => 'nullable|email',
                'phone' => 'nullable|string|max:20',
                'address' => 'sometimes|array',
                'address.address_line1' => 'sometimes|required|string|max:255',
                'address.city_id' => 'sometimes|required|exists:cities,id',
                'address.state_id' => 'sometimes|required|exists:states,id',
                'address.country_id' => 'sometimes|required|exists:countries,id',
                'address.postal_code' => 'sometimes|required|string|max:20',
                'notes' => 'nullable|string',
            ]);

            DB::beginTransaction();

            // Update address if provided
            if (isset($validated['address'])) {
                $state = State::findOrFail($validated['address']['state_id']);
                $country = Country::findOrFail($validated['address']['country_id']);
                $city = City::findOrFail($validated['address']['city_id']);

                // Update or create address
                if ($client->address) {
                    $client->address->update([
                        'address_line1' => $validated['address']['address_line1'],
                        'city_id' => $city->id,
                        'state_id' => $state->id,
                        'country_id' => $country->id,
                        'postcode' => $validated['address']['postal_code'],
                        'state' => $state->name,
                        'city' => $city->name,
                        'country' => $country->name,
                    ]);
                } else {
                    $address = Address::create([
                        'address_line1' => $validated['address']['address_line1'],
                        'city_id' => $city->id,
                        'state_id' => $state->id,
                        'country_id' => $country->id,
                        'postcode' => $validated['address']['postal_code'],
                        'state' => $state->name,
                        'city' => $city->name,
                        'country' => $country->name,
                    ]);
                    $client->address_id = $address->id;
                }
            }

            // Update client details
            $client->update($validated);
            DB::commit();

            return response()->json($client->load('address'));
        } catch (ModelNotFoundException $e) {
            return response()->json(['error' => 'Client not found'], 404);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['error' => 'Failed to update client: ' . $e->getMessage()], 500);
        }
    }

    public function deleteClient($id): JsonResponse
    {
        $venue = $this->venueService->adminAuthCheck();
        if ($venue instanceof JsonResponse) return $venue;

        try {
            $client = AppClient::where('venue_id', $venue->id)->findOrFail($id);
            $client->delete();

            return response()->json(['message' => 'Client soft deleted successfully'], 200);
        } catch (ModelNotFoundException $e) {
            return response()->json(['error' => 'Client not found'], 404);
        }
    }

    private function getFormattedAddress($address): string
    {
        if (!$address) return '';

        $parts = [
            $address->address_line1,
            $address->city,
            $address->state,
            $address->country,
            $address->postcode
        ];

        return implode(', ', array_filter($parts));
    }

    public function createClientUser(Request $request): JsonResponse
    {
        $venue = $this->venueService->adminAuthCheck();
        if ($venue instanceof JsonResponse) return $venue;

        $validator = Validator::make($request->all(), [
            // Client Details
            'name' => 'required|string|max:255',
            'type' => 'required|in:company,homeowner',
            'contact_person' => 'nullable|string|max:255',
            'email' => 'required|email|unique:users,email',
            'phone' => 'nullable|string|max:20',
            'notes' => 'nullable|string',
            'country_code' => 'required|string',

            // Address Details
            'address' => 'required|array',
            'address.address_line1' => 'required|string|max:255',
            'address.city_id' => 'required|exists:cities,id',
            'address.state_id' => 'required|exists:states,id',
            'address.country_id' => 'required|exists:countries,id',
            'address.postal_code' => 'required|string|max:20',

            // User Account Details
            'password' => 'required|string|min:8'
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        try {
            DB::beginTransaction();

            // Create user account
            $user = User::create([
                'name' => $request->name,
                'email' => $request->email,
                'password' => Hash::make($request->password),
                'country_code' => $request->country_code,
                'is_app_client' => true
            ]);

            // Create address
            $state = State::findOrFail($request->address['state_id']);
            $country = Country::findOrFail($request->address['country_id']);
            $city = City::findOrFail($request->address['city_id']);

            $address = Address::create([
                'address_line1' => $request->address['address_line1'],
                'city_id' => $city->id,
                'state_id' => $state->id,
                'country_id' => $country->id,
                'postcode' => $request->address['postal_code'],
                'state' => $state->name,
                'city' => $city->name,
                'country' => $country->name,
            ]);

            // Create client record
            $client = AppClient::create([
                'name' => $request->name,
                'type' => $request->type,
                'contact_person' => $request->contact_person,
                'email' => $request->email,
                'phone' => $request->phone,
                'address_id' => $address->id,
                'venue_id' => $venue->id,
                'notes' => $request->notes,
                'user_id' => $user->id
            ]);

            DB::commit();

            return response()->json([
                'message' => 'Client user created successfully',
                'client' => [
                    'id' => $client->id,
                    'name' => $client->name,
                    'email' => $client->email,
                    'user_id' => $client->user_id
                ]
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['error' => 'Failed to create client user: ' . $e->getMessage()], 500);
        }
    }

    public function connectExistingUser(Request $request): JsonResponse
    {
        $venue = $this->venueService->adminAuthCheck();
        if ($venue instanceof JsonResponse) return $venue;

        $validator = Validator::make($request->all(), [
            'client_id' => 'required|exists:app_clients,id',
            'user_id' => 'required|exists:users,id',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        try {
            $client = AppClient::where('venue_id', $venue->id)->findOrFail($request->client_id);
            $user = User::findOrFail($request->user_id);

            if ($user->is_app_client) {
                return response()->json(['error' => 'User is already connected to a client'], 400);
            }

            $user->update(['is_app_client' => true]);
            $client->update(['user_id' => $user->id]);

            return response()->json([
                'message' => 'User connected to client successfully',
                'client' => [
                    'id' => $client->id,
                    'name' => $client->name,
                    'user_id' => $client->user_id
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed to connect user: ' . $e->getMessage()], 500);
        }
    }

}
