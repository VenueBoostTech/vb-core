<?php

namespace App\Http\Controllers\v2;

use App\Http\Controllers\Controller;
use App\Models\Address;
use App\Models\City;
use App\Models\Country;
use App\Models\EcommercePlatform;
use App\Models\InventoryWarehouse;
use App\Models\PhysicalStore;
use App\Models\Restaurant;
use App\Models\State;
use App\Models\Product;
use App\Models\InventoryRetail;
use App\Services\VenueService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use function now;
use function response;

class InventoryConfigurationController extends Controller
{

    protected VenueService $venueService;

    public function __construct(VenueService $venueService)
    {
        $this->venueService = $venueService;
    }

    private function generateCode($prefix): string
    {
        return $prefix . '-' . Str::substr(Str::uuid(), 0, 8) . '-' . now()->format('Ymd');
    }

    // Inventory Warehouses

    public function listWarehouses(): \Illuminate\Http\JsonResponse
    {
        $venue = $this->venueService->adminAuthCheck();
        // The $venue can be response or object if response then return it else return object
        if($venue instanceof \Illuminate\Http\JsonResponse){
            return $venue;
        }
        if (!$venue) {
            return response()->json(['error' => 'Venue not found'], 404);
        }
        
        $warehouses = InventoryWarehouse::where('venue_id', $venue->id)->with('address', 'inventoryRetails')->get();
        $warehouses = $warehouses->map(function ($warehouse) {
            $total_products = 0;
            $total_stock = 0;
            if ($warehouse->inventoryRetails) {
                $total_products = count($warehouse->inventoryRetails);
                foreach ($warehouse->inventoryRetails as $item) {
                    $total_stock = $total_stock + $item->stock_quantity;
                }
            }
            
            return [
                'id' => $warehouse->id,
                'address' => $warehouse->address,
                'description' => $warehouse->description,
                'name' => $warehouse->name,
                'total_products' => $total_products,
                'total_stock' => $total_stock,
            ];
        });
        return response()->json($warehouses);
    }

    public function createWarehouse(Request $request): \Illuminate\Http\JsonResponse
    {
        $venue = $this->venueService->adminAuthCheck();
        $venueId = $venue->id;

        $validator = Validator::make($request->all(), [
            'name' => 'required|string',
            'country_id' => 'required|exists:countries,id',
            'state_id' => 'required|exists:states,id',
            'city_id' => 'required|exists:cities,id',
            'postcode' => 'required|string',
            'address_line1' => 'required|string',
            'address_line2' => 'nullable|string',
            'description' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        DB::beginTransaction();

        try {
            $validatedData = $validator->validated();

            // Fetch country, state, and city names
            $country = Country::findOrFail($validatedData['country_id']);
            $state = State::findOrFail($validatedData['state_id']);
            $city = City::findOrFail($validatedData['city_id']);

            $address = Address::create([
                'country_id' => $validatedData['country_id'],
                'state_id' => $validatedData['state_id'],
                'city_id' => $validatedData['city_id'],
                'country' => $country->name,
                'state' => $state->name,
                'city' => $city->name,
                'postcode' => $validatedData['postcode'],
                'address_line1' => $validatedData['address_line1'],
                'address_line2' => $validatedData['address_line2'],
            ]);

            $warehouse = InventoryWarehouse::create([
                'venue_id' => $venueId,
                'name' => $validator->validated()['name'],
                'description' => $validatedData['description'],
                'code' => $this->generateCode('IW'),
                'address_id' => $address->id,
            ]);

            Restaurant::findOrFail($venueId)->increment('inventory_warehouses');

            DB::commit();
            return response()->json($warehouse->load('address'), 201);
        } catch (\Exception $e) {
            DB::rollBack();
            $errorMessage = $e->getMessage();
            return response()->json(['error' =>  'An error occurred: ' . $errorMessage], 500);
        }
    }

    public function updateWarehouse(Request $request, $warehouseId): \Illuminate\Http\JsonResponse
    {
        $venue = $this->venueService->adminAuthCheck();
        $venueId = $venue->id;

        $validator = Validator::make($request->all(), [
            'name' => 'required|string',
            'country_id' => 'required|exists:countries,id',
            'state_id' => 'required|exists:states,id',
            'city_id' => 'required|exists:cities,id',
            'postcode' => 'required|string',
            'address_line1' => 'required|string',
            'address_line2' => 'nullable|string',
            'description' => 'nullable|string',
        ]);

        // check if belongs to venue
        $warehouse = InventoryWarehouse::where('venue_id', $venueId)->find($warehouseId);

        if (!$warehouse) {
            return response()->json(['error' => 'Warehouse not found'], 404);
        }

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        DB::beginTransaction();

        try {
            $warehouse = InventoryWarehouse::findOrFail($warehouseId);
            $warehouse->update(['name' => $validator->validated()['name'], 'description' => $validator->validated()['description']]);

            $warehouse->address->update($validator->validated());

            DB::commit();
            return response()->json($warehouse->load('address'));
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['error' => 'Failed to update warehouse'], 500);
        }
    }

    public function deleteWarehouse($warehouseId): \Illuminate\Http\JsonResponse
    {
        $venue = $this->venueService->adminAuthCheck();
        $venueId = $venue->id;

        DB::beginTransaction();

        try {
            // check if belongs to venue
            $warehouse = InventoryWarehouse::where('venue_id', $venueId)->find($warehouseId);
            $warehouse->delete();
            $venue->decrement('inventory_warehouses');

            DB::commit();
            return response()->json(['message' => 'Warehouse deleted successfully']);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['error' => 'Failed to delete warehouse'], 500);
        }
    }

