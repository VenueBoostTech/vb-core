<?php

namespace App\Http\Controllers\v3;

use App\Http\Controllers\Controller;
use App\Models\BbSlider;
use App\Models\BbMainMenu;
use App\Services\VenueService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use GuzzleHttp\Promise;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use App\Jobs\UploadPhotoJob;


class BbWebSyncController extends Controller
{
    private $bybestApiUrl = 'https://bybest.shop/api/V1/';
    private $bybestApiKey = 'crm.pixelbreeze.xyz-dbz';

    private $venueService;

    public function __construct(VenueService $venueService)
    {
        $this->venueService = $venueService;
    }

    public function slidersSync(Request $request): \Illuminate\Http\JsonResponse
    {
        $venue = $this->venueService->adminAuthCheck();
        // Get parameters from request with default values
        $page = $request->input('page', 1);
        $perPage = $request->input('per_page', 100);
        $batchSize = $request->input('batch_size', 50);
        $skippedCount = 0;
        $processedCount = 0;
        // ini_set('max_execution_time', 3000000);
        do {
            try {
                $response = Http::withHeaders([
                    'X-App-Key' => $this->bybestApiKey
                ])->get($this->bybestApiUrl . 'siders-sync', [
                            'page' => $page,
                            'per_page' => $perPage
                        ]);

                if (!$response->successful()) {
                    return response()->json(['message' => 'Failed to fetch data from ByBest API'], 500);
                }

                $bybestData = $response->json();


                if (empty($bybestData) || !isset($bybestData['data'])) {
                    break; // No more sliders to process
                }

                $sliders = $bybestData['data']; // Assuming 'data' contains the actual sliders


                foreach (array_chunk($sliders, $batchSize) as $batch) {
                    DB::transaction(function () use ($batch, $venue, &$skippedCount, &$processedCount) {
                        foreach ($batch as $item) {

                            $slider = BbSlider::withTrashed()->updateOrCreate(
                                ['bybest_id' => $item['id']],
                                [
                                    'title' => $item['title'],
                                    'url' => $item['url'],
                                    'description' => $item['description'],
                                    'button' => $item['button'],
                                    'text_button' => $item['text_button'],
                                    'slider_order' => $item['slider_order'],
                                    'status' => $item['status'],
                                    // 'photo' => 'https://admin.bybest.shop/storage/sliders/' . $item['photo'],
                                    'venue_id' => $venue->id,
                                    'bybest_id' => $item['id'],
                                    'created_at' => $item['created_at'],
                                    'updated_at' => $item['updated_at'],
                                    'deleted_at' => $item['deleted_at'] ? Carbon::parse($item['deleted_at']) : null
                                ]
                            );

                            // Dispatch job for photo upload
                            if ($item['photo']) {
                                \Log::info('Dispatching UploadCollectionPhotoJob', [
                                    'collection_id' => $slider->id,
                                    'photo_url' => $item['photo'],
                                ]);
                                error_log("UploadPhotoJob $slider->id => " . $item['photo']);
                                dispatch(new UploadPhotoJob($slider, 'https://admin.bybest.shop/storage/sliders/' . $item['photo'], 'photo', $venue));
                            }

                            $processedCount++;
                        }
                    });
                }

                error_log("Processed {$processedCount} sliders so far.");
                \Log::info("Processed {$processedCount} sliders so far.");

                $page++;
            } catch (\Throwable $th) {
                \Log::error('Error in sliders sync', [
                    'error' => $th->getMessage(),
                    'trace' => $th->getTraceAsString()
                ]);
                error_log($th->getMessage());
                return response()->json([
                    "message" => "Error in sliders sync",
                    "error" => $th->getMessage()
                ], 503);
            }
        } while (count($sliders) == $perPage); //

        return response()->json([
            'message' => 'sliders sync completed successfully',
            'processed_count' => $processedCount,
            'skipped_count' => $skippedCount
        ], 200);
    }

    public function mainMenuSync(Request $request): \Illuminate\Http\JsonResponse
    {
        $venue = $this->venueService->adminAuthCheck();
        $page = 1;
        $perPage = 100;
        $batchSize = 50;
        $skippedCount = 0;
        $processedCount = 0;

        do {
            try {

                $response = Http::withHeaders([
                    'X-App-Key' => $this->bybestApiKey
                ])->get($this->bybestApiUrl . 'mainmenu-sync', [
                            'page' => $page,
                            'per_page' => $perPage
                        ]);

                if (!$response->successful()) {
                    return response()->json(['message' => 'Failed to fetch data from ByBest API'], 500);
                }

                $bybestData = $response->json();

                if (empty($bybestData) || !isset($bybestData['data'])) {
                    break; // No more data to process
                }

                $groups = $bybestData['data'];

                foreach (array_chunk($groups, $batchSize) as $batch) {
                    DB::transaction(function () use ($batch, $venue, &$skippedCount, &$processedCount) {
                        foreach ($batch as $item) {
                            // Make sure the required fields are available
                            if (!isset($item['id'])) {
                                \Log::error('Product missing id', ['item' => $item]);
                                $skippedCount++;
                                continue;
                            }

                            $groupId = $item['group_id'] ? DB::table('groups')->where('bybest_id', $item['group_id'])->value('id') : null;
                            $typeId = $item['type_id'] ? DB::table('bb_menu_type')->where('bybest_id', $item['type_id'])->value('id') : null;

                            $title = json_decode($item['title']);
                            BbMainMenu::updateOrCreate(
                                ['bybest_id' => $item['id']],
                                [
                                    'venue_id' => $venue->id,
                                    'bybest_id' => $item['id'],
                                    'type_id' => $typeId,
                                    'group_id' => $groupId,
                                    'title' => $title,
                                    'photo' => 'https://admin.bybest.shop/storage/menues/' . $item['photo'],
                                    'order' => $item['order'],
                                    'link' => $item['link'],
                                    'focused' => $item['focused'],
                                    'created_at' => $item['created_at'],
                                    'updated_at' => $item['updated_at'],
                                    'deleted_at' => $item['deleted_at'],
                                ]
                            );

                            $processedCount++;
                        }
                    });
                }

                error_log("Processed {$processedCount} groups so far.");

                $page++;
            } catch (\Throwable $th) {
                error_log("Error in groups sync " . $th->getMessage());
                // return response()->json([
                //     "message" => "Error in groups sync",
                //     "error" => $th->getMessage()
                // ], 503);
            }
        } while (count($groups) == $perPage);

        return response()->json([
            'message' => 'groups sync completed successfully',
            'processed_count' => $processedCount,
            'skipped_count' => $skippedCount
        ], 200);
    }
}
