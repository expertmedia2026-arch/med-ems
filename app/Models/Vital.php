<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Vital extends Model
{
    protected $guarded = [];

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

            $m = $this->height / 100;

            return round($this->weight / ($m * $m), 1);
        });
    }

    public function visit(): BelongsTo
    {
        return $this->belongsTo(Visit::class);
    }

    public function recorder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }

    public function alerts(): array
    {
        $alerts = [];

        if ($this->temperature !== null && $this->temperature >= 38) $alerts[] = 'Fever';
        if ($this->pulse && $this->pulse > 100)                      $alerts[] = 'High pulse';
        if ($this->pulse && $this->pulse < 50)                       $alerts[] = 'Low pulse';
        if ($this->bp_systolic && ($this->bp_systolic >= 140 || $this->bp_diastolic >= 90)) $alerts[] = 'High BP';
        if ($this->spo2 && $this->spo2 < 95)                         $alerts[] = 'Low SpO₂';

        return $alerts;
    }
}