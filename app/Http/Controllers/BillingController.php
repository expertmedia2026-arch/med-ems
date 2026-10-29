<?php

namespace App\Http\Controllers;

use App\Http\Requests\BillingIndexRequest;
use App\Http\Requests\PayInvoiceRequest;
use App\Models\Invoice;
use App\Models\Payment;
use App\Services\BillingOperationsService;

/**
 * Coordinates browser billing, invoice, and payment pages with BillingOperationsService.
 */
class BillingController extends Controller
{
    /**
     * Validates invoice-list filters, retrieves billing data for those filters, and renders the invoice table.
     */
    public function index(BillingIndexRequest $request, BillingOperationsService $billing)
    {
        $filters = $request->validated();

        return view('billing.index', $billing->indexData($filters['status'] ?? null, $filters['q'] ?? null));
    }

    /**
     * Loads the invoice details permitted to the current user and renders the billing detail page.
     */
    public function show(Invoice $invoice, BillingOperationsService $billing)
    {
        $invoice = $billing->invoiceDetails($invoice);

        return view('billing.show', compact('invoice'));
    }

    /**
     * Records payment for validated invoice items and redirects to the refreshed invoice or resulting receipt.
     */
    public function pay(PayInvoiceRequest $request, Invoice $invoice, BillingOperationsService $billing)
    {
        $payment = $billing->recordPayment($invoice, $request->validated(), $request->user()->id);

        if (! $payment) {
            return redirect()->route('billing.show', $invoice)
                ->with('error', 'Some selected items were already paid or are no longer valid. The page has been refreshed.');
        }

        return redirect()
            ->route('billing.receipt', $payment)
            ->with('success', 'Payment of ₦'.number_format((float) $payment->amount, 2).' recorded.');
    }

    /**
     * Retrieves the authorized payment receipt details and renders the printable receipt page.
     */
    public function receipt(Payment $payment, BillingOperationsService $billing)
    {
        $payment = $billing->receiptDetails($payment);

        return view('billing.receipt', compact('payment'));
    }
}
