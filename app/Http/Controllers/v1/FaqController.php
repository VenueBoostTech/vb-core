<?php

namespace App\Http\Controllers\v1;

use App\Http\Controllers\Controller;
use App\Models\Faq;
use App\Models\FaqCat;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
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
 *   description="Operations related to Faqs"
 * )
 */


class FaqController extends Controller
{
    /**
     * @OA\Get(
     *     path="/faqs",
     *     summary="List all FAQs",
     *     description="List all FAQs with their corresponding categories",
     *     tags={"FAQs"},
     *     @OA\Response(
     *         response=200,
     *         description="List of FAQs",
     *         @OA\JsonContent(
     *             @OA\Property(
     *                 property="data",
     *                 type="array",
     *                 @OA\Items(
     *                     @OA\Property(property="id", type="integer"),
     *                     @OA\Property(property="faq_cat_id", type="integer"),
     *                     @OA\Property(property="question", type="string"),
     *                     @OA\Property(property="answer", type="string"),
     *                     @OA\Property(
     *                         property="category",
     *                         type="object",
     *                         @OA\Property(property="id", type="integer"),
     *                         @OA\Property(property="name", type="string")
     *                     )
     *                 )
     *             )
     *         )
     *     )
     * )
     */
    public function index(): \Illuminate\Http\JsonResponse
    {
        $faqs = Faq::all();

        foreach($faqs as $faq) {
            $faq->category = FaqCat::find($faq->faq_cat_id);
        }
        return response()->json(['data' => $faqs], 200);
    }

    /**
     * @OA\Post(
     *     path="/faqs",
     *     summary="Create an FAQ",
     *     description="Create an FAQ with the specified data",
     *     tags={"FAQs"},
     *     @OA\RequestBody(
     *         required=true,
     *         description="Data to create or update an FAQ",
     *         @OA\JsonContent(
     *             @OA\Property(property="question", type="string"),
     *             @OA\Property(property="answer", type="string"),
     *             @OA\Property(property="category", type="integer")
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="The FAQ was created successfully",
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
     *     )
     * )
     */
    public function store(Request $request): \Illuminate\Http\JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'question' => 'required',
            'answer' => 'required',
            'category' => [
                'required',
                Rule::exists('faq_cats', 'id'),
            ],
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()], 422);
        }


        $faq = new Faq();
        $faq->question = $request->input('question');
        $faq->answer = $request->input('answer');
        $faq->faq_cat_id = $request->input('category');
        $faq->save();


        return response()->json(['message' => 'The FAQ was created successfully'], 201);
    }


    /**
     * @OA\Delete(
     *     path="/",
     *     summary="Delete an FAQ",
     *     description="Delete an FAQ by faq ID",
     *     tags={"FAQs"},
     *     @OA\Parameter(
     *         name="id",
     *         in="query",
     *         description="ID of the FAQ to delete",
     *         required=true,
     *         @OA\Schema(
     *             type="integer"
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="The FAQ was deleted successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string")
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="The requested faq does not exist or has already been deleted",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string")
     *         )
     *     )
     * )
     */
    public function destroy(Request $request): \Illuminate\Http\JsonResponse
    {
        $faq = Faq::withTrashed()->find($request->input('id'));

        if (!$faq) {
            return response()->json(['message' => 'The requested FAQ does not exist'], 404);
        }

        if ($faq->trashed()) {
            return response()->json(['message' => 'The requested FAQ has already been deleted'], 404);
        }

        $faq->delete();

        return response()->json(['message' => 'FAQ deleted successfully'], 200);
    }

    /**
     * @OA\Get(
     *     path="/search",
     *     summary="Search FAQs by question",
     *     description="Searches FAQs by question and returns matching results",
     *     tags={"FAQs"},
     *     @OA\Parameter(
     *         name="query",
     *         in="query",
     *         description="The search query",
     *         required=true,
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Successful operation",
     *         @OA\JsonContent(
     *             type="array",
     *             @OA\Items(
     *                 @OA\Property(property="id", type="integer"),
     *                 @OA\Property(property="faq_cat_id", type="integer"),
     *                 @OA\Property(property="question", type="string"),
     *                 @OA\Property(property="answer", type="string"),
     *                 @OA\Property(property="created_at", type="string", format="date-time"),
     *                 @OA\Property(property="updated_at", type="string", format="date-time")
     *             )
     *         )
     *     )
     * )
     */
    public function search(Request $request)
    {
        $query = $request->input('query');

        if (strlen($query) < 3) {
            return response()->json(['message' => 'Search term must be at least 3 characters long'], 422);
        }

        $faqs = Faq::where('question', 'like', '%' . $query . '%')->get();

        return response()->json(['data' => $faqs], 200);
    }

    /**
     * @OA\Get(
     *     path="/by-category",
     *     summary="Search FAQs by category",
     *     description="Filter FAQs by category and returns matching results",
     *     tags={"FAQs"},
     *     @OA\Parameter(
     *         name="category",
     *         in="query",
     *         description="The category to search for",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Successful operation",
     *         @OA\JsonContent(
     *             type="array",
     *             @OA\Items(
     *                 @OA\Property(property="id", type="integer"),
     *                 @OA\Property(property="faq_cat_id", type="integer"),
     *                 @OA\Property(property="question", type="string"),
     *                 @OA\Property(property="answer", type="string"),
     *                 @OA\Property(property="created_at", type="string", format="date-time"),
     *                 @OA\Property(property="updated_at", type="string", format="date-time")
     *             )
     *         )
     *     )
     * )
     */
    public function searchByCategory(Request $request): \Illuminate\Http\JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'category' => 'required|exists:faq_cats,id'
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()], 422);
        }

        $category = $request->input('category');

        $faqs = Faq::where('faq_cat_id', '=', $category)->get();

        return response()->json(['data' => $faqs], 200);
    }


}
