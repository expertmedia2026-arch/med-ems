<?php

namespace App\Services;

use App\Models\HospitalSetting;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Patient;
use App\Models\Visit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class BillingService
{
    /** Create the visit's invoice with its selected consultation type, safely and only once. */
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

    /** Add any lab tests and drugs not yet on the invoice (safe to call repeatedly). */
    public function syncFor(Visit $visit): Invoice
    {
        return DB::transaction(function () use ($visit) {
            $invoice = $this->invoiceFor($visit);

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

    /** Drop the unpaid invoice line for a removed test/prescription. Returns false if it was already paid. */
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

    /** Authoritative check used by the triage gate. */
    public function consultationPaid(Visit $visit): bool
    {
        if ($visit->isLabOnly()) {
            return true;
        }

        $invoice = $this->invoiceFor($visit);
        $items = $invoice->items()->where('category', 'consultation')->get();

        return $items->isNotEmpty() && $items->every(fn ($item) => $item->is_paid);
    }

    /** Bill the one-time registration fee. Returns null when the fee is 0 (patient is marked registered). */
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
