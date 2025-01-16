<?php

namespace App\Http\Controllers\AppSuite\Staff;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Services\VenueService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use App\Models\SafetyAudit;

class SafetyAuditController extends Controller
{
    protected VenueService $venueService;
    public function __construct(VenueService $venueService)
    {
        $this->venueService = $venueService;
    }

    public function index(Request $request, $constructionSiteId)
    {
        $authEmployee = $this->venueService->employee();
        if ($authEmployee instanceof JsonResponse) return $authEmployee;

        $perPage = $request->input('per_page', 10); 
        $page = $request->input('page', 1);
        $safetyAudits = SafetyAudit::with(['audited'])->where('venue_id', $authEmployee->restaurant_id)
            ->where('construction_site_id', $constructionSiteId)
            ->orderBy('created_at', 'desc')
            ->paginate($perPage, ['*'], 'page', $page);
        return response()->json([
            'data' => $safetyAudits->items(), 
            'pagination' => [
                'total' => $safetyAudits->total(),
                'per_page' => $safetyAudits->perPage(),
                'current_page' => $safetyAudits->currentPage(),
                'last_page' => $safetyAudits->lastPage()
            ]
        ]);
    }

    // Get all report by venue
    public function getReportByVenue(Request $request)
    {
        $authEmployee = $this->venueService->employee();
        if ($authEmployee instanceof JsonResponse) return $authEmployee;

        $perPage = $request->input('per_page', 10); 
        $page = $request->input('page', 1);
        $safetyAudits = SafetyAudit::with(['audited', 'oshaCompliance'])->where('venue_id', $authEmployee->restaurant_id)
            ->orderBy('created_at', 'desc')
            ->paginate($perPage, ['*'], 'page', $page);
        return response()->json([
            'data' => $safetyAudits->items(), 
            'pagination' => [
                'total' => $safetyAudits->total(),
                'per_page' => $safetyAudits->perPage(),
                'current_page' => $safetyAudits->currentPage(),
                'last_page' => $safetyAudits->lastPage()
            ]
        ]);
    }

    public function store(Request $request, $constructionSiteId)
    {
        $authEmployee = $this->venueService->employee();
        if ($authEmployee instanceof JsonResponse) return $authEmployee;

        $validator = Validator::make($request->all(), [
            'ppe_compliance' => 'required|file|image',
            'fall_protection' => 'required|file|image',
            'key_findings' => 'required|string',
            'osha_compliance_id' => 'required|exists:osha_compliance_equipment,id',
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()], 422);
        }
        
        $ppeCompliance = Storage::disk('s3')->put('safety_audit/ppe_compliance', $request->file('ppe_compliance'));
        $fallProtection = Storage::disk('s3')->put('safety_audit/fall_protection', $request->file('fall_protection'));

        $safetyAudit = SafetyAudit::create([
            'venue_id' => $authEmployee->restaurant_id,
            'construction_site_id' => $constructionSiteId,
            'osha_compliance_id' => $request->osha_compliance_id,
            'ppe_compliance' => $ppeCompliance,
            'fall_protection' => $fallProtection,
            'key_findings' => $request->key_findings,
            'audited_by' => $authEmployee->id
        ]);

        return response()->json(['message' => 'Safety audit created successfully', 'data' => $safetyAudit]);
    }
}
