<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Drug extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'unit_price' => 'decimal:2',
            'is_active'  => 'boolean',
        ];
    }

    protected function displayName(): Attribute
    {
        return Attribute::get(fn () => trim("{$this->name} {$this->strength} ({$this->form})"));
    }

    protected function isLowStock(): Attribute
    {
        return Attribute::get(fn () => $this->available_quantity <= $this->reorder_level);
    }

    protected function availableQuantity(): Attribute
    {
        return Attribute::get(fn () => max(0, (int) $this->stock_quantity - (int) $this->reserved_quantity));
    }

    public function scopeLowStock($query)
    {
        return $query->whereRaw('(stock_quantity - reserved_quantity) <= reorder_level');
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function reservations(): HasMany
    {
        return $this->hasMany(PrescriptionStockReservation::class);
    }

    public function movements(): HasMany
    {
        return $this->hasMany(DrugStockMovement::class);
    }

    public function emergencyStock(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(EmergencyPharmacyStock::class);
    }


}
