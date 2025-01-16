<?php

namespace App\Http\Controllers\v1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\InventoryReports;
use Illuminate\Support\Facades\Validator;
use Carbon\Carbon;
use App\Models\OrderProduct;
use Illuminate\Support\Facades\Storage;
use Barryvdh\DomPDF\Facade\Pdf;

class InventoryReportController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
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

        $reports = InventoryReports::where('restaurant_id', $venue->id)->get();
        return response()->json(['data' => $reports]);
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create(Request $request)
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

        // Validate the incoming request
        $validator = Validator::make($request->all(), [
            'month' => 'required|integer',
            'year' => 'required|integer',
            'product_id' => 'required|integer',
        ]);

        if ($validator->fails()) {
            return response()->json(['message' => $validator->errors()->first()], 400);
        }
        /**
         * It should be from start of month to end of month 
         */
        $startDate = Carbon::create($request->year, $request->month, 1);    
        $endDate = Carbon::create($request->year, $request->month, 1)->endOfMonth();

        
        $dataCurrentYear = OrderProduct::with('order')
                ->whereHas('order', function ($query) use ($venue) {
                    $query->where('restaurant_id', $venue->id);
                })
                ->where('product_id', $request->product_id)
                ->whereBetween('created_at', [$startDate, $endDate])
                ->selectRaw('DATE(created_at) as date, SUM(product_quantity) as total_quantity')
                ->groupBy('date')
                ->pluck('total_quantity', 'date');
             
        $dataPreviousYear = OrderProduct::with('order')
                ->whereHas('order', function ($query) use ($venue) {
                    $query->where('restaurant_id', $venue->id);
                })
                ->where('product_id', $request->product_id)
                ->whereBetween('created_at', [$startDate->subYear(), $endDate->subYear()])
                ->selectRaw('DATE(created_at) as date, SUM(product_quantity) as total_quantity')
                ->groupBy('date')
                ->pluck('total_quantity', 'date');

        // Generate report data for the entire month
        $daysInMonth = Carbon::create($request->year, $request->month)->daysInMonth;
        $reportData = [];

        for ($i = 1; $i <= $daysInMonth; $i++) {
            $date = Carbon::create($request->year, $request->month, $i)->format('Y-m-d');
            $reportData[] = [
                'date' => $date,
                $request->year => $dataCurrentYear->get($date, 0),
                $request->year - 1 => $dataPreviousYear->get($date, 0),
            ];
        }
        
        $yearsKeys = [$request->year - 1, $request->year];
        $month = Carbon::create($request->year, $request->month)->format('F');
        $year = $request->year;
        // Generate PDF
        $pdf = Pdf::loadView('reports.inventory-report', compact('reportData', 'yearsKeys', 'month', 'year'));
        // Save PDF to local storage (temporary)
        $fileName = "sales_report_{$month}_{$year}.pdf";
        $filePath = storage_path("app/public/{$fileName}");
        $pdf->save($filePath);

        // Upload pdf to s3 using storage
        $s3Path = 'reports/inventory/' . $venue->id . '/' . $month . '_' . $year . '_report.pdf';
        Storage::disk('s3')->put($s3Path, file_get_contents($filePath));

        unlink($filePath);
        $report = InventoryReports::create([
            'period' => $month . ' ' . $year,
            'restaurant_id' => $venue->id,
            'creator_user_id' => auth()->user()->id,
            'pdf_url' => $s3Path,
            'pdf_data' => json_encode($reportData),
        ]);
        return response()->json(['message' => 'Report generated successfully', 'data' => $report]);
    }

    public function show($id)
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

        // add hidden pdf_data
        $report = InventoryReports::find($id)->makeVisible('pdf_data');
        if (!$report) {
            return response()->json(['error' => 'Report not found'], 404);
        }
        $report->pdf_data = json_decode($report->pdf_data);
        return response()->json(['data' => $report]);
    }



  public function update(Request $request, $id)
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

        // Validate the incoming request
        $validator = Validator::make($request->all(), [
            'pdf_data' => 'required',
        ]);

        if ($validator->fails()) {
            return response()->json(['message' => $validator->errors()->first()], 400);
        } 

        $report = InventoryReports::find($id);
        if (!$report) {
            return response()->json(['error' => 'Report not found'], 404);
        }
        preg_match('/^(\\w+)\\s(\\d{4})$/', $report->period, $matches);

        $month = $matches[1] ?? null; // Extracted month, e.g., "October"
        $year = $matches[2] ?? null;  // Extracted year, e.g., "2015"
        
        $yearsKeys = [$year - 1, $year];
     
        // $month = Carbon::create($request->year, $request->month)->format('F');
        // $year = $request->year;
        // Generate PDF
        $reportData = $request['pdf_data'];

        $pdf = Pdf::loadView('reports.inventory-report', compact('reportData', 'yearsKeys', 'month', 'year'));
        // Save PDF to local storage (temporary)
        $fileName = "sales_report_{$month}_{$year}.pdf";
        $filePath = storage_path("app/public/{$fileName}");
        $pdf->save($filePath);

        // Upload pdf to s3 using storage
        $s3Path = 'reports/inventory/' . $venue->id . '/' . $month . '_' . $year . '_report.pdf';
        Storage::disk('s3')->put($s3Path, file_get_contents($filePath));

        unlink($filePath);

        $report->update([
            'pdf_url' => $s3Path,
            'pdf_data' => json_encode($request->pdf_data),
        ]);
        return response()->json(['message' => 'Report generated update', 'data' => $report]);


    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
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

        // add hidden pdf_data
        $report = InventoryReports::find($id);
        if (!$report) {
            return response()->json(['error' => 'Report not found'], 404);
        }
        $report->delete();
        return response()->json(['message' => 'Report deleted successfully']);
    }
}
