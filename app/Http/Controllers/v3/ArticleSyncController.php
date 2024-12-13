<?php

namespace App\Http\Controllers\v3;

use App\Http\Controllers\Controller;
use App\Models\BlogCategory;
use App\Models\Blog;
use App\Services\VenueService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use GuzzleHttp\Promise;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use App\Jobs\UploadCollectionPhotoJob;
use App\Jobs\UploadPhotoJob;


class ArticleSyncController extends Controller
{
    private $bybestApiUrl = 'https://bybest.shop/api/V1/';
    private $bybestApiKey = 'crm.pixelbreeze.xyz-dbz';

    private $venueService;

    public function __construct(VenueService $venueService)
    {
        $this->venueService = $venueService;
    }

    public function articleCategoriesSync(Request $request)
    {
        $venue = $this->venueService->adminAuthCheck();
        $page = $request->input('page', 1);
        $perPage = $request->input('per_page', 100);
        $batchSize = $request->input('batch_size', 50);
        $skippedCount = 0;
        $processedCount = 0;

        // do {
            try {
                $start = microtime(true);
                $response = Http::withHeaders([
                    'X-App-Key' => $this->bybestApiKey
                ])->get($this->bybestApiUrl . 'articlecats-sync', [
                    'page' => $page,
                    'per_page' => $perPage
                ]);

                if (!$response->successful()) {
                    return response()->json(['message' => 'Failed to fetch data from ByBest API'], 500);
                }

                $bybestData = $response->json();

                if (empty($bybestData) || !isset($bybestData['data'])) {
                    // break; // No more data to process
                    return response()->json(['message' => 'No more data to process'], 500);
                }

                $categories = $bybestData['data'];

                // foreach (array_chunk($categories, $batchSize) as $batch) {
                    // DB::transaction(function () use ($batch, $venue, &$skippedCount, &$processedCount) {
                        foreach ($categories as $item) {
                            DB::beginTransaction();
                            try {
                                \Log::info('Processing categories', ['item' => $item]);

                                // Make sure the required fields are available
                                if (!isset($item['id'])) {
                                    \Log::error('categories missing id', ['item' => $item]);
                                    $skippedCount++;
                                    continue;
                                }


                                $json_category = json_decode($item['category']);
                                $json_desc = json_decode($item['description']);

                                $cat = (isset($json_category->en) && isset($json_category->en) != null) ? $json_category->en : '';
                                $cat_al = (isset($json_category->sq) && isset($json_category->sq) != null) ? $json_category->sq : '';
                                $desc = (isset($json_desc->en) && isset($json_desc->en) != null) ? $json_desc->en : '';
                                $desc_al = (isset($json_desc->sq) && isset($json_desc->sq) != null) ? $json_desc->sq : '';


                                BlogCategory::updateOrCreate(
                                    ['bybest_id' => $item['id']],
                                    [
                                        'name' => $cat,
                                        'name_al' => $cat_al,
                                        'description' => $desc,
                                        'description_al' => $desc_al,   
                                        'venue_id' => $venue->id,
                                        'bybest_id' => $item['id'],
                                        'created_at' => $item['created_at'],
                                        'updated_at' => $item['updated_at'],
                                    ]
                                );

                                $processedCount++;
                                DB::commit();
                            } catch (\Exception $e) {
                                DB::rollBack();
                            }
                        }
                    // });
                // }

                \Log::info("Processed {$processedCount} articlecats so far.");

                // $page++;
            } catch (\Throwable $th) {
                \Log::error('Error in articlecats sync', [
                    'error' => $th->getMessage(),
                    'trace' => $th->getTraceAsString()
                ]);
                return response()->json([
                    "message" => "Error in articlecats sync",
                    "error" => $th->getMessage()
                ], 503);
            }
        // } while (count($categories) == $perPage);

        return response()->json([
            'message' => 'articlecats sync completed successfully',
            'processed_count' => $processedCount,
            'skipped_count' => $skippedCount,
            'total_pages' => isset($bybestData['total_pages']) ? $bybestData['total_pages'] : null,
            'current_page' => isset($bybestData['current_page']) ? $bybestData['current_page'] : null
        ], 200);
    }

