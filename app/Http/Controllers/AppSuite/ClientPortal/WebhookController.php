<?php

namespace App\Http\Controllers\AppSuite\ClientPortal;

use App\Http\Controllers\Controller;
use App\Models\AppInvoice;
use App\Models\AppInvoicePayment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Stripe\Webhook;
use Stripe\PaymentIntent;
use Stripe\Stripe;

class WebhookController extends Controller
{
    public function handleStripeWebhook(Request $request)
    {
        Stripe::setApiKey(config('services.stripe.secret'));

        try {
            $payload = $request->getContent();
            $sig_header = $request->header('Stripe-Signature');
            $event = Webhook::constructEvent(
                $payload, $sig_header, config('services.stripe.webhook_secret')
            );
        } catch (\Exception $e) {
            Log::error('Stripe Webhook Error: ' . $e->getMessage());
            return response()->json(['error' => $e->getMessage()], 400);
        }

        try {
            switch ($event->type) {
                case 'payment_intent.succeeded':
                    return $this->handleSuccessfulPayment($event->data->object);

                case 'payment_intent.payment_failed':
                    return $this->handleFailedPayment($event->data->object);

                case 'payment_intent.canceled':
                    return $this->handleCanceledPayment($event->data->object);

                default:
                    return response()->json(['message' => 'Unhandled event type: ' . $event->type]);
            }
        } catch (\Exception $e) {
            Log::error('Payment Processing Error: ' . $e->getMessage());
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    protected function handleSuccessfulPayment(PaymentIntent $paymentIntent)
    {
        $invoice = AppInvoice::where('stripe_payment_intent_id', $paymentIntent->id)->first();

        if (!$invoice) {
            Log::error('Invoice not found for payment: ' . $paymentIntent->id);
            return response()->json(['error' => 'Invoice not found'], 404);
        }

        \DB::transaction(function () use ($invoice, $paymentIntent) {
            // Update payment record
            $payment = $invoice->payments()
                ->where('metadata->payment_intent_id', $paymentIntent->id)
                ->first();

            if ($payment) {
                $payment->update([
                    'status' => AppInvoicePayment::STATUS_COMPLETED,
                    'payment_date' => now(),
                    'metadata' => array_merge($payment->metadata ?? [], [
                        'stripe_payment_method' => $paymentIntent->payment_method,
                        'card_last4' => $paymentIntent->charges->data[0]->payment_method_details->card->last4 ?? null,
                        'card_brand' => $paymentIntent->charges->data[0]->payment_method_details->card->brand ?? null,
                    ])
                ]);
            }

            // Update invoice status
            $invoice->update([
                'status' => AppInvoice::STATUS_PAID,
                'payment_date' => now()
            ]);

            // Send success notification
            // event(new InvoicePaidEvent($invoice));
        });

        return response()->json(['message' => 'Payment processed successfully']);
    }

    protected function handleFailedPayment(PaymentIntent $paymentIntent)
    {
        $invoice = AppInvoice::where('stripe_payment_intent_id', $paymentIntent->id)->first();

        if (!$invoice) {
            Log::error('Invoice not found for failed payment: ' . $paymentIntent->id);
            return response()->json(['error' => 'Invoice not found'], 404);
        }

        \DB::transaction(function () use ($invoice, $paymentIntent) {
            // Update payment record
            $payment = $invoice->payments()
                ->where('metadata->payment_intent_id', $paymentIntent->id)
                ->first();

            if ($payment) {
                $payment->update([
                    'status' => AppInvoicePayment::STATUS_FAILED,
                    'metadata' => array_merge($payment->metadata ?? [], [
                        'error_code' => $paymentIntent->last_payment_error->code ?? null,
                        'error_message' => $paymentIntent->last_payment_error->message ?? null,
                        'failure_reason' => $paymentIntent->last_payment_error->decline_code ?? null,
                    ])
                ]);
            }

//             Send failure notification
//            event(new InvoicePaymentFailedEvent($invoice, $paymentIntent->last_payment_error->message ?? 'Payment failed'));
        });

        return response()->json(['message' => 'Payment failure recorded']);
    }

    protected function handleCanceledPayment(PaymentIntent $paymentIntent)
    {
        $invoice = AppInvoice::where('stripe_payment_intent_id', $paymentIntent->id)->first();

        if (!$invoice) {
            Log::error('Invoice not found for canceled payment: ' . $paymentIntent->id);
            return response()->json(['error' => 'Invoice not found'], 404);
        }

        \DB::transaction(function () use ($invoice, $paymentIntent) {
            // Update payment record
            $payment = $invoice->payments()
                ->where('metadata->payment_intent_id', $paymentIntent->id)
                ->first();

            if ($payment) {
                $payment->update([
                    'status' => AppInvoicePayment::STATUS_FAILED,
                    'metadata' => array_merge($payment->metadata ?? [], [
                        'canceled_at' => now(),
                        'cancel_reason' => $paymentIntent->cancellation_reason ?? 'Payment canceled'
                    ])
                ]);
            }

            // Reset invoice payment method
            $invoice->update([
                'payment_method' => null,
                'stripe_payment_intent_id' => null
            ]);
        });

        return response()->json(['message' => 'Payment cancellation recorded']);
    }
}
