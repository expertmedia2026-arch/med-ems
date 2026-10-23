<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AdmissionFluidEntry extends Model
{
    protected $guarded = [];

    protected function casts(): array { return ['recorded_at' => 'datetime']; }

    public function admission(): BelongsTo { return $this->belongsTo(Admission::class); }
    public function recorder(): BelongsTo { return $this->belongsTo(User::class, 'recorded_by'); }
}
