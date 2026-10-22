<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmergencyPharmacyStock extends Model
{
    protected $guarded = [];

    public function drug(): BelongsTo
    {
        return $this->belongsTo(Drug::class);
    }
}
