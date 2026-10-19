<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\Casts\Attribute;

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
        return $this->category === 'drug' ? 'Drug' : $this->description;
    }

    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }
}
