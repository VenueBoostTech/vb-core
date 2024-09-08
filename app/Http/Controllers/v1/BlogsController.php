<?php

namespace App\Http\Controllers\v1;

use App\Http\Controllers\Controller;
use App\Models\Blog;
use App\Models\BlogCategory;
use App\Models\Photo;
use App\Models\WebsiteStatistic;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use function response;


/**
 * @OA\Info(
 *   title="Blogs API",
 *   version="1.0",
 *   description="This API allows use to retrieve all Blogs API related data",
 * )
 */

/**
 * @OA\Tag(
 *   name="Blogs",
 *   description="Operations related to Blogs"
 * )
 */

class BlogsController extends Controller
{

    /**
     * @OA\Get(
     *     path="/blogs",
     *     tags={"Blogs"},
     *     summary="Retrieve a list of all blogs",
     *     @OA\Response(
     *         response=200,
     *         description="Successfully retrieved list of blogs",
     *         @OA\JsonContent(
     *             type="array",
     *             description="List of blogs",
     *        @OA\Items(
     *              @OA\Property(property="id", type="integer", example=1),
     *             @OA\Property(property="title", type="string", example="Blog Title"),
     *             @OA\Property(property="content", type="string", example="Blog Content"),
     *             @OA\Property(property="restaurant_id", type="integer", example=1),
     *             @OA\Property(property="image", type="string", example="Blog Image"),
     *        )
     *       )
     *     )
     * )
     */
    public function index()
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

