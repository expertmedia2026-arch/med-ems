<?php

namespace App\Http\Controllers;

use App\Http\Requests\BillingIndexRequest;
use App\Http\Requests\PayInvoiceRequest;
use App\Models\Invoice;
use App\Models\Payment;
use App\Services\CashierService;

class BillingController extends Controller
{
    public function index(BillingIndexRequest $request, CashierService $cashier)
    {
        $filters = $request->validated();

        return view('billing.index', $cashier->indexData($filters['status'] ?? null, $filters['q'] ?? null));
    }

    public function show(Invoice $invoice, CashierService $cashier)
    {
        $invoice = $cashier->invoiceDetails($invoice);

        return view('billing.show', compact('invoice'));
    }

    public function pay(PayInvoiceRequest $request, Invoice $invoice, CashierService $cashier)
    {
        $payment = $cashier->recordPayment($invoice, $request->validated(), $request->user()->id);

        if (! $payment) {
            return redirect()->route('billing.show', $invoice)
                ->with('error', 'Some selected items were already paid or are no longer valid. The page has been refreshed.');
        }

        return redirect()
            ->route('billing.receipt', $payment)
            ->with('success', 'Payment of ₦'.number_format((float) $payment->amount, 2).' recorded.');
    }

    public function receipt(Payment $payment, CashierService $cashier)
    {
        $payment = $cashier->receiptDetails($payment);

        return view('billing.receipt', compact('payment'));
    }
}
