<?php

namespace App\Http\Controllers;

use App\Enums\VisitStatus;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\OnlinePayment;
use App\Models\Payment;
use App\Services\OnlinePaymentService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PortalInvoiceController extends Controller
{
    public function index(Request $request)
    {
        $patient = $this->patient($request);

        $invoices = Invoice::where('patient_id', $patient->id)
            ->with('visit')
            ->withCount(['items as unpaid_items_count' => fn ($q) => $q->whereNull('payment_id')->where('total', '>', 0)])
            ->latest()
            ->paginate(10);

        $outstanding = (float) Invoice::where('patient_id', $patient->id)
            ->whereIn('status', ['unpaid', 'partial'])
            ->sum(DB::raw('total - amount_paid'));

        return view('portal.invoices.index', compact('invoices', 'outstanding'));
    }

    public function show(Request $request, Invoice $invoice)
    {
        $this->authorizeInvoice($request, $invoice);

        $invoice->load(['visit', 'items.payment', 'payments']);

        $unpaid  = $invoice->items->filter(fn ($item) => ! $item->is_paid);
        $payable = $unpaid->filter(fn ($item) => $this->payable($invoice, $item))->values();
        $waiting = $unpaid->reject(fn ($item) => $payable->contains('id', $item->id))->values();

        return view('portal.invoices.show', compact('invoice', 'payable', 'waiting'));
    }

    /** Start an online payment for the ticked items and send the patient to the gateway. */
    public function pay(Request $request, Invoice $invoice, OnlinePaymentService $payments)
    {
        $this->authorizeInvoice($request, $invoice);

        $data = $request->validate([
            'item_ids'   => ['required', 'array', 'min:1'],
            'item_ids.*' => ['integer'],
        ], ['item_ids.required' => 'Select at least one item to pay for.']);

        // The patient may have paid earlier and closed the page: settle that first
        $payments->refreshPending($invoice);

        $invoice->load('visit');

        $items = $invoice->items()
            ->whereIn('id', $data['item_ids'])
            ->whereNull('payment_id')
            ->where('total', '>', 0)
            ->get()
            ->filter(fn ($item) => $this->payable($invoice, $item));

        if ($items->count() !== count(array_unique($data['item_ids']))) {
            return back()->with('error', 'Some selected items cannot be paid online (already paid, or not ready yet). Please try again.');
        }

        if ($payments->hasPendingOverlap($invoice, $items)) {
            return back()->with('error', 'A payment for these items is already in progress. Complete it, or try again in a few minutes.');
        }

        try {
            $url = $payments->start($invoice, $items, $request->user());
        } catch (\Throwable $e) {
            report($e);

            return back()->with('error', 'We could not start the payment. Please try again in a moment.');
        }

        return redirect()->away($url);
    }

    /** Where the gateway sends the patient back to. The result is verified with the gateway, never trusted from the URL. */
    public function callback(Request $request, OnlinePaymentService $payments)
    {
        $reference = (string) ($request->query('reference') ?? $request->query('trxref'));

        $attempt = OnlinePayment::where('reference', $reference)
            ->where('patient_id', $request->user()->patient_id)
            ->first();

        abort_unless($attempt, 404);

        try {
            $attempt = $payments->confirm($reference) ?? $attempt;
        } catch (\Throwable $e) {
            report($e);
        }

        $back = redirect()->route('portal.invoices.show', $attempt->invoice_id);

        return match ($attempt->status) {
            'success' => $back->with('success', 'Payment received. Thank you! Your receipt is listed under Payments.'),
            'review'  => $back->with('error', 'We received your payment but need to check it against your bill. The hospital will contact you.'),
            'failed'  => $back->with('error', 'The payment was not completed. You can try again.'),
            default   => $back->with('error', 'We are still waiting for the bank to confirm your payment. Refresh this page in a minute. If money left your account, it will show here.'),
        };
    }

    public function receipt(Request $request, Payment $payment)
    {
        $payment->load(['invoice.patient', 'invoice.visit.doctor', 'items', 'receiver']);

        abort_unless($payment->invoice->patient_id === $request->user()->patient_id, 404);

        return view('billing.receipt', compact('payment'));
    }

    private function patient(Request $request)
    {
        $patient = $request->user()->patient;
        abort_unless($patient, 403);

        return $patient;
    }

    private function authorizeInvoice(Request $request, Invoice $invoice): void
    {
        abort_unless($request->user()->patient_id && $invoice->patient_id === $request->user()->patient_id, 404);
    }

    /** Drugs become payable once the doctor has finished the consultation (the prescription is final). */
    private function payable(Invoice $invoice, InvoiceItem $item): bool
    {
        if ($item->category === 'drug') {
            return $invoice->visit?->status === VisitStatus::Completed;
        }

        return true;
    }
}