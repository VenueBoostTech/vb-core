<?php

namespace App\Services;

use App\Models\AppInvoice;
use PhpOffice\PhpSpreadsheet\Writer\Pdf;

class InvoicePdfService
{
    public function generatePdf(AppInvoice $invoice): string
    {
        $data = [
            'invoice' => $invoice->load(['client', 'items', 'serviceRequest.service']),
            'company' => [
                'name' => config('app.company_name'),
                'address' => config('app.company_address'),
                'phone' => config('app.company_phone'),
                'email' => config('app.company_email'),
                'logo' => public_path('images/logo.png')
            ]
        ];

        $pdf = PDF::loadView('pdfs.invoice', $data);

        // Optional: Customize PDF settings
        $pdf->setPaper('a4');
        $pdf->setOption([
            'dpi' => 150,
            'defaultFont' => 'sans-serif',
            'isHtml5ParserEnabled' => true,
            'isRemoteEnabled' => true
        ]);

        return $pdf->output();
    }
}
