<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use App\Models\Order;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;
//use App\Mail\BKTInvoice;

class BktPaymentService
{
    public static function getStaticOrderDetails(): array
    {
        return [
            'id' => 123,
            'total' => 100.00,
            'total_eur' => 85.00,
        ];
    }

    public function initiatePayment(array $orderDetails): array
    {
        $paymentData = $this->preparePaymentData([
            'default' => $orderDetails['total'],
            'eur' => $orderDetails['total'] / 100 * 0.008,
            // longer order id strings  BBSH0P-$orderDetails['id']
            'order_id' => 'BBSH0P-' . $orderDetails['id']
            //'order_id' => $orderDetails['id']
        ]);

        $bktUrl =  env('BKT_URL', 'https://testvpos.asseco-see.com.tr/fim/est3Dgate');

        return [
            'url' => $bktUrl,
            'data' => $paymentData
        ];
    }

    public function handleWebhook($request)
    {
        Log::info('BKT Webhook received', $request->all());

        try {
            $currency = '008';
            $storekey = env('BKT_SKEY_' . $currency);


            // todo: disable hash verification for now
//            if (!$this->verifyHash($request, $storekey)) {
//
//                Log::error('BKT Webhook: Hash verification failed');
//                return $this->redirectToReactRoute('payment', ['error' => 'Invalid hash']);
//            }



            $order = Order::find($request->oid);

            if (!$order) {
                Log::error('BKT Webhook: Order not found', ['order_id' => $request->oid]);
                return $this->redirectToReactRoute('payment', ['error' => 'Order not found']);
            }

            $status = $this->determineOrderStatus($request);

            $this->updateOrder($order, $status, $request);

            if ($status === 'success' && !$request->canceled) {
                $this->sendInvoice($order);
            }

            return $this->handleRedirect($request, $status, $order);

        } catch (\Exception $e) {
            Log::error('BKT Webhook error: ' . $e->getMessage(), ['exception' => $e]);
            return $this->redirectToReactRoute('payment', ['error' => 'An unexpected error occurred']);
        }
    }

    private function verifyHash($request, $storekey)
    {
        $hashData = $request->except(['hash', 'encoding']);
        $calculatedHash = $this->calculateHashV3($hashData, $storekey);
        return $calculatedHash === $request->hash;
    }

    private function determineOrderStatus($request)
    {
        // Implement logic to determine order status based on BKT response
        // This is a placeholder - adjust according to BKT's actual response structure
        if ($request->mdStatus == '1' && $request->Response == 'Approved') {
            return 'success';
        } elseif ($request->canceled) {
            return 'canceled';
        } else {
            return 'failed';
        }
    }

    private function updateOrder($order, $status, $request)
    {
        $statusId = $status === 'success' ? 3 : 1;
        $paymentId = $status === 'success' ? 5 : 4;

        $order->update([
            'meta' => $request->except('hash', 'encoding'),
            'status_id' => $statusId,
            'payment_id' => $paymentId,
        ]);

        Log::info('BKT Webhook: Order updated', ['order_id' => $order->id, 'status' => $status]);
    }

    private function sendInvoice($order)
    {
        try {
            // Uncomment the following line when BKTInvoice is implemented
            // Mail::to($order->billing_email)->send(new \App\Mail\BKTInvoice($order));
            Log::info('BKT Invoice sent', ['order_id' => $order->id]);
        } catch (\Exception $e) {
            Log::error('Failed to send BKT invoice', ['order_id' => $order->id, 'error' => $e->getMessage()]);
        }
    }

    private function handleRedirect($request, $status, $order)
    {
        if ($status === 'success') {

            $route = $request->type == 'WO' ? 'cart/success' : 'checkout-success-gift-card';
            return $this->redirectToReactRoute($route, ['order_id' => $order->id]);
        } else {

            $error = $status === 'canceled' ? 'Payment was cancelled' : 'Payment failed';
            return $this->redirectToReactRoute('payment', ['error' => $error]);
        }
    }

    private function redirectToReactRoute($route, $params = [])
    {
        $baseUrl = config('services.bkt.web_redirect_url');
        $url = $baseUrl . '/' . $route;
        if (!empty($params)) {
            $url .= '?' . http_build_query($params);
        }

        \Log::info('Redirecting to: ' . $url);

//        $html = <<<HTML
//    <!DOCTYPE html>
//    <html lang="en">
//    <head>
//        <meta charset="UTF-8">
//        <meta name="viewport" content="width=device-width, initial-scale=1.0">
//        <title>Redirecting...</title>
//    </head>
//    <body>
//        <h1>Redirecting...</h1>
//        <p>If you are not redirected automatically, please <a href="{$url}">click here</a>.</p>
//        <script>
//            window.onload = function() {
//                window.location.href = "{$url}";
//            }
//        </script>
//    </body>
//    </html>
//    HTML;
//
//        return response($html)->header('Content-Type', 'text/html');

        // TODO: maybe use html with loader
        return redirect()->away($url);
    }

    private function calculateHashV3(array $data, string $storekey)
    {
        // Remove hash and encoding from the data if present
        unset($data['hash'], $data['encoding']);

        // Sort the data array by keys
        ksort($data);

        // Build the hash string
        $hashStr = '';
        foreach ($data as $key => $value) {
            // Include empty values in the hash string
            $escapedValue = $this->escapeSpecialChars($value);
            $hashStr .= $escapedValue . '|';
        }

        // Append the storekey
        $hashStr .= $storekey;

        // Calculate SHA512 hash
        $hash = hash('sha512', $hashStr);

        // Return base64 encoded hash
        return base64_encode(pack('H*', $hash));
    }

    private function escapeSpecialChars($value)
    {
        // Escape backslashes first, then escape pipes
        $escaped = str_replace('\\', '\\\\', $value);
        $escaped = str_replace('|', '\\|', $escaped);
        return $escaped;
    }

    public function preparePaymentData(array $arr)
    {
        $oid = data_get($arr, 'order_id');
        $currency = '008';

        $data = [
            'clientid' => env('BKT_CID_' . $currency),
            'oid' => $oid,
            'amount' => data_get($arr, 'default'),
            'okUrl' => route('webhook.bkt', ['oid' => $oid, 'type' => 'WO']),
            'failUrl' => route('webhook.bkt', ['canceled' => 1, 'oid' => $oid, 'type' => 'WO']),
            'trantype' => 'Auth',
            'instalment' => '',
            'rnd' => Str::random(20),
            'currency' => $currency,
            'storetype' => '3d_pay_hosting',
            'lang' => 'en',
            'hashAlgorithm' => 'ver3',
            'refreshtime' => '10'
        ];

        $storekey = env('BKT_SKEY_' . $currency);
        $data['hash'] = $this->calculateHashV3($data, $storekey);

        return $data;
    }
}
