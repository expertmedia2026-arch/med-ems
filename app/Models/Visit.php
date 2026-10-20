<?php

namespace App\Models;

use App\Enums\VisitStatus;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Visit extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'status' => VisitStatus::class,
            'checked_in_at' => 'datetime',
            'consulted_at' => 'datetime',
            'closed_at' => 'datetime',
            'discharged_at' => 'datetime',
        ];
    }

    public function scopeToday($query)
    {
        return $query->whereDate('checked_in_at', today());
    }

    public function scopeWithStatus($query, VisitStatus ...$statuses)
    {
        return $query->whereIn('status', array_map(fn ($s) => $s->value, $statuses));
    }

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    public function appointment(): BelongsTo
    {
        return $this->belongsTo(Appointment::class);
    }

    public function doctor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'doctor_id');
    }

    public function dischargeActor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'discharged_by');
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function consultationType(): BelongsTo
    {
        return $this->belongsTo(ConsultationType::class);
    }

    public function vitals(): HasOne
    {
        return $this->hasOne(Vital::class);
    }

    public function prescriptions(): HasMany
    {
        return $this->hasMany(Prescription::class);
    }

    public function medicationAdministrations(): HasMany
    {
        return $this->hasMany(MedicationAdministration::class);
    }

    public function labRequests(): HasMany
    {
        return $this->hasMany(LabRequest::class);
    }

    public function invoice(): HasOne
    {
        return $this->hasOne(Invoice::class);
    }

    public function admission(): HasOne
    {
        return $this->hasOne(Admission::class);
    }

    public function isOpenFor(User $user): bool
    {
        return $this->status === VisitStatus::InConsultation
            && $this->doctor_id === $user->id;
    }

    protected function consultationPaid(): Attribute
    {
        return Attribute::get(function () {
            $item = $this->invoice?->items->firstWhere('category', 'consultation');

            return (bool) $item?->is_paid;
        });
    }

    public function isLabOnly(): bool
    {
        return $this->type === 'lab_only';
    }
}
