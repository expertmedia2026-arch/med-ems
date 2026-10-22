<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MortuaryCase extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'deceased_at' => 'datetime',
            'received_at' => 'datetime',
            'released_at' => 'datetime',
            'storage_billed_through' => 'datetime',
            'intake_fee' => 'decimal:2',
            'daily_storage_fee' => 'decimal:2',
        ];
    }

    public function visit(): BelongsTo
    {
        return $this->belongsTo(Visit::class);
    }

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function openedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'opened_by');
    }

    public function receivedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'received_by');
    }

    public function releasedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'released_by');
    }

    public function events(): HasMany
    {
        return $this->hasMany(MortuaryCaseEvent::class)->latest('occurred_at');
    }
}
