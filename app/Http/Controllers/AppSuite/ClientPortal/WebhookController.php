<?php

namespace App\Http\Controllers\AppSuite\ClientPortal;

use App\Http\Controllers\Controller;
use App\Models\AppInvoice;
use App\Models\AppInvoicePayment;
use App\Services\AppInvoiceService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Stripe\Stripe;
use Stripe\Webhook;
use Stripe\Event;
use Stripe\PaymentIntent;

class WebhookController extends Controller
{
    protected AppInvoiceService $invoiceService;

    public function __construct(AppInvoiceService $invoiceService)
    {
        $this->invoiceService = $invoiceService;
    }

    public function handleStripeWebhook(Request $request)
    {
        // Set your Stripe secret key
        Stripe::setApiKey(env('STRIPE_SECRET'));

        $payload = $request->getContent();
        $sigHeader = $request->header('Stripe-Signature');
        $webhookSecret = env('STRIPE_WEBHOOK_SECRET');

        try {
            $event = Webhook::constructEvent($payload, $sigHeader, $webhookSecret);
        } catch (\Exception $e) {
            Log::error('Stripe Webhook Error: ' . $e->getMessage());
            return response()->json(['error' => 'Invalid signature'], 400);
        }

        switch ($event->type) {
            case 'payment_intent.succeeded':
                return $this->handleSuccessfulPayment($event->data->object);

            case 'payment_intent.payment_failed':
                return $this->handleFailedPayment($event->data->object);

            default:
                return response()->json(['message' => 'Unhandled event'], 200);
        }
    }

    protected function handleSuccessfulPayment(PaymentIntent $paymentIntent)
    {
        $invoice = AppInvoice::where('stripe_payment_intent_id', $paymentIntent->id)->first();

        if (!$invoice) {
            return response()->json(['error' => 'Invoice not found'], 404);
        }

        $invoice->payments()->create([
            'amount' => $paymentIntent->amount / 100,
            'payment_method' => 'card',
            'status' => AppInvoicePayment::STATUS_COMPLETED,
            'transaction_id' => $paymentIntent->id,
            'payment_date' => now(),
            'metadata' => [
                'payment_method_type' => $paymentIntent->payment_method_type,
                'last4' => $paymentIntent->charges->data[0]->payment_method_details->card->last4 ?? null
            ]
        ]);

        $invoice->update(['status' => AppInvoice::STATUS_PAID]);

        return response()->json(['message' => 'Payment recorded successfully']);
    }

    protected function handleFailedPayment(PaymentIntent $paymentIntent)
    {
        $invoice = AppInvoice::where('stripe_payment_intent_id', $paymentIntent->id)->first();

        if (!$invoice) {
            return response()->json(['error' => 'Invoice not found'], 404);
        }

        $invoice->payments()->create([
            'amount' => $paymentIntent->amount / 100,
            'payment_method' => 'card',
            'status' => AppInvoicePayment::STATUS_FAILED,
            'transaction_id' => $paymentIntent->id,
            'payment_date' => now(),
            'metadata' => [
                'error_code' => $paymentIntent->last_payment_error->code ?? null,
                'error_message' => $paymentIntent->last_payment_error->message ?? null
            ]
        ]);

        return response()->json(['message' => 'Failed payment recorded']);
    }
}
