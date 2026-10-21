<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MortuaryCaseEvent extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'occurred_at' => 'datetime',
            'details' => 'array',
        ];
    }

    public function mortuaryCase(): BelongsTo
    {
        return $this->belongsTo(MortuaryCase::class);
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'performed_by');
    }
}
