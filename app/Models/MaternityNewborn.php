<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MaternityNewborn extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['date_of_birth' => 'date'];
    }

    public function episode(): BelongsTo
    {
        return $this->belongsTo(MaternityEpisode::class, 'maternity_episode_id');
    }

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }
}
