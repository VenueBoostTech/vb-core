<?php

namespace App\Http\Controllers\v2;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class TestingLocalController extends Controller
{

    public function convertJsonToXlsx(Request $request): \Illuminate\Http\JsonResponse
    {

        $jsonContent = file_get_contents($request->file('jsonfile')->path());
        $data = json_decode($jsonContent, true);

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        $row = 1;
        $sheet->setCellValue('A' . $row, 'Path');
        $sheet->setCellValue('B' . $row, 'Key');
        $sheet->setCellValue('C' . $row, 'Value');
        $row++;

        $this->processArray($data, '', $sheet, $row);

        $filename = "output.xlsx";
        $temp_path = tempnam(sys_get_temp_dir(), $filename);
        $writer = new Xlsx($spreadsheet);
        $writer->save($temp_path);

        // Move the saved file to Laravel's storage
        $storagePath = Storage::disk('local')->put('excel_files/' . $filename, fopen($temp_path, 'r+'));
        unlink($temp_path); // Delete the temporary file

        return response()->json(['message' => 'File saved in storage.', 'path' => $storagePath]);


    }

    private function processArray($data, $path, &$sheet, &$row)
    {
        foreach ($data as $key => $value) {
            $currentPath = $path ? $path . '.' . $key : $key;
            if (is_array($value)) {
                $this->processArray($value, $currentPath, $sheet, $row);
            } else {
                $sheet->setCellValue('A' . $row, $currentPath);
                $sheet->setCellValue('B' . $row, $key);
                $sheet->setCellValue('C' . $row, $value);
                $row++;
            }
        }
    }


}
