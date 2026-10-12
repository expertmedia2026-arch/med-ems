<?php

namespace App\Models;

use App\Enums\AppointmentStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Appointment extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'scheduled_at' => 'datetime',
            'status'       => AppointmentStatus::class,
        ];
    }

    public function isLab(): bool
    {
        return $this->type === 'lab';
    }

    public function scopeToday($query)
    {
        return $query->whereDate('scheduled_at', today());
    }

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    public function doctor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'doctor_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function reassignments(): HasMany
    {
        return $this->hasMany(AppointmentReassignment::class)->latest();
    }

    /** Tests booked on a lab appointment. */
    public function labTests(): BelongsToMany
    {
        return $this->belongsToMany(LabTest::class, 'appointment_lab_test');
    }
}
