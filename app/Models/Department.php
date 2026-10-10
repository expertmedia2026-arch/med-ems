<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Department extends Model
{
    protected $guarded = [];

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function consultationTypes(): HasMany
    {
        return $this->hasMany(ConsultationType::class);
    }

    public function staffInvitations(): HasMany
    {
        return $this->hasMany(StaffInvitation::class);
    }
}
