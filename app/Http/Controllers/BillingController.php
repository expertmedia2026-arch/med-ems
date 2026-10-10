<?php

namespace App\Http\Controllers;

use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Payment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class BillingController extends Controller
{
    public function index(Request $request)
    {
        $status = $request->query('status');

        $invoices = Invoice::with('patient')
            ->withCount(['items as unpaid_items_count' => fn ($q) => $q->whereNull('payment_id')->where('total', '>', 0)])
            ->when($request->filled('q'), fn ($q) => $q->whereHas('patient', fn ($p) => $p->search($request->query('q'))))
            ->when(in_array($status, ['unpaid', 'partial', 'paid'], true), fn ($q) => $q->where('status', $status))
            ->latest()
            ->paginate(15)
            ->withQueryString();

        return view('billing.index', [
            'invoices'       => $invoices,
            'status'         => $status,
            'collectedToday' => Payment::whereDate('created_at', today())->sum('amount'),
            'outstanding'    => Invoice::whereIn('status', ['unpaid', 'partial'])->sum(DB::raw('total - amount_paid')),
        ]);
    }

    public function show(Invoice $invoice)
    {
        $invoice->load(['patient', 'visit.doctor', 'items.payment', 'payments.receiver']);

        return view('billing.show', compact('invoice'));
    }

    public function pay(Request $request, Invoice $invoice)
    {
        $data = $request->validate([
            'item_ids'   => ['required', 'array', 'min:1'],
            'item_ids.*' => ['integer'],
            'method'     => ['required', 'in:cash,transfer,pos'],
            'reference'  => ['nullable', 'string', 'max:100'],
        ], ['item_ids.required' => 'Select at least one item to pay for.']);

        $payment = null;
        $wanted  = array_values(array_unique($data['item_ids']));

        DB::transaction(function () use ($invoice, $data, $wanted, $request, &$payment) {
            // Lock the invoice so two cashiers can't take payment for the same item
            Invoice::lockForUpdate()->findOrFail($invoice->id);

            $items = $invoice->items()
                ->whereIn('id', $wanted)
                ->whereNull('payment_id')
                ->where('total', '>', 0)
                ->lockForUpdate()
                ->get();

            if ($items->count() !== count($wanted)) {
                return; // an item was already paid, or does not belong to this invoice
            }

            $payment = $invoice->payments()->create([
                'amount'      => round((float) $items->sum('total'), 2),
                'method'      => $data['method'],
                'reference'   => $data['reference'] ?? null,
                'received_by' => $request->user()->id,
            ]);

            InvoiceItem::whereIn('id', $items->pluck('id'))->update(['payment_id' => $payment->id]);

            // Paying the registration fee activates the patient's portal login
            if ($items->contains('category', 'registration')) {
                $invoice->patient()->whereNull('registration_paid_at')->update(['registration_paid_at' => now()]);
            }

        });

        if (! $payment) {
            return redirect()->route('billing.show', $invoice)
                ->with('error', 'Some selected items were already paid or are no longer valid. The page has been refreshed.');
        }

        return redirect()
            ->route('billing.receipt', $payment)
            ->with('success', 'Payment of ₦' . number_format((float) $payment->amount, 2) . ' recorded.');
    }

    public function receipt(Payment $payment)
    {
        $payment->load(['invoice.patient', 'invoice.visit.doctor', 'items', 'receiver']);

        return view('billing.receipt', compact('payment'));
    }
}