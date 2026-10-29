<?php

namespace App\Http\Controllers;

use App\Models\OnlinePayment;
use App\Services\OnlinePaymentService;
use App\Services\PaystackGateway;
use App\Support\HospitalContext;
use Illuminate\Http\Request;

/**
 * Verifies Paystack webhook signatures and delegates successful payment confirmations.
 */
class PaystackWebhookController extends Controller
{
    /**
     * Resolves tenant context for the referenced payment, rejects invalid signatures, and acknowledges or retries confirmation.
     */
    public function __invoke(Request $request, PaystackGateway $gateway, OnlinePaymentService $payments, HospitalContext $context)
    {
        $reference = $request->input('data.reference');
        if ($reference) {
            $attempt = OnlinePayment::withoutGlobalScope('hospital')->where('reference', $reference)->first();
            if ($attempt) {
                $context->set((int) $attempt->hospital_id);
            }
        }

        // Only genuine Paystack calls carry a valid signature
        if (! $gateway->validSignature($request->getContent(), $request->header('x-paystack-signature'))) {
            abort(401);
        }

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
