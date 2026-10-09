<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Payment extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['amount' => 'decimal:2'];
    }

    protected static function booted(): void
    {
        // Sequential, unique receipt number, e.g. RCP-261009-00012
        static::created(function (Payment $payment) {
            $payment->forceFill([
                'receipt_number' => 'RCP-' . $payment->created_at->format('ymd') . '-' . str_pad($payment->id, 5, '0', STR_PAD_LEFT),
            ])->saveQuietly();
        });

        static::saved(fn (Payment $payment) => $payment->invoice->recalculate());
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function receiver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'received_by');
    }

    /** The invoice items this payment settled. */
    public function items(): HasMany
    {
        return $this->hasMany(InvoiceItem::class);
    }
}