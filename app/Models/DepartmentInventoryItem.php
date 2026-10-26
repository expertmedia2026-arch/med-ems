<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DepartmentInventoryItem extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'quantity_on_hand' => 'decimal:3',
            'reorder_level' => 'decimal:3',
            'is_active' => 'boolean',
        ];
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function movements(): HasMany
    {
        return $this->hasMany(DepartmentInventoryMovement::class);
    }
}
