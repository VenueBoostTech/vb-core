<?php

namespace App\Http\Controllers\v1;

use App\Http\Controllers\Controller;
use App\Models\FaqCat;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use function response;

/**
 * @OA\Info(
 *   title="FAQs API",
 *   version="1.0",
 *   description="This API allows users to retrieve and manage FAQs and FAQ categories.",
 * )
 */

/**
 * @OA\Tag(
 *   name="FAQs",
 *   description="Operations related to FAQ categories"
 * )
 */

class FaqCatController extends Controller
{
    /**
     * @OA\Get(
     *     path="/categories",
     *     summary="Retrieve all FAQ categories",
     *     description="Retrieves all FAQ categories",
     *     tags={"FAQs"},
     *     @OA\Response(
     *         response=200,
     *         description="Successful operation",
     *         @OA\JsonContent(
     *             type="array",
     *             @OA\Items(
     *                 @OA\Property(property="id", type="integer"),
     *                 @OA\Property(property="title", type="string"),
     *                 @OA\Property(property="created_at", type="string", format="date-time")
     *             )
     *         )
     *     )
     * )
     */
    public function index()
    {
        $faqcats = FaqCat::all();

        foreach($faqcats as $faqcat) {
            $faqcat->created_at = date('Y-m-d h:i:s', strtotime($faqcat->created_at));
        }
        return response()->json(['data' => $faqcats], 200);
    }


    /**
     * @OA\Post(
     *     path="/category",
     *     summary="Create an FAQ category",
     *     description="Create an FAQ category with the specified data",
     *     tags={"FAQs"},
     *     @OA\RequestBody(
     *         required=true,
     *         description="Data to create an FAQ category",
     *         @OA\JsonContent(
     *             @OA\Property(property="title", type="string")
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="The FAQ category was created successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string")
     *         )
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation error",
     *         @OA\JsonContent(
     *             @OA\Property(
     *                 property="errors",
     *                 type="object",
     *                 additionalProperties=true
     *             )
     *         )
     *
     * )
     * )
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'title' => 'required|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()], 422);
        }


        $faqcat = new FaqCat();
        $faqcat->title = $request->input('title');
        $faqcat->save();

        return response()->json(['message' => 'The FAQ category was created successfully'], 201);
    }

    /**
     * @OA\Delete (
     *     path="/category",
     *     summary="Delete an FAQ category",
     *     description="Delete an FAQ category with the specified ID",
     *     tags={"FAQs"},
     *     @OA\RequestBody(
     *         required=true,
     *         description="Data to delete an FAQ category",
     *         @OA\JsonContent(
     *             @OA\Property(property="id", type="integer")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="The FAQ category was deleted successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string")
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="The requested category does not exist",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string")
     *         )
     *     )
     * )
     */
    public function destroy(Request $request)
    {
        $faqcat = FaqCat::find($request->input('id'));
        if (!$faqcat) {
            return response()->json(['message' => 'The requested FAQ category does not exist'], 404);
        }
        $faqcat->delete();
        return response()->json(['message' => 'The FAQ category was deleted successfully']);
    }
}
