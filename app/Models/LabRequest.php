<?php

namespace App\Models;

use App\Enums\WorkStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Relations\MorphOne;

class LabRequest extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'status'       => WorkStatus::class,
            'completed_at' => 'datetime',
        ];
    }

    public function visit(): BelongsTo
    {
        return $this->belongsTo(Visit::class);
    }

    public function test(): BelongsTo
    {
        return $this->belongsTo(LabTest::class, 'lab_test_id');
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function processor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'processed_by');
    }
    public function scopePending($query)
    {
        return $query->where('status', WorkStatus::Pending->value);
    }

    public function scopeCompleted($query)
    {
        return $query->where('status', WorkStatus::Completed->value);
    }
    public function attachments(): HasMany
    {
        return $this->hasMany(LabAttachment::class);
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