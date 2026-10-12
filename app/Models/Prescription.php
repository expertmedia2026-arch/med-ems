<?php

namespace App\Models;

use App\Enums\WorkStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
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
            'dispensable_quantity' => 'integer',
            'requires_in_facility_administration' => 'boolean',
            'required_administration_count' => 'integer',
            'administration_waived_at' => 'datetime',
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

    public function stockReservation(): HasOne
    {
        return $this->hasOne(PrescriptionStockReservation::class);
    }

    public function administrations(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(MedicationAdministration::class);
    }

    public function administrationWaiver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'administration_waived_by');
    }

    public function getAdministeredCountAttribute(): int
    {
        return $this->relationLoaded('administrations')
            ? $this->administrations->where('status', 'administered')->count()
            : $this->administrations()->where('status', 'administered')->count();
    }

    protected function isPaid(): Attribute
    {
        return Attribute::get(fn () => (bool) $this->invoiceItem?->is_paid);
    }
    
}
