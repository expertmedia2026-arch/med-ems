<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\BillingIndexRequest;
use App\Http\Requests\PayInvoiceRequest;
use App\Http\Requests\PaystackCallbackRequest;
use App\Http\Requests\PortalInvoicePaymentRequest;
use App\Http\Resources\Api\V1\InvoiceResource;
use App\Http\Resources\Api\V1\PaymentReceiptResource;
use App\Models\Invoice;
use App\Models\Payment;
use App\Services\CashierService;
use App\Services\OnlinePaymentService;
use App\Services\PortalInvoiceService;
use Illuminate\Http\Request;

class BillingOperationsController extends Controller
{
    public function index(BillingIndexRequest $request, CashierService $cashier): array
    {
        $data = $cashier->indexData($request->validated('status'), $request->validated('q'));

        return [
            'invoices' => InvoiceResource::collection($data['invoices']),
            'collected_today' => (float) $data['collectedToday'],
            'outstanding' => (float) $data['outstanding'],
            'online_payments_for_review' => $data['review']->map(fn ($attempt) => [
                'id' => $attempt->id,
                'invoice_id' => $attempt->invoice_id,
                'patient' => $attempt->patient?->full_name,
                'amount' => (float) $attempt->amount,
                'reference' => $attempt->reference,
            ])->values(),
        ];
    }

    public function show(Invoice $invoice, CashierService $cashier): InvoiceResource
    {
        return new InvoiceResource($cashier->invoiceDetails($invoice));
    }

    public function pay(PayInvoiceRequest $request, Invoice $invoice, CashierService $cashier): array
    {
        $payment = $cashier->recordPayment($invoice, $request->validated(), $request->user()->id);
        abort_unless($payment, 409, 'Some selected items were already paid or are no longer valid. Refresh the invoice.');

        return [
            'payment' => [
                'id' => $payment->id,
                'receipt_number' => $payment->receipt_number,
                'amount' => (float) $payment->amount,
                'method' => $payment->method,
                'reference' => $payment->reference,
            ],
            'invoice' => new InvoiceResource($cashier->invoiceDetails($invoice->refresh())),
        ];
    }

    /** Return receipt data and HTML rendered from the web receipt view for mobile printing. */
    public function receipt(Payment $payment, CashierService $cashier): array
    {
        $payment = $cashier->receiptDetails($payment);

        return [
            'receipt' => (new PaymentReceiptResource($payment))->resolve(request()),
            'receipt_number' => $payment->receipt_number,
            'amount' => (float) $payment->amount,
            'method' => $payment->method,
            'reference' => $payment->reference,
            'created_at' => $payment->created_at->toIso8601String(),
            'patient' => $payment->invoice->patient?->full_name,
            'invoice_number' => $payment->invoice->invoice_number,
            'items' => $payment->items->map(fn ($item) => ['description' => $item->description, 'total' => (float) $item->total])->values(),
            'printable' => [
                'content_type' => 'text/html; charset=UTF-8',
                'html' => view('billing.receipt', compact('payment'))->render(),
            ],
        ];
    }

    public function patientInvoices(Request $request, PortalInvoiceService $invoices): array
    {
        $patient = $this->patient($request);
        $data = $invoices->indexData($patient);

        return ['invoices' => InvoiceResource::collection($data['invoices']), 'outstanding' => $data['outstanding']];
    }

    public function patientInvoice(Request $request, Invoice $invoice, PortalInvoiceService $invoices): array
    {
        $invoices->authorizePatientInvoice($request->user()->patient_id, $invoice);
        $details = $invoices->invoiceDetails($invoice);

        return [
            'invoice' => new InvoiceResource($invoice->load('patient', 'items')),
            'payable_items' => $details['payable']->map(fn ($item) => ['id' => $item->id, 'description' => $item->description, 'total' => (float) $item->total])->values(),
            'waiting_items' => $details['waiting']->map(fn ($item) => ['id' => $item->id, 'description' => $item->description, 'total' => (float) $item->total])->values(),
        ];
    }

    public function startOnlinePayment(PortalInvoicePaymentRequest $request, Invoice $invoice, PortalInvoiceService $invoices, OnlinePaymentService $payments): array
    {
        $invoices->authorizePatientInvoice($request->user()->patient_id, $invoice);
        $checkout = $invoices->startOnlinePayment($invoice, $request->validated('item_ids'), $request->user(), $payments);
        abort_unless($checkout['status'] === 'started', 422, match ($checkout['status']) {
            'invalid_items' => 'Some selected items cannot be paid online.',
            'pending_overlap' => 'A payment for these items is already in progress.',
            'stock_unavailable' => 'There is not enough available stock for one or more selected drug items. Contact the pharmacy.',
            default => 'We could not start the payment. Please try again.',
        });

        return ['checkout_url' => $checkout['url'], 'status' => 'started'];
    }

    public function confirmOnlinePayment(PaystackCallbackRequest $request, OnlinePaymentService $payments): array
    {
        $reference = $request->validated('reference') ?? $request->validated('trxref');
        $attempt = $payments->findForPatient($reference, (int) $request->user()->patient_id);
        abort_unless($attempt, 404);
        try {
            $attempt = $payments->confirm($reference) ?? $attempt;
        } catch (\Throwable $exception) {
            report($exception);
        }

        return ['status' => $attempt->status, 'invoice_id' => $attempt->invoice_id, 'amount' => (float) $attempt->amount];
    }

    /** Return a patient's own receipt data and the shared web printable layout. */
    public function patientReceipt(Request $request, Payment $payment, PortalInvoiceService $invoices): array
    {
        $payment = $invoices->receiptForPatient($payment, $request->user()->patient_id);

        return [
            'receipt' => (new PaymentReceiptResource($payment))->resolve($request),
            'receipt_number' => $payment->receipt_number,
            'amount' => (float) $payment->amount,
            'method' => $payment->method,
            'reference' => $payment->reference,
            'created_at' => $payment->created_at->toIso8601String(),
            'invoice_number' => $payment->invoice->invoice_number,
            'items' => $payment->items->map(fn ($item) => ['description' => $item->description, 'total' => (float) $item->total])->values(),
            'printable' => [
                'content_type' => 'text/html; charset=UTF-8',
                'html' => view('billing.receipt', compact('payment'))->render(),
            ],
        ];
    }

    private function patient(Request $request)
    {
        $patient = $request->user()->patient;
        abort_unless($patient, 403, 'A patient account is required.');

        return $patient;
    }
}
