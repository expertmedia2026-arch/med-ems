<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphOne;

class EmergencyPharmacyItem extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['unit_price' => 'decimal:2', 'is_active' => 'boolean'];
    }

    public function uses(): HasMany
    {
        return $this->hasMany(EmergencyItemUse::class);
    }

    public function movements(): HasMany
    {
        return $this->hasMany(EmergencyPharmacyItemMovement::class);
    }
}
