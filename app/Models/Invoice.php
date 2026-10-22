<?php

namespace App\Models;

use App\Enums\InvoiceStatus;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Str;

class Invoice extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'status'      => InvoiceStatus::class,
            'total'       => 'decimal:2',
            'amount_paid' => 'decimal:2',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Invoice $invoice) {
            $invoice->invoice_number ??= 'INV-' . now()->format('ymd') . '-' . strtoupper(Str::random(4));
        });
    }

    protected function balance(): Attribute
    {
        return Attribute::get(fn () => (float) $this->total - (float) $this->amount_paid);
    }

    /** Re-sum items and payments, then set the status. Called automatically by InvoiceItem and Payment. */
    public function recalculate(): void
    {
        $total = (float) $this->items()->sum('total');
        $paid  = (float) $this->payments()->sum('amount');

        $this->update([
            'total'       => $total,
            'amount_paid' => $paid,
            'status' => match (true) {
                $total <= 0, $paid >= $total => InvoiceStatus::Paid,
                $paid <= 0   => InvoiceStatus::Unpaid,
                default  => InvoiceStatus::Partial,
            },
        ]);
    }

    public function visit(): BelongsTo
    {
        return $this->belongsTo(Visit::class);
    }

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(InvoiceItem::class);
    }

    public function mortuaryCase(): HasOne
    {
        return $this->hasOne(MortuaryCase::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }
}
