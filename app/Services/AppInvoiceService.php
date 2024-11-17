<?php

namespace App\Services;

use App\Models\AppInvoice;
use App\Models\AppInvoicePayment;
use App\Models\Invoice;
use App\Models\ServiceRequest;
use Carbon\Carbon;

class AppInvoiceService
{
    public function generateFromServiceRequest(ServiceRequest $serviceRequest): AppInvoice
    {
        // Generate unique invoice number
        $number = 'INV-' . date('Y') . str_pad((AppInvoice::count() + 1), 4, '0', STR_PAD_LEFT);

        // Create invoice
        $invoice = AppInvoice::create([
            'number' => $number,
            'client_id' => $serviceRequest->client_id,
            'venue_id' => $serviceRequest->venue_id,
            'service_request_id' => $serviceRequest->id,
            'status' => AppInvoice::STATUS_PENDING,
            'issue_date' => now(),
            'due_date' => now()->addDays(30), // Default 30 days
            'amount' => $serviceRequest->service->base_price,
            'tax_amount' => $serviceRequest->service->base_price * 0.1, // 10% tax
            'total_amount' => $serviceRequest->service->base_price * 1.1,
            'payment_terms' => 'Net 30',
            'notes' => "Invoice for service: {$serviceRequest->service->name}"
        ]);

        // Create invoice item
        $invoice->items()->create([
            'description' => $serviceRequest->service->name,
            'quantity' => 1,
            'rate' => $serviceRequest->service->base_price,
            'amount' => $serviceRequest->service->base_price
        ]);

        return $invoice;
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
        // Initialize Stripe payment intent
        $stripe = new \Stripe\StripeClient ('sk_test_51NfR0wK9QDeYHZl0Ni35wgVXokm41feShMTuyDuibohCocPVycCnWgaQqS0LqhHPwFTyPaeEnAkytIKSbWNtVIah00d4JlNcwc');

        $paymentIntent = $stripe->paymentIntents->create([
            'amount' => $invoice->total_amount * 100, // Stripe uses cents
            'currency' => strtolower($invoice->currency),
            'payment_method_types' => ['card'],
            'metadata' => [
                'invoice_id' => $invoice->id,
                'invoice_number' => $invoice->number
            ]
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
    }

    protected function processBankTransfer(AppInvoice $invoice, array $paymentData)
    {
        $paymentDueDate = Carbon::parse($paymentData['payment_due_date']);

        $invoice->update([
            'payment_method' => AppInvoice::PAYMENT_METHOD_BANK,
            'payment_due_date' => $paymentDueDate,
            'bank_transfer_date' => $paymentDueDate
        ]);

        return $invoice->payments()->create([
            'amount' => $invoice->total_amount,
            'payment_method' => AppInvoice::PAYMENT_METHOD_BANK,
            'status' => AppInvoicePayment::STATUS_PENDING,
            'payment_date' => $paymentDueDate,
            'metadata' => [
                'bank_details' => config('services.bank.details'),
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
            'payment_date' => $paymentDueDate
        ]);
    }
}
