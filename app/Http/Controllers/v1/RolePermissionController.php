<?php
namespace App\Http\Controllers\v1;
use App\Http\Controllers\Controller;
use App\Models\Role;
use App\Models\VenueType;
use function response;

/**
 * @OA\Info(
 *   title="Staff Management API",
 *   version="1.0",
 *   description="This API allows use Staff Management Related API for Venue Boost"
 * )
 */

/**
 * @OA\Tag(
 *   name="Staff Management",
 *   description="Operations related to Staff Management"
 * )
 */


class RolePermissionController extends Controller
{
    /**
     * @OA\Get(
     *      path="/staff/roles",
     *      operationId="listRoles",
     *      tags={"Staff Management"},
     *      summary="List roles and their permissions",
     *      description="Returns a list of all roles and their permissions",
     *      @OA\Response(response=200, description="successful operation"),
     *      @OA\Response(response=401, description="unauthorized"),
     *      @OA\Response(response=500, description="internal server error"),
     * )
     */
    public function index(): \Illuminate\Http\JsonResponse
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

        $venueType = VenueType::where('id', $venue->venue_type)->first();
        $roleType = 'restaurant_hierarchy';

        if ($venueType->name === 'Hotel') {
            $roleType = 'hotel_hierarchy';
        }

        if ($venueType->definition === 'retail') {
            $roleType = 'retail_hierarchy';
        }

        if ($venueType->definition === 'accommodation' && $venueType->name !== 'Hotel') {
            $roleType = 'vacation_rental_hierarchy';
        }

        if ($venueType->short_name === 'golf_hierarchy') {
            $roleType = 'golf_hierarchy';
        }

        // todo: add more for entertainment venues

        $roles = Role::where('role_type', $roleType)->with('permissions')->get();

        return response()->json(['data' => $roles], 200);
    }
}