    // Physical Stores

    public function listStores(): \Illuminate\Http\JsonResponse
    {
        $venue = $this->venueService->adminAuthCheck();
        $venueId = $venue->id;

        $stores = PhysicalStore::where('venue_id', $venueId)->with('address')->get();

        $nProducts = Product::where('restaurant_id', $venueId)->count();
        $totalStock = InventoryRetail
            ::leftJoin('products', 'inventory_retail.product_id', '=', 'products.id')
            ->leftJoin('brands', 'brands.id', '=', 'products.brand_id')
            ->where('inventory_retail.venue_id', $venue->id)
            ->sum('inventory_retail.stock_quantity');
            
        $stores = $stores->map(function ($store) use ($nProducts, $totalStock){
            return [
                'id' => $store->id,
                'address' => $store->address,
                'name' => $store->name,
                'total_products' => $nProducts,
                'total_stock' => $totalStock,
            ];
        });

        return response()->json($stores);
    }

    public function createStore(Request $request): \Illuminate\Http\JsonResponse
    {
        $venue = $this->venueService->adminAuthCheck();
        $venueId = $venue->id;

        $validator = Validator::make($request->all(), [
            'name' => 'required|string',
            'country_id' => 'required|exists:countries,id',
            'state_id' => 'required|exists:states,id',
            'city_id' => 'required|exists:cities,id',
            'postcode' => 'required|string',
            'address_line1' => 'required|string',
            'address_line2' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        DB::beginTransaction();

        try {
            $validatedData = $validator->validated();

            // Fetch country, state, and city names
            $country = Country::findOrFail($validatedData['country_id']);
            $state = State::findOrFail($validatedData['state_id']);
            $city = City::findOrFail($validatedData['city_id']);

            $address = Address::create([
                'country_id' => $validatedData['country_id'],
                'state_id' => $validatedData['state_id'],
                'city_id' => $validatedData['city_id'],
                'country' => $country->name,
                'state' => $state->name,
                'city' => $city->name,
                'postcode' => $validatedData['postcode'],
                'address_line1' => $validatedData['address_line1'],
                'address_line2' => $validatedData['address_line2'],
            ]);

            $store = PhysicalStore::create([
                'venue_id' => $venueId,
                'name' => $validator->validated()['name'],
                'code' => $this->generateCode('PS'),
                'address_id' => $address->id,
            ]);

            Restaurant::findOrFail($venueId)->increment('physical_stores');

            DB::commit();
            return response()->json($store->load('address'), 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['error' => 'Failed to create store'], 500);
        }
    }

    public function updateStore(Request $request, $storeId): \Illuminate\Http\JsonResponse
    {
        $venue = $this->venueService->adminAuthCheck();
        $venueId = $venue->id;

        $validator = Validator::make($request->all(), [
            'name' => 'required|string',
            'country_id' => 'required|exists:countries,id',
            'state_id' => 'required|exists:states,id',
            'city_id' => 'required|exists:cities,id',
            'postcode' => 'required|string',
            'address_line1' => 'required|string',
            'address_line2' => 'nullable|string',
        ]);

        // check if belongs to venue

        $store = PhysicalStore::where('venue_id', $venueId)->find($storeId);

        if (!$store) {
            return response()->json(['error' => 'Store not found'], 404);
        }

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        DB::beginTransaction();

        try {
            $store = PhysicalStore::findOrFail($storeId);
            $store->update(['name' => $validator->validated()['name']]);

            $store->address->update($validator->validated());

            DB::commit();
            return response()->json($store->load('address'));
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['error' => 'Failed to update store'], 500);
        }
    }

    public function deleteStore($storeId): \Illuminate\Http\JsonResponse
    {
        $venue = $this->venueService->adminAuthCheck();
        $venueId = $venue->id;

        DB::beginTransaction();

        try {
            // check if belongs to venue
            $store = PhysicalStore::where('venue_id', $venueId)->find($storeId);
            $store->delete();
            $venue->decrement('physical_stores');

            DB::commit();
            return response()->json(['message' => 'Store deleted successfully']);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['error' => 'Failed to delete store'], 500);
        }
    }