        $perPage = request()->get('per_page', 15); // Default to 15 items per page
        $blogs = Blog
                    ::join('blog_blog_category', 'blog_blog_category.blog_id', '=', 'blogs.id')
                    ->join('blog_categories', 'blog_blog_category.blog_category_id', '=', 'blog_categories.id')
                    ->where('blogs.restaurant_id', $venue->id)
                    ->select('blogs.*', DB::raw('blog_categories.id as category'), DB::raw('blog_categories.name as category_text'))
                    ->paginate($perPage);
        $updatedBlogs = $blogs->map(function ($blog) {
            if ($blog->image !== null) {
                // Generate the new path and update the image_path attribute
                $newPath = Storage::disk('s3')->temporaryUrl($blog->image, '+5 minutes');
                $blog->image = $newPath;
            }
            return $blog;
        });
        return response()->json([
            'blogs' => $updatedBlogs,
            'pagination' => [
                'total' => $blogs->total(),
                'per_page' => $blogs->perPage(),
                'current_page' => $blogs->currentPage(),
                'last_page' => $blogs->lastPage(),
                'from' => $blogs->firstItem(),
                'to' => $blogs->lastItem(),
            ],
        ]);
    }


    /**
     * @OA\Post(
     *     path="/blogs",
     *     tags={"Blogs"},
     *     summary="Create a new blog",
     *     @OA\RequestBody(
     *          required=true,
     *          @OA\JsonContent(
     *              type="object",
     *              required={"title", "content", "restaurant_id"},
     *              @OA\Property(property="title", type="string", example="Blog Title"),
     *              @OA\Property(property="content", type="string", example="Blog Content"),
     *              @OA\Property(property="restaurant_id", type="integer", example="1"),
     *              @OA\Property(property="categories", type="array", example={1,2,3},@OA\Items(type="integer"))
     *          )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Successfully created a new blog",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="message", type="string", example="Blog created successfully")
     *         )
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation Error",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="error", type="string", example="The title field is required.")
     *         )
     *     )
     * )
     */
    public function store(Request $request) {
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
            'title' => 'required|string',
            'content' => 'required|string',
            'category' => 'required|integer',
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()], 400);
        }

        $data['title'] = $request->input('title');
        $data['content'] = $request->input('content');
        $data['is_active'] = $request->input('is_active');
        $data['tags'] =  $request->input('tags');
        $data['slug'] = Str::slug($request->input('title'));
        $data['slug_related'] = "/blog/{$data['slug']}";

        $path = null;
        if ($request->file('image')) {

            $requestType = 'other';

            $logoCollection = $request->file('image');

            // Decode base64 image data
            $photoFile = $logoCollection;
            $filename = Str::random(20) . '.' . $photoFile->getClientOriginalExtension();

            // Upload photo to AWS S3
            $path = Storage::disk('s3')->putFileAs('venue_gallery_photos/' . $venue->venueType->short_name . '/' . $requestType . '/' . strtolower(str_replace(' ', '-', $venue->name . '-' . $venue->short_code)), $photoFile, $filename);

            // Save photo record in the database
            $photo = new Photo();
            $photo->venue_id = $venue->id;
            $photo->image_path = $path;
            $photo->type = $requestType;
            $photo->save();
        }

        $data['image'] = $path;
        $data['restaurant_id'] = $venue->id;
        $data['author_name'] = '';
        $data['author_designation'] = '';
        $data['read_time'] = 0;
        $blog = Blog::create($data);

        DB::table('blog_blog_category')->insert([
            'blog_id' => $blog->id,
            'blog_category_id' => $request->input('category')
        ]);

        return response()->json($blog, 201);
    }

    /**
     * @OA\Get(
     *     path="/blogs/{id}",
     *     tags={"Blogs"},
     *     summary="Retrieve a single blog",
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="ID of blog to retrieve",
     *         required=true,
     *         @OA\Schema(
     *             type="integer",
     *             format="int64"
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Successfully retrieved a single blog",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="id", type="integer", example=1),
     *             @OA\Property(property="title", type="string", example="Blog Title"),
     *             @OA\Property(property="content", type="string", example="Blog Content"),
     *             @OA\Property(property="restaurant_id", type="integer", example=1),
     *             @OA\Property(property="image", type="string", example="Blog Image"),
     *             @OA\Property(property="views", type="integer", example=1),
     *             @OA\Property(property="created_at", type="string", example="2021-05-01T12:00:00.000000Z"),
     *             @OA\Property(property="updated_at", type="string", example="2021-05-01T12:00:00.000000Z"),
     *             @OA\Property(property="categories", type="array", example={1,2,3},@OA\Items(type="integer"))
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Blog not found",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="error", type="string", example="Blog not found")
     *         )
     *     )
     * )
     */

    public function view($id) {

        $blog = Blog::with(['categories', 'restaurant'])->findOrFail($id);
        if (!$blog) {
            return response()->json(['error' => 'Blog not found'], 404);
        }
        $blog->increment('views');
        $blog->save();
        return response()->json($blog);

    }

    /**
     * @OA\Put(
     *     path="/blogs/{id}",
     *     tags={"Blogs"},
     *     summary="Update an existing blog",
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="ID of the blog to update",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *              type="object",
     *              required={"title", "content"},
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Successfully updated the blog",
     *         @OA\JsonContent(
     *              type="object",
     *              @OA\Property(property="id", type="integer", example=1),
     *              @OA\Property(property="title", type="string", example="Blog Title"),
     *              @OA\Property(property="content", type="string", example="Blog Content"),
     *              @OA\Property(property="restaurant_id", type="integer", example=1),
     *              @OA\Property(property="image", type="string", example="Blog Image"),
     *              @OA\Property(property="views", type="integer", example=1),
     *              @OA\Property(property="created_at", type="string", example="2021-05-01T12:00:00.000000Z"),
     *              @OA\Property(property="updated_at", type="string", example="2021-05-01T12:00:00.000000Z"),
     *              @OA\Property(property="categories", type="array", example={1,2,3},@OA\Items(type="integer"))
     *        )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Blog not found"
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation errors"
     *     )
     * )
     */
    public function update($id, Request $request)
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

        $validator = Validator::make($request->all(), [
            'title' => 'required|string',
            'content' => 'required|string',
            'category' => 'required|integer',
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()], 400);
        }

        $blog = Blog::where('id', $id)->where('restaurant_id', $venue->id)->first();
        if (!$blog) {
            return response()->json(['error' => 'Blog not found'], 404);
        }

        $blog->title =  $request->input('title');
        $blog->content =  $request->input('content');
        $blog->is_active =  $request->input('is_active');
        $blog->tags =  $request->input('tags');
        $blog->slug = Str::slug($request->input('title'));
        $blog->slug_related = "/blog/{$blog->slug}";

        $path = null;
        if ($request->file('image')) {

            $requestType = 'other';

            $logoCollection = $request->file('image');

            // Decode base64 image data
            $photoFile = $logoCollection;
            $filename = Str::random(20) . '.' . $photoFile->getClientOriginalExtension();

            // Upload photo to AWS S3
            $path = Storage::disk('s3')->putFileAs('venue_gallery_photos/' . $venue->venueType->short_name . '/' . $requestType . '/' . strtolower(str_replace(' ', '-', $venue->name . '-' . $venue->short_code)), $photoFile, $filename);

            // Save photo record in the database
            $photo = new Photo();
            $photo->venue_id = $venue->id;
            $photo->image_path = $path;
            $photo->type = $requestType;
            $photo->save();

            $blog->image = $path;
        }

        $blog->save();

        DB::table('blog_blog_category')->where('blog_id', $blog->id)->update(['blog_category_id' => $request->input('category')]);
        return response()->json($blog, 200);
    }

    /**
     * @OA\Delete(
     *     path="/blogs/{id}",
     *     tags={"Blogs"},
     *     summary="Delete a specific blog",
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="ID of the blog to delete",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Successfully deleted the blog"
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="The requested blog does not exist"
     *     )
     * )
     */
    public function destroy($id)
    {
        $blog = Blog::find($id);
        if (!$blog) {
            return response()->json(['message' => 'The requested blog does not exist'], 404);
        }
        $blog->delete();
        return response()->json(['message' => 'Successfully deleted the blog']);
    }

    // Blog Categories CRUD
    public function createBlogCategory(Request $request): \Illuminate\Http\JsonResponse
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

        $validator = Validator::make($request->all(), [
            'name' => 'required|string',
            'description' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()], 400);
        }

        $data['name'] = $request->input('name');
        $data['description'] = $request->input('description');
        $data['venue_id'] = $venue->id;

        $category = BlogCategory::create($data);

        return response()->json(['message' => 'Category created successfully', 'category' => $category]);
    }

    public function updateBlogCategory(Request $request): \Illuminate\Http\JsonResponse
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

        $validator = Validator::make($request->all(), [
            'name' => 'required|string',
            'description' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()], 400);
        }

        $category = BlogCategory::find($request->id);
        if (!$category) {
            return response()->json(['error' => 'Category not found'], 404);
        }

        $category->name = $request->input('name');
        $category->description = $request->input('description');
        $category->save();

        return response()->json(['message' => 'Category updated successfully', 'category' => $category]);
    }

    public function listBlogCategories(): \Illuminate\Http\JsonResponse
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

        $perPage = request()->get('per_page', 15); // Default to 15 items per page
        $categories = BlogCategory::where('venue_id', $venue->id)->paginate($perPage);

        return response()->json([
            'categories' => $categories,
            'pagination' => [
                'total' => $categories->total(),
                'per_page' => $categories->perPage(),
                'current_page' => $categories->currentPage(),
                'last_page' => $categories->lastPage(),
                'from' => $categories->firstItem(),
                'to' => $categories->lastItem(),
            ],
        ]);
    }

    public function deleteBlogCategory($id): \Illuminate\Http\JsonResponse
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

        $category = BlogCategory::where('id', $id)->where('venue_id', $venue->id)->first();
        if (!$category) {
            return response()->json(['message' => 'The requested category does not exist'], 404);
        }

        $category->delete();

        return response()->json(['message' => 'Blog Category deleted successfully']);
    }

    public function blogsListAndReadCount(Request $request): \Illuminate\Http\JsonResponse
    {
        $limit = $request->input('limit', 10);

        $blogs = Blog::select('id', 'title', 'read_count')
            ->selectRaw("DATE_FORMAT(created_at, '%Y-%m-%d') as posted_on")
            ->orderBy('created_at', 'desc') // Order by latest
            ->take($limit)
            ->get();

        $totalReadCount = (int) Blog::sum('read_count');

        $faqScreenCount = WebsiteStatistic::first()->faqs_screen_count ?? 0;

        return response()->json([
            'blogs' => $blogs,
            'blog_read_count' => $totalReadCount,
            'case_studies_read_count' => 0,
            'affiliate_program_read_count' => 0,
            'faq_read_count' => $faqScreenCount
        ]);
    }

    public function blogsListSuperadmin(Request $request): \Illuminate\Http\JsonResponse
    {

        $blogs = Blog::select('id', 'title', 'author_name')
            ->selectRaw("DATE_FORMAT(created_at, '%Y-%m-%d') as posted_on")
            ->orderBy('created_at', 'desc') // Order by latest
            ->get();


        return response()->json([
            'blogs' => $blogs,
        ]);
    }

    public function blogsList(Request $request): \Illuminate\Http\JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'category' => 'sometimes|string'
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()], 400);
        }

        $query = Blog::with('categories');

        $categoryName = $request->input('category');
        if (!empty($categoryName)) {
            $query->whereHas('categories', function ($query) use ($categoryName) {
                $query->where('name', '=', $categoryName);
            });
        }

        $excludedIds = [
            76,
            75,
            74,
            73,
            72,
            69,
            68, 67, 66, 65, 64, 63, 62, 61, 60, 59,
            58, 57, 55, 54, 53, 52, 51, 50, 49, 48,
            47, 46, 45, 44, 43, 42, 41, 40,
            39, 38, 37,
            36,
            79,
            80,
            81,
            82,
        ];

        // Mapping array
        $categoryMapping = [
            'news_and_trends' => 'News and trends',
            'venue_management' => 'Venue management',
            'pro_tips_and_best_practices' => 'Pro Tips and Best Practices',
            'special_announcements' => 'Special Announcements',
            'feature_showcase' => 'Feature Showcase',
            'industry_insights' => 'Industry insights'
        ];

        $blogs = $query->whereNotIn('id', $excludedIds)
            ->orderBy('created_at', 'desc') // Order by latest
            ->get(['id', 'title', 'slug', 'slug_related', 'created_at', 'image', 'tags', 'body', 'author_name', 'author_designation', 'read_time', 'show_quiz', 'is_new_type', 'category_text'])
            ->map(function ($blog) use ($categoryMapping) {
                return [
                    'id' => $blog->id,
                    'title' => $blog->title,
                    'slug' => $blog->slug,
                    'slug_related' => $blog->slug_related,
                    'date' => $blog->created_at ? $blog->created_at->format('F j, Y') :'-' ,
                    'image' => (
                        $blog->id === 56 ||
                        $blog->id === 70 ||
                        $blog->id === 71 ||
                        $blog->id === 171 ||
                        $blog->id === 175 ||
                        $blog->id === 191 ||
                        $blog->id === 192 ||
                        $blog->id === 193 ||
                        $blog->id === 194 ||
                        $blog->id === 195 ||
                        $blog->id === 196 ||
                        $blog->id === 197 ||
                        $blog->id === 198 ||
                        $blog->id === 199 ||
                        $blog->id === 200 ||
                        $blog->id === 201 ||
                        $blog->id === 202 ||
                        $blog->id === 214 ||
                        $blog->id === 216
                    )
                        ? $blog->image
                        : ($blog->image ? Storage::disk('s3')->temporaryUrl($blog->image, '+5 minutes') : null),
                    'tags' => $blog->tags,
                    'body' => $blog->body,
                    'author_name' => $blog->author_name,
                    'author_designation' => $blog->author_designation,
                    'read_time' => $blog->read_time,
                    'show_quiz' => $blog->show_quiz,
                    'is_new_type' => $blog->is_new_type,
                    'category_text' => $categoryMapping[$blog->category_text] ?? $blog->category_text,
                ];
            });

        return response()->json($blogs);
    }

    public function getOneBlog($id): \Illuminate\Http\JsonResponse
    {
        // Validate that the blog exists
        $blog = Blog::with('categories')->find($id);

        if (!$blog) {
            return response()->json(['error' => 'Blog not found'], 404);
        }

        // Mapping array
        $categoryMapping = [
            'news_and_trends' => 'News and trends',
            'venue_management' => 'Venue management',
            'pro_tips_and_best_practices' => 'Pro Tips and Best Practices',
            'special_announcements' => 'Special Announcements',
            'feature_showcase' => 'Feature Showcase',
            'industry_insights' => 'Industry insights'
        ];

        $blogData = [
            'id' => $blog->id,
            'title' => $blog->title,
            'category' => $blog->categories->pluck('name')->join(', '),
            'slug' => $blog->slug,
            'date' => $blog->created_at ? $blog->created_at->format('F j, Y') :'-' ,
            'image' => (
                $blog->id === 56 ||
                $blog->id === 70 ||
                $blog->id === 71 ||
                $blog->id === 171 ||
                $blog->id === 175 ||
                $blog->id === 191 || // Ungerboeck
                $blog->id === 192 || // EventBooking
                $blog->id === 193 || // MINDBODY
                $blog->id === 194 || // Rezdy
                $blog->id === 195 || // Oracle
                $blog->id === 196 || // PerfectVenue
                $blog->id === 197 || // HoneyBook
                $blog->id === 198 || // Eventbrite
                $blog->id === 199 || //Gather
                $blog->id === 200 || // Priava
                $blog->id === 201 || // Aventri
                $blog->id === 202 || // Ivvy
                $blog->id === 214 || // Xero
                $blog->id === 216

            )
                ? $blog->image
                : ($blog->image ? Storage::disk('s3')->temporaryUrl($blog->image, '+5 minutes') : null),
            'tags' => $blog->tags,
            'body' => $blog->body,
            'author_name' => $blog->author_name,
            'author_designation' => $blog->author_designation,
            'read_time' => $blog->read_time,
            'show_quiz' => $blog->show_quiz,
            'is_new_type' => $blog->is_new_type,
            'category_text' => $categoryMapping[$blog->category_text] ?? $blog->category_text,
        ];

        return response()->json($blogData);
    }


    public function storeBlog(Request $request): \Illuminate\Http\JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'title' => 'required|string|max:255',
            'image' => 'nullable',
             'body' => 'required',
             'tags' => 'required|string',
             'category_text' => 'required|string',
            'author_name' => 'required|string|max:255',
            'author_designation' => 'required|string|max:255',
            'read_time' => 'required|integer',
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()], 400);
        }
