<?php

namespace App\Models;

use App\Enums\AdmissionStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Admission extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'status' => AdmissionStatus::class,
            'admitted_at' => 'datetime',
            'expected_discharge_date' => 'date',
            'discharged_at' => 'datetime',
        ];
    }

    public function visit(): BelongsTo
    {
        return $this->belongsTo(Visit::class);
    }

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function attendingDoctor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'attending_doctor_id');
    }

    public function admittedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'admitted_by');
    }

    public function dischargedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'discharged_by');
    }

    public function notes(): HasMany
    {
        return $this->hasMany(AdmissionNote::class)->latest();
    }

    public function vitals(): HasMany
    {
        return $this->hasMany(AdmissionVital::class)->latest('recorded_at');
    }

    public function sugarChecks(): HasMany { return $this->hasMany(AdmissionSugarCheck::class)->latest('checked_at'); }
    public function fluidEntries(): HasMany { return $this->hasMany(AdmissionFluidEntry::class)->latest('recorded_at'); }
    public function oxygenAdministrations(): HasMany { return $this->hasMany(AdmissionOxygenAdministration::class)->latest('administered_at'); }
    public function nebulizerUses(): HasMany { return $this->hasMany(AdmissionNebulizerUse::class)->latest('used_at'); }
    public function damaRecord(): \Illuminate\Database\Eloquent\Relations\HasOne { return $this->hasOne(AdmissionDamaRecord::class); }

}
