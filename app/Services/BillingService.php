<?php

namespace App\Services;

use App\Models\HospitalSetting;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Patient;
use App\Models\Visit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * Creates and synchronizes invoices with consultation, registration, laboratory, and drug charges.
 */
class BillingService
{
    /**
     * Return the visit invoice, creating it and its consultation line when needed.
     *
     * Lab-only visits are not charged a consultation fee. Repeated calls reuse the same invoice
     * and do not duplicate the consultation item.
     */
    public function invoiceFor(Visit $visit): Invoice
    {
        $invoice = Invoice::firstOrCreate(
            ['visit_id' => $visit->id],
            ['patient_id' => $visit->patient_id]
        );

        // Referral (lab-only) patients pay for their tests only
        if (! $visit->isLabOnly()
            && $visit->consultationType
            && ! $invoice->items()->where('category', 'consultation')->exists()) {
            $invoice->items()->create([
                'category' => 'consultation',
                'description' => $visit->consultationType->name,
                'quantity' => 1,
                'unit_price' => $visit->consultationType->price,
            ]);
        }

        return $invoice;
    }

    /**
     * Add the configured emergency attendance fee once for an emergency visit.
     *
     * Returns null for non-emergency visits or when the configured fee is zero; otherwise returns
     * the refreshed invoice after creating the missing A&E charge.
     */
    public function addEmergencyAttendanceFee(Visit $visit): ?Invoice
    {
        if (! $visit->is_emergency) {
            return null;
        }

        $fee = (float) HospitalSetting::current()->emergency_fee;
        if ($fee <= 0) {
            return null;
        }

        $invoice = $this->invoiceFor($visit);
        if (! $invoice->items()->where('category', 'emergency')->exists()) {
            $invoice->items()->create([
                'category' => 'emergency',
                'description' => 'A&E attendance fee',
                'quantity' => 1,
                'unit_price' => $fee,
            ]);
        }

        return $invoice->fresh();
    }

    /**
     * Synchronize registration, laboratory, and prescription charges to the visit invoice.
     *
     * Creation of the invoice lines is transactional and idempotent by billable record. When
     * requested, a configured registration fee is included; a zero registration fee marks the
     * patient as registered without adding an invoice line.
     */
    public function syncFor(Visit $visit, bool $includeRegistrationFee = false): Invoice
    {
        return DB::transaction(function () use ($visit, $includeRegistrationFee) {
            $invoice = $this->invoiceFor($visit);

            if ($includeRegistrationFee && ! $invoice->items()->where('category', 'registration')->exists()) {
                $fee = (float) HospitalSetting::current()->registration_fee;
                if ($fee > 0) {
                    $invoice->items()->create([
                        'category' => 'registration',
                        'description' => 'Patient registration fee',
                        'quantity' => 1,
                        'unit_price' => $fee,
                    ]);
                } else {
                    $visit->patient()->whereNull('registration_paid_at')->update(['registration_paid_at' => now()]);
                }
            }

            $billed = $invoice->items()->whereNotNull('billable_id')->get(['billable_type', 'billable_id'])
                ->map(fn ($item) => "{$item->billable_type}:{$item->billable_id}")
                ->all();

            foreach ($visit->labRequests()->with('test')->get() as $req) {
                if (in_array("{$req->getMorphClass()}:{$req->id}", $billed, true)) {
                    continue;
                }

                $invoice->items()->create([
                    'category' => 'lab',
                    'description' => $req->test->name,
                    'quantity' => 1,
                    'unit_price' => $req->test->price,
                    'billable_type' => $req->getMorphClass(),
                    'billable_id' => $req->id,
                ]);
            }

            foreach ($visit->prescriptions()->with('drug')->get() as $rx) {
                if (in_array("{$rx->getMorphClass()}:{$rx->id}", $billed, true)) {
                    continue;
                }

                $invoice->items()->create([
                    'category' => 'drug',
                    'description' => $rx->drug->display_name,
                    'quantity' => $rx->dispensable_quantity ?? $rx->quantity,
                    'unit_price' => $rx->drug->unit_price,
                    'billable_type' => $rx->getMorphClass(),
                    'billable_id' => $rx->id,
                ]);
            }

            return $invoice->fresh();
        });
    }

    /**
     * Remove the invoice item associated with a deleted billable record when it remains unpaid.
     *
     * Returns true when no line exists or deletion succeeds, and false when the item has already
     * been paid and therefore must remain on the financial record.
     */
    public function release(Model $billable): bool
    {
        $item = InvoiceItem::where('billable_type', $billable->getMorphClass())
            ->where('billable_id', $billable->getKey())
            ->first();

        if (! $item) {
            return true;
        }

        if ($item->payment_id) {
            return false;
        }

        $item->delete();

        return true;
    }

    /**
     * Determine whether a standard consultation's consultation fee has been fully paid.
     *
     * Lab-only and emergency visits bypass this fee gate. For other visits, ensures the invoice
     * exists and requires at least one consultation line with every such line paid.
     */
    public function consultationPaid(Visit $visit): bool
    {
        if ($visit->isLabOnly() || $visit->is_emergency) {
            return true;
        }

        $invoice = $this->invoiceFor($visit);
        $items = $invoice->items()->where('category', 'consultation')->get();

        return $items->isNotEmpty() && $items->every(fn ($item) => $item->is_paid);
    }

    /**
     * Find or create the patient's one-time, visit-independent registration invoice.
     *
     * Returns an existing registration invoice when present. If the configured fee is zero, marks
     * the patient registered and returns null; otherwise creates and returns a fresh invoice.
     */
    public function registrationInvoiceFor(Patient $patient): ?Invoice
    {
        $existing = Invoice::where('patient_id', $patient->id)
            ->whereNull('visit_id')
            ->whereHas('items', fn ($q) => $q->where('category', 'registration'))
            ->first();

        if ($existing) {
            return $existing;
        }

        $fee = (float) HospitalSetting::current()->registration_fee;

        if ($fee <= 0) {
            $patient->update(['registration_paid_at' => $patient->registration_paid_at ?? now()]);

            return null;
        }

        $invoice = Invoice::create(['patient_id' => $patient->id]);

        $invoice->items()->create([
            'category' => 'registration',
            'description' => 'Registration fee',
            'quantity' => 1,
            'unit_price' => $fee,
        ]);

        return $invoice->fresh();
    }
}
