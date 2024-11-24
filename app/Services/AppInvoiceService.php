<?php

namespace App\Services;

use App\Models\AppInvoice;
use App\Models\AppInvoicePayment;
use App\Models\ServiceRequest;
use Carbon\Carbon;
use Illuminate\Support\Str;

class AppInvoiceService
{
    public function generateFromServiceRequest(ServiceRequest $serviceRequest, array $data = []): AppInvoice
    {
        // Generate unique invoice number with venue prefix
        $prefix = strtoupper(Str::substr($serviceRequest->venue->name, 0, 3));
        $number = $prefix . '-' . date('Y') . str_pad((AppInvoice::count() + 1), 4, '0', STR_PAD_LEFT);

        // Calculate amounts from items
        $subtotal = collect($data['items'] ?? [])->sum(function ($item) {
            return ($item['quantity'] * $item['rate']);
        });

        if (empty($subtotal)) {
            $subtotal = $serviceRequest->service->base_price;
        }

        $taxRate = config('invoice.tax_rate', 0.10); // 10% default tax
        $taxAmount = $subtotal * $taxRate;
        $totalAmount = $subtotal + $taxAmount;

        // Create invoice
        $invoice = AppInvoice::create([
            'number' => $number,
            'client_id' => $serviceRequest->client_id,
            'venue_id' => $serviceRequest->venue_id,
            'service_request_id' => $serviceRequest->id,
            'status' => AppInvoice::STATUS_PENDING,
            'issue_date' => now(),
            'due_date' => Carbon::parse($data['due_date']) ?? now()->addDays(30),
            'amount' => $subtotal,
            'tax_amount' => $taxAmount,
            'total_amount' => $totalAmount,
            'payment_terms' => $data['payment_terms'] ?? 'Net 30',
            'notes' => $data['notes'] ?? "Invoice for service: {$serviceRequest->service->name}",
            'currency' => $data['currency'] ?? 'usd'
        ]);

        // Create invoice items
        if (!empty($data['items'])) {
            foreach ($data['items'] as $item) {
                $invoice->items()->create([
                    'description' => $item['description'],
                    'quantity' => $item['quantity'],
                    'rate' => $item['rate'],
                    'amount' => $item['quantity'] * $item['rate']
                ]);
            }
        } else {
            // Create default item from service
            $invoice->items()->create([
                'description' => $serviceRequest->service->name,
                'quantity' => 1,
                'rate' => $serviceRequest->service->base_price,
                'amount' => $serviceRequest->service->base_price
            ]);
        }

        return $invoice->load('items');
    }

    public function processPayment(AppInvoice $invoice, array $paymentData): AppInvoicePayment
    {
        switch ($paymentData['payment_method']) {
            case AppInvoice::PAYMENT_METHOD_CARD:
                return $this->processStripePayment($invoice, $paymentData);

            case AppInvoice::PAYMENT_METHOD_BANK:
                return $this->processBankTransfer($invoice, $paymentData);

            case AppInvoice::PAYMENT_METHOD_CASH:
                return $this->processCashPayment($invoice, $paymentData);

            default:
                throw new \Exception('Invalid payment method');
        }
    }

    protected function processStripePayment(AppInvoice $invoice, array $paymentData)
    {
        $stripe = new \Stripe\StripeClient ('sk_test_51NfR0wK9QDeYHZl0Ni35wgVXokm41feShMTuyDuibohCocPVycCnWgaQqS0LqhHPwFTyPaeEnAkytIKSbWNtVIah00d4JlNcwc');
        //$stripe = new \Stripe\StripeClient(config('services.stripe.secret'));

        try {
            $paymentIntent = $stripe->paymentIntents->create([
                'amount' => (int)($invoice->total_amount * 100), // Convert to cents
                'currency' => strtolower($invoice->currency),
                'payment_method_types' => ['card'],
                'metadata' => [
                    'invoice_id' => $invoice->id,
                    'invoice_number' => $invoice->number,
                    'client_id' => $invoice->client_id,
                    'venue_id' => $invoice->venue_id
                ],
                'description' => "Payment for invoice {$invoice->number}",
                'statement_descriptor' => substr("INV {$invoice->number}", 0, 22)
            ]);

            $invoice->update([
                'payment_method' => AppInvoice::PAYMENT_METHOD_CARD,
                'stripe_payment_intent_id' => $paymentIntent->id
            ]);

            return $invoice->payments()->create([
                'amount' => $invoice->total_amount,
                'payment_method' => AppInvoice::PAYMENT_METHOD_CARD,
                'status' => AppInvoicePayment::STATUS_PENDING,
                'metadata' => [
                    'payment_intent_id' => $paymentIntent->id,
                    'client_secret' => $paymentIntent->client_secret
                ]
            ]);
        } catch (\Stripe\Exception\ApiErrorException $e) {
            throw new \Exception('Stripe payment failed: ' . $e->getMessage());
        }
    }

    protected function processBankTransfer(AppInvoice $invoice, array $paymentData)
    {
        $paymentDueDate = Carbon::parse($paymentData['payment_due_date']);

        $invoice->update([
            'payment_method' => AppInvoice::PAYMENT_METHOD_BANK,
            'payment_due_date' => $paymentDueDate
        ]);

        return $invoice->payments()->create([
            'amount' => $invoice->total_amount,
            'payment_method' => AppInvoice::PAYMENT_METHOD_BANK,
            'status' => AppInvoicePayment::STATUS_PENDING,
            'payment_date' => $paymentDueDate,
            'metadata' => [
                'bank_name' => config('services.bank.name'),
                'account_name' => config('services.bank.account_name'),
                'account_number' => config('services.bank.account_number'),
                'routing_number' => config('services.bank.routing_number'),
                'swift_code' => config('services.bank.swift_code'),
                'reference' => $invoice->number
            ]
        ]);
    }

    protected function processCashPayment(AppInvoice $invoice, array $paymentData)
    {
        $paymentDueDate = Carbon::parse($paymentData['payment_due_date']);

        $invoice->update([
            'payment_method' => AppInvoice::PAYMENT_METHOD_CASH,
            'payment_due_date' => $paymentDueDate
        ]);

        return $invoice->payments()->create([
            'amount' => $invoice->total_amount,
            'payment_method' => AppInvoice::PAYMENT_METHOD_CASH,
            'status' => AppInvoicePayment::STATUS_PENDING,
            'payment_date' => $paymentDueDate,
            'metadata' => [
                'venue_address' => $invoice->venue->address->address_line_1 ?? '',
                'venue_phone' => $invoice->venue->phone,
                'business_email' => $invoice->venue->email,
            ]
        ]);
    }
}
