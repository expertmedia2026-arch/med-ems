<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Support\Facades\DB;

class InvoiceItem extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'unit_price' => 'decimal:2',
            'total'      => 'decimal:2',
        ];
    }

    protected static function booted(): void
    {
        static::created(function (InvoiceItem $item) {
            // Registration has a dedicated patient-registration notification.
            if ($item->category === 'registration' || (float) $item->total <= 0) {
                return;
            }

            DB::afterCommit(function () use ($item) {
                $item->loadMissing('invoice.patient');
                $invoice = $item->invoice;
                $patient = $invoice?->patient;

                if (! $invoice || ! $patient) {
                    return;
                }

                app(\App\Services\WorkflowNotificationService::class)->billing(
                    (int) $invoice->hospital_id,
                    'New charge awaiting payment',
                    "A {$item->descriptionForBilling()} charge for {$patient->full_name} ({$patient->hospital_number}) was added to Billing.",
                );
            });
        });

        static::saving(function (InvoiceItem $item) {
            $item->total = $item->quantity * $item->unit_price;
        });

        static::saved(fn (InvoiceItem $item) => $item->invoice->recalculate());
        static::deleted(fn (InvoiceItem $item) => $item->invoice->recalculate());
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function billable(): MorphTo
    {
        return $this->morphTo();
    }

    /** Paid when linked to a payment (items with no charge count as paid). */
    protected function isPaid(): Attribute
    {
        return Attribute::get(fn () => $this->payment_id !== null || (float) $this->total <= 0);
    }

    public function descriptionForPatient(): string
    {
        return in_array($this->category, ['drug', 'emergency_item'], true) ? ($this->category === 'drug' ? 'Drug' : 'A&E item') : $this->description;
    }

    /** Keep the dispensed medicine name out of cashier-facing invoice details. */
    public function descriptionForBilling(): string
    {
        return in_array($this->category, ['drug', 'emergency_item'], true) ? ($this->category === 'drug' ? 'Drug' : 'A&E item') : $this->description;
    }

    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }
}
