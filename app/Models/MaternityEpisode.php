<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Owns the clinical labor-to-postpartum record for one inpatient admission.
 *
 * Newborns and maternity invoice items are created through MaternityService so
 * delivery, patient registration, and billing remain one atomic operation.
 */
class MaternityEpisode extends Model
{
    protected $guarded = [];

    /** Cast timestamps and optional historical supply answers for safe rendering and comparisons. */
    protected function casts(): array
    {
        return [
            'labor_started_at' => 'datetime',
            'delivery_at' => 'datetime',
            'patient_brought_delivery_materials' => 'boolean',
            'hospital_supplied_delivery_pack' => 'boolean',
        ];
    }

    /** The inpatient admission that owns this labor and delivery episode. */
    public function admission(): BelongsTo
    {
        return $this->belongsTo(Admission::class);
    }

    /** The staff member who opened the labor episode. */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** Babies are returned in clinical birth order rather than database order. */
    public function newborns(): HasMany
    {
        return $this->hasMany(MaternityNewborn::class)->orderBy('birth_order');
    }
}
