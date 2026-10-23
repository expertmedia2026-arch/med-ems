<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphOne;

class AdmissionInsulinMachineBlock extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['fee' => 'decimal:2', 'started_at' => 'datetime'];
    }

    public function admission(): BelongsTo { return $this->belongsTo(Admission::class); }
    public function starter(): BelongsTo { return $this->belongsTo(User::class, 'started_by'); }
    public function uses(): HasMany { return $this->hasMany(AdmissionInsulinMachineUse::class, 'block_id')->oldest('opened_at'); }
    public function invoiceItem(): MorphOne { return $this->morphOne(InvoiceItem::class, 'billable'); }

    protected function paymentComplete(): Attribute
    {
        return Attribute::get(function () {
            $invoiceItem = $this->invoiceItem;

            return $invoiceItem ? (bool) $invoiceItem->is_paid : (float) $this->fee <= 0;
        });
    }
}
