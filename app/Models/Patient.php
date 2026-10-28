<?php

namespace App\Models;

use App\Enums\VisitStatus;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Str;

class Patient extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'date_of_birth' => 'date',
            'dob_estimated' => 'boolean',
            'identity_status' => 'string',
            'is_nhis' => 'boolean',
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
        $hospitalName = HospitalSetting::current()->name;
        $prefix = self::hospitalNumberPrefix($hospitalName);

        return $prefix.'-'.now()->format('y').'-'.str_pad((string) $next, 5, '0', STR_PAD_LEFT);
    }

    private static function hospitalNumberPrefix(?string $hospitalName): string
    {
        $words = preg_split('/[^A-Za-z0-9]+/', Str::ascii(trim((string) $hospitalName)), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $initials = implode('', array_map(fn (string $word) => $word[0], $words));

        return Str::upper($initials ?: 'HOSP');
    }

    protected function fullName(): Attribute
    {
        return Attribute::get(fn () => $this->identity_status === 'unidentified'
            ? 'Unidentified patient'
            : "{$this->first_name} {$this->last_name}");
    }

    protected function photoUrl(): Attribute
    {
        return Attribute::get(fn () => $this->photo_path ? route('patients.photo', $this) : null);
    }

    protected function age(): Attribute
    {
        return Attribute::get(fn () => $this->identity_status === 'unidentified' ? null : $this->date_of_birth?->age);
    }

    public function scopeSearch($query, ?string $term)
    {
        return $query->when($term, fn ($q) => $q->where(function ($q) use ($term) {
            $q->whereLike('first_name', "%{$term}%")
                ->orWhereLike('last_name', "%{$term}%")
                ->orWhereLike('hospital_number', "%{$term}%")
                ->orWhereLike('phone', "%{$term}%");
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

    public function admissions(): HasMany
    {
        return $this->hasMany(Admission::class);
    }

    public function activeAdmission(): HasOne
    {
        return $this->hasOne(Admission::class)->where('status', 'active');
    }

    public function currentVisit(): ?Visit
    {
        return $this->visits()
            ->where('type', 'consultation')
            ->whereNotIn('status', [VisitStatus::Completed->value, VisitStatus::Inpatient->value])
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
