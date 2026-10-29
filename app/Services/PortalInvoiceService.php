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

/**
 * Provides invoice summaries, payable-item filtering, ownership checks, and online checkout.
 *
 * Patient ownership can be asserted for invoice access and is checked when returning
 * receipts. Online checkout refreshes pending attempts and reserves required drug stock.
 */
class PortalInvoiceService
{
    /** @param DrugInventoryService $inventory Reserves or releases stock for invoice drug items. */
    public function __construct(private readonly DrugInventoryService $inventory) {}

    /**
     * Paginate the patient's invoices and calculate their unpaid balance.
     *
     * @param  Patient  $patient  Patient whose invoices are listed.
     * @return array{invoices: mixed, outstanding: float} Invoice paginator and total unpaid amount.
     */
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

    /**
     * Split an invoice's unpaid items into currently payable and waiting groups.
     *
     * Drug items remain unavailable for payment until the related visit is completed
     * or inpatient; other unpaid items are immediately payable.
     *
     * @param  Invoice  $invoice  Invoice whose items are inspected.
     * @return array{payable: Collection<int, InvoiceItem>, waiting: Collection<int, InvoiceItem>} Unpaid item groups.
     */
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

    /**
     * Ensure the invoice belongs to the patient authenticated in the portal.
     *
     * Mismatches and missing patient IDs are intentionally reported as not found.
     *
     * @param  int|null  $patientId  Authenticated portal patient's ID.
     * @param  Invoice  $invoice  Invoice being accessed.
     */
    public function authorizePatientInvoice(?int $patientId, Invoice $invoice): void
    {
        abort_unless($patientId && $invoice->patient_id === $patientId, 404);
    }

    /**
     * Load and authorize a payment receipt for its patient.
     *
     * @param  Payment  $payment  Payment receipt requested.
     * @param  int|null  $patientId  Authenticated portal patient's ID.
     * @return Payment Payment with invoice, visit, patient, items, and receiver loaded.
     */
    public function receiptForPatient(Payment $payment, ?int $patientId): Payment
    {
        $payment->load(['invoice.patient', 'invoice.visit.doctor', 'items', 'receiver']);
        abort_unless($patientId && $payment->invoice->patient_id === $patientId, 404);

        return $payment;
    }

    /**
     * Fetch selected unpaid, positive-value invoice items that are currently payable.
     *
     * Items belonging to another invoice, already settled, or blocked by the visit's
     * status are excluded from the returned collection.
     *
     * @param  Invoice  $invoice  Invoice from which item IDs may be selected.
     * @param  array<int, int>  $itemIds  Requested invoice-item IDs.
     * @return Collection<int, InvoiceItem> Matching payable items.
     */
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

    /**
     * Validate and start hosted checkout for selected invoice items.
     *
     * Returns status codes for disabled gateways, invalid selections, overlapping
     * pending attempts, unavailable stock, gateway errors, or a successful checkout.
     * Errors from checkout initialization are reported and returned as a status rather
     * than propagated.
     *
     * @param  Invoice  $invoice  Invoice to pay.
     * @param  array<int, int>  $itemIds  Exact set of requested item IDs.
     * @param  User  $user  Portal user whose email is passed to the gateway.
     * @param  OnlinePaymentService  $payments  Payment-attempt coordinator.
     * @return array{status: string, url: ?string} Checkout outcome and hosted payment URL when started.
     */
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

    /**
     * Determine whether a specific invoice item is eligible for patient payment.
     *
     * Drug charges are payable only after the visit is completed or inpatient.
     *
     * @param  Invoice  $invoice  Invoice containing the item.
     * @param  InvoiceItem  $item  Item whose payment eligibility is checked.
     * @return bool Whether the item can be paid online now.
     */
    private function itemIsPayable(Invoice $invoice, InvoiceItem $item): bool
    {
        return $item->category !== 'drug' || in_array($invoice->visit?->status, [VisitStatus::Completed, VisitStatus::Inpatient], true);
    }
}