    public function articlesSync(Request $request)
    {
        $venue = $this->venueService->adminAuthCheck();
        $page = $request->input('page', 1);
        $perPage = $request->input('per_page', 100);
        $batchSize = $request->input('batch_size', 50);
        $skippedCount = 0;
        $processedCount = 0;

        // do {
            try {
                $start = microtime(true);
                $response = Http::withHeaders([
                    'X-App-Key' => $this->bybestApiKey
                ])->get($this->bybestApiUrl . 'articles-sync', [
                    'page' => $page,
                    'per_page' => $perPage
                ]);


                if (!$response->successful()) {
                    return response()->json(['message' => 'Failed to fetch data from ByBest API'], 500);
                }

                $bybestData = $response->json();
                if (empty($bybestData) || !isset($bybestData['data'])) {
                    // break; // No more data to process
                    return response()->json(['message' => 'No more data to process'], 500);
                }

                $articles = $bybestData['data'];

                // foreach (array_chunk($articles, $batchSize) as $batch) {
                    // DB::transaction(function () use ($batch, $venue, &$skippedCount, &$processedCount) {
                        foreach ($articles as $item) {
                            DB::beginTransaction();
                            try {
                                \Log::info('Processing articles', ['item' => $item]);

                                // Make sure the required fields are available
                                if (!isset($item['id'])) {
                                    \Log::error('articles missing id', ['item' => $item]);
                                    $skippedCount++;
                                    continue;
                                }

                                $json_name = json_decode($item['article_title']);
                                $json_desc = json_decode($item['article_content']);

                                $title = (isset($json_name->en) && isset($json_name->en) != null) ? $json_name->en : '';
                                $title_al = (isset($json_name->sq) && isset($json_name->sq) != null) ? $json_name->sq : '';
                                $content = (isset($json_desc->en) && isset($json_desc->en) != null) ? $json_desc->en : '';
                                $content_al = (isset($json_desc->sq) && isset($json_desc->sq) != null) ? $json_desc->sq : '';



                                $blog = Blog::updateOrCreate(
                                    ['bybest_id' => $item['id']],
                                    [
                                        'title' => $title,
                                        'title_al' => $title_al,
                                        'content' => $content,
                                        'content_al' => $content_al,
                                        'restaurant_id' => $venue->id,
                                        'is_active' => $item['status_id'] == 1 ? 1 : 0,
                                        'slug' => $item['article_link'],
                                        'slug_related' => "/blog/{$item['article_link']}",
                                        'author_name' =>  'BB Import',
                                        'author_designation' => '',
                                        'read_time' => (int)$item['time_to_read'],
                                        'tags' =>  $item['article_tags'],
                                        'image' => 'https://admin.bybest.shop/storage/articles/' . $item['article_featured_image'],
                                        'bybest_id' => $item['id'],
                                        'created_at' => $item['created_at'],
                                        'updated_at' => $item['updated_at'],
                                    ]
                                );



                                $blogCategory = BlogCategory::where('bybest_id', $item['category_id'])->first();

                                $blog_blog_category = DB::table('blog_blog_category')
                                ->where('blog_id', $blog->id)
                                    ->where('blog_category_id', $blogCategory->id)
                                    ->first();
                                if (!$blog_blog_category) {
                                    DB::table('blog_blog_category')->insert([
                                        'blog_id' => $blog->id,
                                        'blog_category_id' => $blogCategory->id
                                    ]);
                                }

                                // Dispatch job for photo upload
                                if ($item['article_featured_image']) {
                                    \Log::info('Dispatching UploadPhotoJob', [
                                        'blog' => $blog->id,
                                        'photo_url' => $item['article_featured_image'],
                                    ]);

                                    // UploadPhotoJob::dispatch($blog, 'https://admin.bybest.shop/storage/articles/' . $item['article_featured_image'], 'image', $venue);
                                }
                                $processedCount++;
                                DB::commit();
                            } catch (\Exception $e) {
                                DB::rollBack();
                            }
                        }
                    // });
                // }

                \Log::info("Processed {$processedCount} articles so far.");

                // $page++;
            } catch (\Throwable $th) {
                \Log::error('Error in articles sync', [
                    'error' => $th->getMessage(),
                    'trace' => $th->getTraceAsString()
                ]);
                return response()->json([
                    "message" => "Error in articles sync",
                    "error" => $th->getMessage()
                ], 503);
            }
        // } while (count($articles) == $perPage);

        return response()->json([
            'message' => 'articles sync completed successfully',
            'processed_count' => $processedCount,
            'skipped_count' => $skippedCount,
            'total_pages' => isset($bybestData['total_pages']) ? $bybestData['total_pages'] : null,
            'current_page' => isset($bybestData['current_page']) ? $bybestData['current_page'] : null
        ], 200);
    }
}
