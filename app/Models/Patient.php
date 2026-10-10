<?php

namespace App\Models;

use App\Enums\VisitStatus;
use App\Models\Appointment;
use App\Models\Visit;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Patient extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'date_of_birth' => 'date',
            'dob_estimated' => 'boolean',
            'registration_paid_at' => 'datetime',


        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Patient $patient) {
            $patient->hospital_number ??= self::nextHospitalNumber();
        });
    }

    public static function nextHospitalNumber(): string
    {
        $next = (self::max('id') ?? 0) + 1;

        return 'HN-' . now()->format('y') . '-' . str_pad($next, 5, '0', STR_PAD_LEFT);
    }

    protected function fullName(): Attribute
    {
        return Attribute::get(fn () => "{$this->first_name} {$this->last_name}");
    }

    protected function age(): Attribute
    {
        return Attribute::get(fn () => $this->date_of_birth?->age);
    }

    public function scopeSearch($query, ?string $term)
    {
        return $query->when($term, fn ($q) => $q->where(function ($q) use ($term) {
            $q->where('first_name', 'like', "%{$term}%")
              ->orWhere('last_name', 'like', "%{$term}%")
              ->orWhere('hospital_number', 'like', "%{$term}%")
              ->orWhere('phone', 'like', "%{$term}%");
        }));
    }

    public function appointments(): HasMany
    {
        return $this->hasMany(Appointment::class);
    }

    public function visits(): HasMany
    {
        return $this->hasMany(Visit::class);
    }

   
    public function currentVisit(): ?Visit
    {
        return $this->visits()
            ->where('type', 'consultation')
            ->where('status', '!=', VisitStatus::Completed->value)
            ->latest('checked_in_at')
            ->first();
    }

    public function user(): HasOne
    {
        return $this->hasOne(User::class);
    }

    public function invitations(): HasMany
    {
        return $this->hasMany(PatientInvitation::class);
    }

    
}