    public function listEcommercePlatforms(): \Illuminate\Http\JsonResponse
    {
        $venue = $this->venueService->adminAuthCheck();
        $venueId = $venue->id;

        $platforms = EcommercePlatform::where('venue_id', $venueId)->get();

        $nProducts = Product::where('restaurant_id', $venueId)->count();
        $totalStock = InventoryRetail
            ::leftJoin('products', 'inventory_retail.product_id', '=', 'products.id')
            ->leftJoin('brands', 'brands.id', '=', 'products.brand_id')
            ->where('inventory_retail.venue_id', $venue->id)
            ->sum('inventory_retail.stock_quantity');
            
        $platforms = $platforms->map(function ($platform) use ($nProducts, $totalStock){
            return [
                'id' => $platform->id,
                'name' => $platform->name,
                'url' => $platform->url,
                'type' => $platform->type,
                'total_products' => $nProducts,
                'total_stock' => $totalStock,
            ];
        });
        return response()->json($platforms);
    }

    public function createEcommercePlatform(Request $request): \Illuminate\Http\JsonResponse
    {
        $venue = $this->venueService->adminAuthCheck();
        $venueId = $venue->id;

        $validator = Validator::make($request->all(), [
            'name' => 'required|string',
            'url' => 'required|url',
            'type' => 'required|in:shopify,woocommerce,magento,custom,other',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $data = $validator->validated();



        $platform = EcommercePlatform::create([
            'venue_id' => $venueId,
            'name' => $data['name'],
            'url' => $data['url'],
            'type' => $data['type'],
        ]);


        $venue = Restaurant::findOrFail($venueId);
        $venue->update(['has_ecommerce' => true]);

        return response()->json($platform, 201);
    }

    public function updateEcommercePlatform(Request $request, $platformId): \Illuminate\Http\JsonResponse
    {
        $venue = $this->venueService->adminAuthCheck();
        $venueId = $venue->id;

        $platform = EcommercePlatform::where('venue_id', $venueId)->find($platformId);

        // check if belongs to venue
        if (!$platform) {
            return response()->json(['error' => 'Platform not found'], 404);
        }

        $validator = Validator::make($request->all(), [
            'name' => 'required|string',
            'url' => 'required|url',
            'type' => 'required|in:shopify,woocommerce,magento,custom,other',
        ]);
        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $data = $validator->validated();

        $platform = EcommercePlatform::findOrFail($platformId);
        $platform->update($data);

        return response()->json($platform);
    }

    public function deleteEcommercePlatform($platformId): \Illuminate\Http\JsonResponse
    {
        $venue = $this->venueService->adminAuthCheck();
        $venueId = $venue->id;
        $platform = EcommercePlatform::where('venue_id', $venueId)->find($platformId);

        $platform->delete();

        $finalVenue = Restaurant::findOrFail($venueId);
        // Check if the vendor has any remaining e-commerce platforms
        if ($finalVenue->ecommercePlatforms()->count() == 0) {
            $finalVenue->update(['has_ecommerce' => false]);
        }

        return response()->json(['message' => 'E-commerce platform deleted successfully']);
    }
}
