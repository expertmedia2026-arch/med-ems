<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Department extends Model
{
    public const FINANCE_NAME = 'Accounting & Finance';
    public const PHARMACY_NAME = 'Pharmacy';

    protected $guarded = [];

    protected function casts(): array
    {
        return ['has_ward' => 'boolean'];
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function headOfDepartment(): BelongsTo
    {
        return $this->belongsTo(User::class, 'hod_id');
    }

    public function consultationTypes(): HasMany
    {
        return $this->hasMany(ConsultationType::class);
    }

    public function staffInvitations(): HasMany
    {
        return $this->hasMany(StaffInvitation::class);
    }

    public function admissions(): HasMany
    {
        return $this->hasMany(Admission::class);
    }
}
