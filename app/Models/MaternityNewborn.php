<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Stores one birth outcome within a maternity episode.
 *
 * Live births may link to a newly registered Patient; non-live outcomes remain
 * clinical records without creating a patient identity or hospital number.
 */
class MaternityNewborn extends Model
{
    protected $guarded = [];

    /** Present birth dates as date objects while leaving the clinical record time zone independent. */
    protected function casts(): array
    {
        return ['date_of_birth' => 'date'];
    }

    /** The labor and delivery episode in which this baby was recorded. */
    public function episode(): BelongsTo
    {
        return $this->belongsTo(MaternityEpisode::class, 'maternity_episode_id');
    }

    /** A live-born baby's linked patient record; non-live outcomes may have no patient. */
    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }
}
