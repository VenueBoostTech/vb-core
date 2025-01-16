<?php

namespace App\Http\Controllers\AppSuite\Staff;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Services\VenueService;
use App\Models\ConstructionSiteGallery;
use Illuminate\Support\Facades\Storage;
use Illuminate\Http\JsonResponse;
use App\Models\ConstructionSite;

class ConstructionSiteGalleryController extends Controller
{

    protected VenueService $venueService;

    public function __construct(VenueService $venueService)
    {
        $this->venueService = $venueService;
    }
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request, $id)
    {
        $authEmployee = $this->venueService->employee();
        if ($authEmployee instanceof JsonResponse) return $authEmployee;

        $constructionSite = ConstructionSite::where('id', $id)->first();
        if (!$constructionSite instanceof ConstructionSite) {
            return response()->json(['error' => 'Construction site not found'], 404);
        }

        $perPage = $request->query('per_page', 10);
        $page = $request->query('page', 1); 
        $galleries = ConstructionSiteGallery::with('uploader')
                                ->where('construction_site_id', $constructionSite->id)
                                ->where('venue_id', $authEmployee->restaurant_id)   
                                ->orderBy('created_at', 'desc')
                                ->paginate($perPage, ['*'], 'page', $page);
        return response()->json([
            'message' => 'Construction site galleries fetched successfully',
            'data' => $galleries->items(),
            'pagination' => [
                'total' => $galleries->total(),
                'per_page' => $galleries->perPage(),
                'current_page' => $galleries->currentPage(),
                'last_page' => $galleries->lastPage()
            ]
        ], 200);

    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create(Request $request, $id)
    {
        $authEmployee = $this->venueService->employee();
        if ($authEmployee instanceof JsonResponse) return $authEmployee;

        $constructionSite = ConstructionSite::where('id', $id)->first();
        if (!$constructionSite instanceof ConstructionSite) {
            return response()->json(['error' => 'Construction site not found'], 404);
        }

        try {
            $validated = $request->validate([
                'photo' => 'required_without:video|image|max:15360',
                'video' => 'required_without:photo|mimes:mp4,avi,mov|max:102400',
            ]);

            // Handle photo upload
            if ($request->hasFile('photo')) {
                $photoFile = $request->file('photo');
                $photoPath = Storage::disk('s3')->putFile('construction_site_galleries/images', $photoFile);
                $validated['photo_path'] = $photoPath;
            }
            
            if ($request->hasFile('video')) {
                $videoFile = $request->file('video');
                $videoPath = Storage::disk('s3')->putFile('construction_site_galleries/videos', $videoFile);
                $validated['video_path'] = $videoPath;
            }

            if (!$request->hasFile('photo') && !$request->hasFile('video')) {
                return response()->json(['error' => 'No media files uploaded'], 400);
            }


            $gallery = ConstructionSiteGallery::create([
                'venue_id' => $authEmployee->restaurant_id,
                'construction_site_id' => $constructionSite->id,
                'uploader_id' => $authEmployee->id,
                'photo_path' => isset($validated['photo_path']) ? $validated['photo_path'] : null,
                'video_path' => isset($validated['video_path']) ? $validated['video_path'] : null,
            ]);

            return response()->json(['message' => 'Construction site gallery created successfully', 'data' => $gallery], 201);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json(['message' => 'Validation error', 'errors' => $e->errors()], 422);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Error creating construction site gallery', 'error' => $e->getMessage()], 500);
        }
    }

  
    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        $authEmployee = $this->venueService->employee();
        if ($authEmployee instanceof JsonResponse) return $authEmployee;

        $gallery = ConstructionSiteGallery::where('id', $id)->first();
        if (!$gallery instanceof ConstructionSiteGallery) {
            return response()->json(['error' => 'Construction site gallery not found'], 404);
        }

        $gallery->delete();
        return response()->json(['message' => 'Construction site gallery deleted successfully'], 200);
    }
}
