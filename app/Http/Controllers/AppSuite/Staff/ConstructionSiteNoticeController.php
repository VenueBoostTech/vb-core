<?php

namespace App\Http\Controllers\AppSuite\Staff;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Services\VenueService;
use App\Models\ConstructionSiteNotice;
use Illuminate\Support\Facades\Storage;
use Illuminate\Http\JsonResponse;

class ConstructionSiteNoticeController extends Controller
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
    public function index(Request $request, $constructionSiteId)
    {
        $authEmployee = $this->venueService->employee();
        if ($authEmployee instanceof JsonResponse) return $authEmployee;

        $perPage = $request->query('per_page', 10);
        $page = $request->query('page', 1); 

        $constructionSiteNotices = ConstructionSiteNotice::where('venue_id', $authEmployee->restaurant_id)
                                ->when($constructionSiteId, function ($query) use ($constructionSiteId) {
                                    return $query->where('construction_site_id', $constructionSiteId);
                                })
                                ->orderBy('created_at', 'desc')
                                ->paginate($perPage, ['*'], 'page', $page);

        return response()->json([
            'message' => 'Construction site notices fetched successfully',
            'data' => $constructionSiteNotices->items(),
            'pagination' => [
                'total' => $constructionSiteNotices->total(),
                'per_page' => $constructionSiteNotices->perPage(),
                'current_page' => $constructionSiteNotices->currentPage(),
                'last_page' => $constructionSiteNotices->lastPage(),
            ]
        ]);
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create(Request $request, $constructionSiteId)
    {
        $authEmployee = $this->venueService->employee();
        if ($authEmployee instanceof JsonResponse) return $authEmployee;
        try {
            $validated = $request->validate([
                'title' => 'required|string|max:255',
                'description' => 'nullable|string',
                'type' => 'required|string',
                'attachment' => 'nullable|file|mimes:jpeg,png,jpg,pdf|max:2048',
            ]);

            if ($request->hasFile('attachment')) {
                $attachmentPath = Storage::disk('s3')->put('construction-site-notices', $request->file('attachment'));
                $validated['attachment'] = $attachmentPath;
            }

            $validated['venue_id'] = $authEmployee->restaurant_id;
            $validated['construction_site_id'] = $constructionSiteId;

            $constructionSiteNotice = ConstructionSiteNotice::create($validated);

            return response()->json([
                'message' => 'Construction site notice created successfully',
                'data' => $constructionSiteNotice
            ]);
        }  catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json(['message' => 'Validation error', 'errors' => $e->errors()], 422);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Error creating construction site notice', 'error' => $e->getMessage()], 500);
        }
    }

   
    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {
        $authEmployee = $this->venueService->employee();
        if ($authEmployee instanceof JsonResponse) return $authEmployee;

        try {
            $validated = $request->validate([
                'title' => 'required|string|max:255',
                'description' => 'nullable|string',
                'type' => 'required|string',
                'attachment' => 'nullable|file|mimes:jpeg,png,jpg,pdf|max:2048',
            ]);

            $constructionSiteNotice = ConstructionSiteNotice::find($id);
            if (!$constructionSiteNotice) {
                return response()->json(['message' => 'Construction site notice not found'], 404);
            }

            if ($request->hasFile('attachment')) {
                if (is_string($request->attachment)) {
                    unset($validated['attachment']);
                } else {
                    $attachmentPath = Storage::disk('s3')->put('construction-site-notices', $request->file('attachment'));
                    $validated['attachment'] = $attachmentPath;
                }
            }

            $constructionSiteNotice->update($validated);

            return response()->json(['message' => 'Construction site notice updated successfully', 'data' => $constructionSiteNotice]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json(['message' => 'Validation error', 'errors' => $e->errors()], 422);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Error updating construction site notice', 'error' => $e->getMessage()], 500);
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

        $constructionSiteNotice = ConstructionSiteNotice::find($id);
        if (!$constructionSiteNotice) {
            return response()->json(['message' => 'Construction site notice not found'], 404);
        }

        $constructionSiteNotice->delete();
        return response()->json(['message' => 'Construction site notice deleted successfully']);
    }
}
