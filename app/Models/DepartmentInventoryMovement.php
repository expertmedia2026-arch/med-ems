<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DepartmentInventoryMovement extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:3',
            'balance_after' => 'decimal:3',
        ];
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(DepartmentInventoryItem::class, 'department_inventory_item_id');
    }

    public function recorder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }
}
