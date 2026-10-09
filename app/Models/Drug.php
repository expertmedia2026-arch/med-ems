<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;

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
        return Attribute::get(fn () => $this->stock_quantity <= $this->reorder_level);
    }

    public function scopeLowStock($query)
    {
        return $query->whereColumn('stock_quantity', '<=', 'reorder_level');
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }


}