<?php


namespace App\Http\Controllers\v3\Whitelabel;

use App\Http\Controllers\Controller;

use Illuminate\Http\Request;
use App\Services\BktPaymentService;
use App\Models\Order;
use Illuminate\Support\Facades\Mail;
//use App\Mail\BKTInvoice;

    class BktPaymentController extends Controller
{
    protected $bktPaymentService;

    public function __construct(BktPaymentService $bktPaymentService)
    {
        $this->bktPaymentService = $bktPaymentService;
    }

    public function initiatePayment(Request $request)
    {
        $orderDetails = $this->bktPaymentService->getStaticOrderDetails();

        $paymentInfo = $this->bktPaymentService->initiatePayment($orderDetails);

        return response()->json([
            'status' => 'success',
            'payment_url' => $paymentInfo['url'],
            'payment_data' => $paymentInfo['data']
        ]);
    }


        public function webhook(Request $request)
        {
            return $this->bktPaymentService->handleWebhook($request);

            // Force the content to be sent as-is
            // return response($result->getContent(), 200)->header('Content-Type', 'text/html');
        }

}
