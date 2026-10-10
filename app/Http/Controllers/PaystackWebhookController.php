<?php

namespace App\Http\Controllers;

use App\Services\OnlinePaymentService;
use App\Services\PaystackGateway;
use Illuminate\Http\Request;

class PaystackWebhookController extends Controller
{
    public function __invoke(Request $request, PaystackGateway $gateway, OnlinePaymentService $payments)
    {
        // Only genuine Paystack calls carry a valid signature
        if (! $gateway->validSignature($request->getContent(), $request->header('x-paystack-signature'))) {
            abort(401);
        }

        $reference = $request->input('data.reference');

        if ($request->input('event') === 'charge.success' && $reference) {
            try {
                $payments->confirm($reference);
            } catch (\Throwable $e) {
                report($e);

                return response('retry', 500); // Paystack will call again
            }
        }

        return response('ok');
    }
}