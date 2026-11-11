<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DrugStockMovement extends Model
{
    protected $guarded = [];

    public function drug(): BelongsTo
    {
        return $this->belongsTo(Drug::class);
    }

    public function prescription(): BelongsTo
    {
        return $this->belongsTo(Prescription::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function pharmacySaleItem(): BelongsTo
    {
        return $this->belongsTo(PharmacySaleItem::class);
    }
}
