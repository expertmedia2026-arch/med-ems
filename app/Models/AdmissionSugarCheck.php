<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AdmissionSugarCheck extends Model
{
    protected $guarded = [];

    protected function casts(): array { return ['checked_at' => 'datetime', 'result' => 'decimal:2']; }

    public function admission(): BelongsTo { return $this->belongsTo(Admission::class); }
    public function checker(): BelongsTo { return $this->belongsTo(User::class, 'checked_by'); }
}
