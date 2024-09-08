<?php

namespace App\Http\Controllers\v1;

use App\Http\Controllers\Controller;
use App\Models\Category;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

/**
 * @OA\Info(
 *   title="Menu Management API",
 *   version="1.0",
 *   description="This API allows use to retrieve all Menu Management related data",
 * )
 */

/**
 * @OA\Tag(
 *   name="Menu Management",
 *   description="Operations related to Menu Management"
 * )
 */


class CategoriesController extends Controller
{

    /**
     * @OA\Get(
     *     path="/menu/categories",
     *     summary="Get all categories",
     *     tags={"Menu Management"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(
     *     response=200,
     *     description="Categories retrieved successfully",
     *     @OA\JsonContent(
     *     @OA\Property(property="message", type="string", example="Categories retrieved successfully"),
     *     )
     *    ),
     *     @OA\Response(
     *     response=400,
     *     description="Restaurant not found for the user making the API call",
     *     @OA\JsonContent(
     *     @OA\Property(property="error", type="string", example="Restaurant not found for the user making the API call"),
     *     )
     *   ),
     *     @OA\Response(
     *     response=500,
     *     description="Server error",
     *     @OA\JsonContent(
     *     @OA\Property(property="message", type="string", example="Server error"),
     *     )
     *  ),
     *     @OA\Response(
     *     response=401,
     *     description="Unauthenticated",
     *     @OA\JsonContent(
     *     @OA\Property(property="message", type="string", example="Unauthenticated"),
     *     )
     * )
     * )
     */
    public function get(): \Illuminate\Http\JsonResponse
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

        try {

            $categories = Category::where('restaurant_id', $venue->id)
                ->orderBy('created_at', 'DESC')
                ->get()
                ->map(function($category) {
                    return [
                        'id' => $category->id,
                        'title' => $category->title,
                        'description' => $category->description,
                        'parent' => $category->parent // assuming you have a relation named 'parent' in Category model
                    ];
                });
            return response()->json(['message' => 'Categories retrieved successfully', 'categories' => $categories], 200);
        } catch (\Exception $e) {
            \Sentry\captureException($e);
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }

    /**
     * @OA\Post(
     *     path="/menu/categories",
     *     summary="Create a category",
     *     tags={"Menu Management"},
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *     required=true,
     *     description="Pass category details",
     *     @OA\JsonContent(
     *     required={"category"},
     *     @OA\Property(property="category", type="object",
     *     @OA\Property(property="name", type="string", example="Category name"),
     *     @OA\Property(property="description", type="string", example="Category description"),
     *     ),
     *     ),
     *     ),
     *     @OA\Response(
     *     response=200,
     *     description="Category created successfully",
     *     @OA\JsonContent(
     *     @OA\Property(property="message", type="string", example="Category created successfully"),
     *     )
     *   ),
     *     @OA\Response(
     *     response=400,
     *     description="Restaurant not found for the user making the API call",
     *     @OA\JsonContent(
     *     @OA\Property(property="error", type="string", example="Restaurant not found for the user making the API call"),
     *     )
     *  ),
     *     @OA\Response(
     *     response=500,
     *     description="Server error",
     *     @OA\JsonContent(
     *     @OA\Property(property="message", type="string", example="Server error"),
     *     )
     * ),
     *     @OA\Response(
     *     response=401,
     *     description="Unauthenticated",
     *     @OA\JsonContent(
     *     @OA\Property(property="message", type="string", example="Unauthenticated"),
     *     )
     * )
     * )
     */
    public function store(Request $request): \Illuminate\Http\JsonResponse
    {
        try {
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
                'category' => 'required',
                'category.title' => 'required|string|max:255',
            ]);

            if ($validator->fails()) {
                return response()->json(['error' => $validator->errors()], 400);
            }
            $category = $request->input('category');

            if (isset($category['id']) && $category['id']) {
                $updatedCategory =  Category::where('id', $category['id'])->where('restaurant_id', $venue->id)->first();
                if (!$updatedCategory) {
                    return response()->json(['message' => 'Category not found'], 400);
                }

                $updatedCategory->update($category);
                return response()->json(['message' => 'Category is updated successfully!'], 200);

            } else {

                $category = new Category($category);
                $category->restaurant_id = $venue->id;
                $category->save();

                return response()->json(['message' => 'Category is created successfully'], 200);
            }

        } catch (\Exception $e) {
            \Sentry\captureException($e);
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }

    /**
     * @OA\Delete (
     *     path="/menu/categories/{id}",
     *     summary="Delete a category",
     *     tags={"Menu Management"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *     name="id",
     *     in="path",
     *     description="Category id",
     *     required=true,
     *     @OA\Schema(
     *     type="integer",
     *     )
     *    ),
     *     @OA\Response(
     *     response=200,
     *     description="Category deleted successfully",
     *     @OA\JsonContent(
     *     @OA\Property(property="message", type="string", example="Category deleted successfully"),
     *     )
     *  ),
     *     @OA\Response(
     *     response=400,
     *     description="Category not found",
     *     @OA\JsonContent(
     *     @OA\Property(property="message", type="string", example="Category not found"),
     *     )
     * ),
     *     @OA\Response(
     *     response=500,
     *     description="Server error",
     *     @OA\JsonContent(
     *     @OA\Property(property="message", type="string", example="Server error"),
     *     )
     * ),
     *     @OA\Response(
     *     response=401,
     *     description="Unauthenticated",
     *     @OA\JsonContent(
     *     @OA\Property(property="message", type="string", example="Unauthenticated"),
     *     )
     * )
     * )
     */
    public function delete($id): \Illuminate\Http\JsonResponse
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

        try {
            $category = Category::where('restaurant_id', $venue->id)->find($id);
            if (!$category) {
                return response()->json(['message' => 'Category not found'], 400);
            }
            $category->delete();
            return response()->json(['message' => 'Category is deleted successfully'], 200);
        } catch (\Exception $e) {
            \Sentry\captureException($e);
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }
}
