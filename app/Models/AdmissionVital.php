<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AdmissionVital extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'recorded_at' => 'datetime',
            'temperature' => 'decimal:1',
            'weight' => 'decimal:2',
            'height' => 'decimal:2',
        ];
    }

    protected function bloodPressure(): Attribute
    {
        return Attribute::get(fn () => $this->bp_systolic && $this->bp_diastolic
            ? "{$this->bp_systolic}/{$this->bp_diastolic}"
            : null);
    }

    protected function bmi(): Attribute
    {
        return Attribute::get(function () {
            if (! $this->weight || ! $this->height) {
                return null;
            }

            $heightInMetres = $this->height / 100;

            return round($this->weight / ($heightInMetres * $heightInMetres), 1);
        });
    }

    public function admission(): BelongsTo
    {
        return $this->belongsTo(Admission::class);
    }

    public function recorder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }
}
