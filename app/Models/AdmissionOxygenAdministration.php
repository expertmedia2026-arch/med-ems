<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AdmissionOxygenAdministration extends Model
{
    protected $guarded = [];

    protected function casts(): array { return ['administered_at' => 'datetime']; }

    public function admission(): BelongsTo { return $this->belongsTo(Admission::class); }
    public function nurse(): BelongsTo { return $this->belongsTo(User::class, 'administered_by'); }
}
