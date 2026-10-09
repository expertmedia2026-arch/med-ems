<?php

namespace App\Models;

use App\Enums\WorkStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Relations\MorphOne;

class Prescription extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'status'       => WorkStatus::class,
            'dispensed_at' => 'datetime',
        ];
    }

    public function visit(): BelongsTo
    {
        return $this->belongsTo(Visit::class);
    }

    public function drug(): BelongsTo
    {
        return $this->belongsTo(Drug::class);
    }

    public function prescriber(): BelongsTo
    {
        return $this->belongsTo(User::class, 'prescribed_by');
    }

    public function dispenser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'dispensed_by');
    }
    public function scopePending($query)
    {
        return $query->where('status', WorkStatus::Pending->value);
    }

    public function scopeCompleted($query)
    {
        return $query->where('status', WorkStatus::Completed->value);
    }

    public function invoiceItem(): MorphOne
    {
        return $this->morphOne(InvoiceItem::class, 'billable');
    }

    protected function isPaid(): Attribute
    {
        return Attribute::get(fn () => (bool) $this->invoiceItem?->is_paid);
    }
    
}