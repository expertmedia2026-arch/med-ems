<?php

namespace App\Services;

use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\OnlinePayment;
use App\Models\Payment;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\DB;

class CashierService
{
    public function __construct(private readonly DrugInventoryService $inventory) {}

    public function indexData(?string $status, ?string $term): array
    {
        return [
            'invoices' => Invoice::with('patient')
                ->withCount(['items as unpaid_items_count' => fn ($query) => $query->whereNull('payment_id')->where('total', '>', 0)])
                ->when($term, fn ($query) => $query->whereHas('patient', fn ($patient) => $patient->search($term)))
                ->when(in_array($status, ['unpaid', 'partial', 'paid'], true), fn ($query) => $query->where('status', $status))
                ->latest()->paginate(15)->withQueryString(),
            'status' => $status,
            'collectedToday' => Payment::whereDate('created_at', today())->sum('amount'),
            'outstanding' => Invoice::whereIn('status', ['unpaid', 'partial'])->sum(DB::raw('total - amount_paid')),
            'review' => OnlinePayment::where('status', 'review')->with('patient')->latest()->get(),
        ];
    }

    public function invoiceDetails(Invoice $invoice): Invoice
    {
        return $invoice->load(['patient', 'visit.doctor', 'items.payment', 'payments.receiver']);
    }

    public function receiptDetails(Payment $payment): Payment
    {
        return $payment->load(['invoice.patient', 'invoice.visit.doctor', 'items', 'receiver']);
    }

    public function recordPayment(Invoice $invoice, array $data, int $cashierId): ?Payment
    {
        $payment = null;
        $wanted = array_values(array_unique($data['item_ids']));

        DB::transaction(function () use ($invoice, $data, $wanted, $cashierId, &$payment) {
            Invoice::lockForUpdate()->findOrFail($invoice->id);

            $items = $invoice->items()->whereIn('id', $wanted)->whereNull('payment_id')
                ->where('total', '>', 0)->lockForUpdate()->get();

            if ($items->count() !== count($wanted)) {
                return;
            }

            if (! $this->inventory->ensureItemsReserved($items, null)) {
                throw ValidationException::withMessages([
                    'item_ids' => 'There is not enough available stock for one or more drug items. Remove those items or contact the pharmacy before collecting payment.',
                ]);
            }

            $payment = $invoice->payments()->create([
                'amount' => round((float) $items->sum('total'), 2),
                'method' => $data['method'],
                'reference' => $data['reference'] ?? null,
                'received_by' => $cashierId,
            ]);

            InvoiceItem::whereIn('id', $items->pluck('id'))->update(['payment_id' => $payment->id]);

            if ($items->contains('category', 'registration')) {
                $invoice->patient()->whereNull('registration_paid_at')->update(['registration_paid_at' => now()]);
            }
        });

        return $payment;
    }
}
