<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MaternityEpisode extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'labor_started_at' => 'datetime',
            'delivery_at' => 'datetime',
            'patient_brought_delivery_materials' => 'boolean',
            'hospital_supplied_delivery_pack' => 'boolean',
        ];
    }

    public function admission(): BelongsTo
    {
        return $this->belongsTo(Admission::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function newborns(): HasMany
    {
        return $this->hasMany(MaternityNewborn::class)->orderBy('birth_order');
    }
}
