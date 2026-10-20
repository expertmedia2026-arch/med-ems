<?php

namespace App\Services;

use App\Enums\VisitStatus;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\HospitalSetting;
use App\Models\Patient;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class PortalInvoiceService
{
    public function __construct(private readonly DrugInventoryService $inventory) {}

    /** @return array{invoices: mixed, outstanding: float} */
    public function indexData(Patient $patient): array
    {
        return [
            'invoices' => Invoice::where('patient_id', $patient->id)
                ->with('visit')
                ->withCount(['items as unpaid_items_count' => fn ($query) => $query->whereNull('payment_id')->where('total', '>', 0)])
                ->latest()
                ->paginate(10),
            'outstanding' => (float) Invoice::where('patient_id', $patient->id)
                ->whereIn('status', ['unpaid', 'partial'])
                ->sum(DB::raw('total - amount_paid')),
        ];
    }

    /** @return array{payable: Collection, waiting: Collection} */
    public function invoiceDetails(Invoice $invoice): array
    {
        $invoice->load(['visit', 'items.payment', 'payments']);
        $unpaid = $invoice->items->filter(fn (InvoiceItem $item) => ! $item->is_paid);
        $payable = $unpaid->filter(fn (InvoiceItem $item) => $this->itemIsPayable($invoice, $item))->values();

        return [
            'payable' => $payable,
            'waiting' => $unpaid->reject(fn (InvoiceItem $item) => $payable->contains('id', $item->id))->values(),
        ];
    }

    public function authorizePatientInvoice(?int $patientId, Invoice $invoice): void
    {
        abort_unless($patientId && $invoice->patient_id === $patientId, 404);
    }

    public function receiptForPatient(Payment $payment, ?int $patientId): Payment
    {
        $payment->load(['invoice.patient', 'invoice.visit.doctor', 'items', 'receiver']);
        abort_unless($patientId && $payment->invoice->patient_id === $patientId, 404);

        return $payment;
    }

    public function payableItems(Invoice $invoice, array $itemIds): Collection
    {
        $invoice->loadMissing('visit');

        return $invoice->items()
            ->whereIn('id', $itemIds)
            ->whereNull('payment_id')
            ->where('total', '>', 0)
            ->get()
            ->filter(fn (InvoiceItem $item) => $this->itemIsPayable($invoice, $item));
    }

    /** @return array{status: string, url: ?string} */
    public function startOnlinePayment(Invoice $invoice, array $itemIds, User $user, OnlinePaymentService $payments): array
    {
        if ((HospitalSetting::current()->payment_gateway ?: 'paystack') !== 'paystack') {
            return ['status' => 'gateway_disabled', 'url' => null];
        }

        $payments->refreshPending($invoice);
        $items = $this->payableItems($invoice, $itemIds);

        if ($items->count() !== count(array_unique($itemIds))) {
            return ['status' => 'invalid_items', 'url' => null];
        }

        if ($payments->hasPendingOverlap($invoice, $items)) {
            return ['status' => 'pending_overlap', 'url' => null];
        }

        $expiresAt = now()->addMinutes((int) config('hms.pharmacy.online_payment_reservation_minutes', 30));
        if (! $this->inventory->ensureItemsReserved($items, $expiresAt)) {
            return ['status' => 'stock_unavailable', 'url' => null];
        }

        try {
            return ['status' => 'started', 'url' => $payments->start($invoice, $items, $user)];
        } catch (\Throwable $exception) {
            report($exception);

            return ['status' => 'gateway_error', 'url' => null];
        }
    }

    private function itemIsPayable(Invoice $invoice, InvoiceItem $item): bool
    {
        return $item->category !== 'drug' || in_array($invoice->visit?->status, [VisitStatus::Completed, VisitStatus::Inpatient], true);
    }
}
