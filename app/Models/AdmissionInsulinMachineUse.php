<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AdmissionInsulinMachineUse extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['opened_at' => 'datetime'];
    }

    public function admission(): BelongsTo { return $this->belongsTo(Admission::class); }
    public function block(): BelongsTo { return $this->belongsTo(AdmissionInsulinMachineBlock::class, 'block_id'); }
    public function prescription(): BelongsTo { return $this->belongsTo(Prescription::class); }
    public function opener(): BelongsTo { return $this->belongsTo(User::class, 'opened_by'); }
}
