<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AdmissionDamaRecord extends Model
{
    protected $guarded = [];

    protected function casts(): array { return ['completed_at' => 'datetime']; }

    public function admission(): BelongsTo { return $this->belongsTo(Admission::class); }
    public function clinician(): BelongsTo { return $this->belongsTo(User::class, 'completed_by'); }
    public function witnesses(): HasMany { return $this->hasMany(AdmissionDamaWitness::class); }
}
