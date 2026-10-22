<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmergencyPharmacyMovement extends Model
{
    protected $guarded = [];

    public function drug(): BelongsTo
    {
        return $this->belongsTo(Drug::class);
    }

    public function visit(): BelongsTo
    {
        return $this->belongsTo(Visit::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function request(): BelongsTo
    {
        return $this->belongsTo(EmergencyPharmacyRequest::class, 'request_id');
    }
}