////
//        $user = auth()->user();
////        dd($user->role->name);
//        if ($user->role->name !== 'Superadmin') {
//            return response()->json(['error' => 'Unauthorized: Only super admins can perform this action'], 403);
//        }

        $requestType = 'gallery';

        $slug = Str::slug($request->input('title'));
        $slugRelated = "/blog/{$slug}";


        $path = null;
        if ($request->file('image')) {

            $blogImage = $request->file('image');
            $filename = Str::random(20) . '.' . $blogImage->getClientOriginalExtension();

            $path = Storage::disk('s3')->putFileAs('blog_section_images/' . $requestType, $blogImage, $filename);

            $photo = new Photo();
            $photo->image_path = $path;
            $photo->type = $requestType;
            $photo->venue_id = 1;
            $photo->save();

        }

        $blogData = [
            'title' => $request->input('title'),
            'content' =>'-',
            'body' => $request->input('body'),
            'image' => $path,
            'author_avatar' => $request->input('author_avatar'),
            'author_name' => $request->input('author_name'),
            'author_designation' => $request->input('author_designation'),
            'read_time' => $request->input('read_time'),
            'has_tags' => true,
            'tags' => $request->input('tags'),
            'slug' => $slug,
            'slug_related' => $slugRelated,
            'show_quiz' => $request->input('show_quiz'),
            'category_text' => $request->input('category_text'),
            'is_new_type' => true
        ];

        Blog::create($blogData);

        return response()->json(['message' => 'Successfully stored the blogs']);
    }

    public function updateStatus(Request $request, $id): \Illuminate\Http\JsonResponse
    {
        $user = auth()->user();
        if ($user->role->name !== 'Superadmin') {
            return response()->json(['error' => 'Unauthorized: Only super admins can perform this action'], 403);
        }

        $validator = Validator::make($request->all(), [
            'is_active' => 'boolean',
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()], 400);
        }

        $blog = Blog::find($id);

        if (!$blog) {
            return response()->json(['error' => 'Blog not found'], 404);
        }

        $blog->is_active = $request->input('is_active');
        $blog->save();

        return response()->json(['message' => 'Blog status updated successfully', 'blog' => $blog]);
    }

    public function updateBlog(Request $request, $id): \Illuminate\Http\JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'title' => 'required|string|max:255',
            'content' => 'required|string',
            'image' => 'nullable|string',
            'section_1_ul_list' => 'required|array',
            'section_1_ul_list.*.title' => 'required|string|max:255',
            'section_1_ul_list.*.content' => 'required|string',
            'author_avatar' => 'nullable|string',
            'author_name' => 'required|string|max:255',
            'author_designation' => 'required|string|max:255',
            'read_time' => 'required|integer',
            'has_tags' => 'required|boolean',
            'detail_image' => 'nullable|string',
            'detail_image_2' => 'nullable|string',
            'detail_image_3' => 'nullable|string',
            'detail_image_4' => 'nullable|string',
            'show_quiz' => 'sometimes|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()], 400);
        }

        $user = auth()->user();
        if ($user->role->name !== 'Superadmin') {
            return response()->json(['error' => 'Unauthorized: Only super admins can perform this action'], 403);
        }

        $blog = Blog::find($id);
        if (!$blog) {
            return response()->json(['error' => 'Blog not found'], 404);
        }

        $blog->fill($request->only([
            'title', 'content', 'author_avatar', 'author_name', 'author_designation', 'read_time', 'has_tags',
        ]));

        if ($request->has('title')) {
            $blog->slug = Str::slug($request->input('title'));
            $blog->slug_related = "/blog/{$blog->slug}";
        }

        if ($request->has('section_1_ul_list')) {
            $sectionUlList = $request->input('section_1_ul_list');
            $sanitizedUlList = array_map(function ($item) {
                $item['title'] = htmlspecialchars($item['title'], ENT_QUOTES, 'UTF-8');
                $item['content'] = htmlspecialchars($item['content'], ENT_QUOTES, 'UTF-8');
                return $item;
            }, $sectionUlList);

            $blog->section_1_new_ul_list = json_encode($sanitizedUlList);
        }

        $sectionJson = $this->processSectionData($request, 'blog');
        if ($sectionJson) {
            $blog->sections = $sectionJson;
        }

        if ($request->file('image')) {
            if ($blog->image) {
                $oldImagePath = str_replace(url(''), '', $blog->image);
                Storage::disk('s3')->delete($oldImagePath);
            }

            $newImage = $request->file('image');
            $filename = Str::random(20) . '.' . $newImage->getClientOriginalExtension();
            $path = Storage::disk('s3')->putFileAs('blog_images', $newImage, $filename);

            $blog->image = Storage::disk('s3')->url($path);
        }

        if ($request->has('show_quiz')) {
            $blog->show_quiz = $request->input('show_quiz');
        }

        $blog->save();

        return response()->json(['message' => 'Blog updated successfully', 'blog' => $blog]);
    }

    protected function processSectionData($request, $requestType)
    {
        $sections = [];

        // Dynamic section handler
        for ($sectionNum = 1; $sectionNum <= 10; $sectionNum++) {
            $sectionTitleKey = "section_{$sectionNum}_title";
            $sectionContentKey = "section_{$sectionNum}_content";
            $sectionImageKey = "section_{$sectionNum}_image";

            if ($request->has($sectionTitleKey) && $request->has($sectionContentKey)) {
                $section = [
                    'title' => $request->input($sectionTitleKey),
                    'content' => $request->input($sectionContentKey),
                    'points' => [],
                ];

                if ($request->file($sectionImageKey)) {
                    $blogImage = $request->file($sectionImageKey);
                    $filename = Str::random(20) . '.' . $blogImage->getClientOriginalExtension();

                    // TODO: change the file path accordingly to the AWS folder structure!!!!
                    $path = Storage::disk('s3')->putFileAs('blog_section_images', $blogImage, $filename);
                    $section['image_path'] = Storage::disk('s3')->url($path);

                    $photo = new Photo();
                    $photo->image_path = $path;
                    $photo->type = $requestType;
                    $photo->save();
                }

                // Process points if they exist
                for ($i = 1; $i <= 10; $i++) { // Maximum of 10 points
                    $pointTitleKey = "section_{$sectionNum}_point_{$i}_title";
                    $pointContentKey = "section_{$sectionNum}_point_{$i}_content";

                    if ($request->has($pointTitleKey) && $request->has($pointContentKey)) {
                        $section['points'][] = [
                            'title' => $request->input($pointTitleKey),
                            'content' => $request->input($pointContentKey),
                        ];
                    }
                }

                $sections[] = $section;
            }
        }

        return !empty($sections) ? json_encode($sections) : null;
    }

}
