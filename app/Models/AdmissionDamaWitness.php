<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AdmissionDamaWitness extends Model
{
    protected $guarded = [];

    public function record(): BelongsTo { return $this->belongsTo(AdmissionDamaRecord::class, 'dama_record_id'); }
}
