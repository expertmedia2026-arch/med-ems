<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;

class HospitalSetting extends Model
{
    protected $guarded = [];

    private static ?self $current = null;

    protected function casts(): array
    {
        return [
            'registration_fee' => 'decimal:2',
            'emergency_fee' => 'decimal:2',
            'mortuary_intake_fee' => 'decimal:2',
            'mortuary_daily_storage_fee' => 'decimal:2',
            'paystack_secret_key' => 'encrypted',
        ];
    }

    public static function current(): self
    {
        return self::$current ??= static::first()
            ?? static::create(['name' => config('app.name', 'Hospital')]);
    }

    protected function logoUrl(): Attribute
    {
        return Attribute::get(fn () => $this->logo_path ? asset('storage/'.$this->logo_path) : null);
    }
}
