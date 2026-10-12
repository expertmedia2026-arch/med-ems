<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MedicationAdministration extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['administered_at' => 'datetime'];
    }

    public function prescription(): BelongsTo
    {
        return $this->belongsTo(Prescription::class);
    }

    public function visit(): BelongsTo
    {
        return $this->belongsTo(Visit::class);
    }

    public function nurse(): BelongsTo
    {
        return $this->belongsTo(User::class, 'administered_by');
    }
}
