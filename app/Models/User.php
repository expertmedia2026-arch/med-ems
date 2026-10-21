<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Enums\Role;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

#[Fillable(['name', 'email', 'role', 'is_active', 'email_verified_at', 'password', 'patient_id', 'department_id'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'role' => Role::class,
            'is_active' => 'boolean',

        ];
    }

    public function hasRole(Role|string ...$roles): bool
    {
        $current = $this->role->value;

        foreach ($roles as $role) {
            if ($current === ($role instanceof Role ? $role->value : $role)) {
                return true;
            }
        }

        return false;
    }

    public function isCentralVitalsNurse(): bool
    {
        if (! $this->hasRole(Role::Nurse)) {
            return false;
        }

        $departmentName = trim((string) $this->department?->name);

        return preg_match('/\\bOPD\\b|^outpatient(?:\\s|$)/i', $departmentName) === 1;
    }

    public function isEmergencyDepartmentStaff(): bool
    {
        $departmentName = trim((string) $this->department?->name);

        return preg_match('/accident\\s*(?:&|and)\\s*emergency|emergency department/i', $departmentName) === 1;
    }

    public function canAccessEmergency(): bool
    {
        return $this->hasRole(Role::Admin, Role::Receptionist)
            || ($this->hasRole(Role::Nurse) && ($this->isCentralVitalsNurse() || $this->isEmergencyDepartmentStaff()))
            || ($this->hasRole(Role::Doctor) && $this->isEmergencyDepartmentStaff());
    }

    public function canRegisterEmergencyArrival(): bool
    {
        return $this->hasRole(Role::Admin, Role::Receptionist)
            || ($this->hasRole(Role::Nurse) && ($this->isCentralVitalsNurse() || $this->isEmergencyDepartmentStaff()));
    }

    public function canReceiveEmergencyMortuaryHandoff(): bool
    {
        return $this->hasRole(Role::Admin)
            || ($this->hasRole(Role::Nurse) && ($this->isCentralVitalsNurse() || $this->isEmergencyDepartmentStaff()));
    }

    public function scopeOfRole($query, Role $role)
    {
        return $query->where('role', $role->value);
    }

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function doctorAvailabilities(): HasMany
    {
        return $this->hasMany(DoctorAvailability::class, 'doctor_id');
    }

    public function nurseDuties(): HasMany
    {
        return $this->hasMany(NurseDuty::class, 'nurse_id');
    }
}
