<?php

namespace App\Http\Controllers\AccountingFinance;

use App\Http\Controllers\Controller;
use App\Models\AccountingFinance\Company;
use App\Models\AccountingFinance\Document;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\JsonResponse;

class DocumentManagementController extends Controller
{
    public function index(): JsonResponse
    {
        $company = $this->getCompany();
        if (!$company) {
            return response()->json(['message' => 'Company not found'], 404);
        }

        $documents = $company->documents()->get()->map(function ($document) {
            return [
                'id' => $document->id,
                'name' => $document->name,
                'type' => $document->type,
                'upload_date' => $document->upload_date,
                'file_url' => $document->getFileUrl(),
                'file_size' => $document->getFileSize(),
                'file_extension' => $document->getFileExtension(),
                'is_image' => $document->isImage(),
                'is_pdf' => $document->isPdf()
            ];
        });

        return response()->json([
            'documents' => $documents,
            'document_count_by_type' => Document::getDocumentCountByType(),
            'recent_documents' => Document::getRecentDocuments(5)
        ]);
    }

    public function uploadDocument(Request $request): JsonResponse
    {
        $company = $this->getCompany();
        if (!$company) {
            return response()->json(['message' => 'Company not found'], 404);
        }

        $validatedData = $request->validate([
            'name' => 'required|string',
            'file' => 'required|file',
            'type' => 'required|string'
        ]);

        $path = $request->file('file')->store('documents');

        $document = $company->documents()->create([
            'name' => $validatedData['name'],
            'file_path' => $path,
            'type' => $validatedData['type'],
            'upload_date' => now()
        ]);

        return response()->json([
            'message' => 'Document uploaded successfully',
            'document' => [
                'id' => $document->id,
                'name' => $document->name,
                'type' => $document->type,
                'upload_date' => $document->upload_date,
                'file_url' => $document->getFileUrl(),
                'file_size' => $document->getFileSize(),
                'file_extension' => $document->getFileExtension()
            ]
        ], 201);
    }

    public function searchDocuments(Request $request): JsonResponse
    {
        $company = $this->getCompany();
        if (!$company) {
            return response()->json(['message' => 'Company not found'], 404);
        }

        $query = $request->get('query');
        $type = $request->get('type');
        $startDate = $request->get('start_date');
        $endDate = $request->get('end_date');

        $documents = $company->documents()
            ->when($query, function ($q) use ($query) {
                return $q->where('name', 'like', "%{$query}%");
            })
            ->when($type, function ($q) use ($type) {
                return $q->byType($type);
            })
            ->when($startDate && $endDate, function ($q) use ($startDate, $endDate) {
                return $q->byUploadDateRange($startDate, $endDate);
            })
            ->get()
            ->map(function ($document) {
                return [
                    'id' => $document->id,
                    'name' => $document->name,
                    'type' => $document->type,
                    'upload_date' => $document->upload_date,
                    'file_url' => $document->getFileUrl(),
                    'file_size' => $document->getFileSize(),
                    'file_extension' => $document->getFileExtension()
                ];
            });

        return response()->json($documents);
    }

    private function getCompany()
    {
        return Company::where('user_id', Auth::id())->first();
    }
}
