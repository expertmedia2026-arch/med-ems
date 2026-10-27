<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Hospital extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function settings(): HasMany
    {
        return $this->hasMany(HospitalSetting::class);
    }

    public function modules(): HasMany
    {
        return $this->hasMany(HospitalModule::class);
    }

    public function workflows(): HasMany
    {
        return $this->hasMany(HospitalWorkflow::class);
    }

    public function hasModule(string $key): bool
    {
        return (bool) $this->modules()->where('module', $key)->value('enabled');
    }
}
