<?php

namespace App\Http\Controllers;

use App\Http\Requests\PaystackCallbackRequest;
use App\Http\Requests\PortalInvoicePaymentRequest;
use App\Models\Invoice;
use App\Models\Payment;
use App\Services\OnlinePaymentService;
use App\Services\PortalInvoiceService;
use Illuminate\Http\Request;

class PortalInvoiceController extends Controller
{
    public function index(Request $request, PortalInvoiceService $invoices)
    {
        $patient = $this->patient($request);
        $data = $invoices->indexData($patient);

        return view('portal.invoices.index', $data);
    }

    public function show(Request $request, Invoice $invoice, PortalInvoiceService $invoices)
    {
        $invoices->authorizePatientInvoice($request->user()->patient_id, $invoice);
        $data = $invoices->invoiceDetails($invoice);

        return view('portal.invoices.show', ['invoice' => $invoice] + $data);
    }

    /** Start an online payment for the ticked items and send the patient to the gateway. */
    public function pay(PortalInvoicePaymentRequest $request, Invoice $invoice, OnlinePaymentService $payments, PortalInvoiceService $invoices)
    {
        $invoices->authorizePatientInvoice($request->user()->patient_id, $invoice);

        $data = $request->validated();

        $checkout = $invoices->startOnlinePayment($invoice, $data['item_ids'], $request->user(), $payments);

        if ($checkout['status'] !== 'started') {
            $message = match ($checkout['status']) {
                'invalid_items' => 'Some selected items cannot be paid online (already paid, or not ready yet). Please try again.',
                'pending_overlap' => 'A payment for these items is already in progress. Complete it, or try again in a few minutes.',
                default => 'We could not start the payment. Please try again in a moment.',
            };

            return back()->with('error', $message);
        }

        return redirect()->away($checkout['url']);
    }

    /** Where the gateway sends the patient back to. The result is verified with the gateway, never trusted from the URL. */
    public function callback(PaystackCallbackRequest $request, OnlinePaymentService $payments)
    {
        $data = $request->validated();
        $reference = $data['reference'] ?? $data['trxref'];

        abort_unless($request->user()->patient_id, 403);
        $attempt = $payments->findForPatient($reference, (int) $request->user()->patient_id);

        abort_unless($attempt, 404);

        try {
            $attempt = $payments->confirm($reference) ?? $attempt;
        } catch (\Throwable $e) {
            report($e);
        }

        $back = redirect()->route('portal.invoices.show', $attempt->invoice_id);

        return match ($attempt->status) {
            'success' => $back->with('success', 'Payment received. Thank you! Your receipt is listed under Payments.'),
            'review' => $back->with('error', 'We received your payment but need to check it against your bill. The hospital will contact you.'),
            'failed' => $back->with('error', 'The payment was not completed. You can try again.'),
            default => $back->with('error', 'We are still waiting for the bank to confirm your payment. Refresh this page in a minute. If money left your account, it will show here.'),
        };
    }

    public function receipt(Request $request, Payment $payment, PortalInvoiceService $invoices)
    {
        $payment = $invoices->receiptForPatient($payment, $request->user()->patient_id);

        return view('billing.receipt', compact('payment'));
    }

    private function patient(Request $request)
    {
        $patient = $request->user()->patient;
        abort_unless($patient, 403);

        return $patient;
    }
}
