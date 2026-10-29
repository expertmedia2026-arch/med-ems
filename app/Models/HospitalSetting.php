<?php

namespace App\Models;

use App\Support\HospitalContext;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HospitalSetting extends Model
{
    protected $guarded = [];

    /** Keep configured fees as decimal strings and decrypt the payment secret transparently. */
    protected function casts(): array
    {
        return [
            'registration_fee' => 'decimal:2',
            'emergency_fee' => 'decimal:2',
            'mortuary_intake_fee' => 'decimal:2',
            'mortuary_daily_storage_fee' => 'decimal:2',
            'state_mortuary_tax_daily_fee' => 'decimal:2',
            'oxygen_per_administration_fee' => 'decimal:2',
            'nebulizer_fee_per_three_uses' => 'decimal:2',
            'sugar_check_fee' => 'decimal:2',
            'maternity_vaginal_delivery_fee' => 'decimal:2',
            'maternity_assisted_delivery_fee' => 'decimal:2',
            'maternity_cesarean_delivery_fee' => 'decimal:2',
            'maternity_other_delivery_fee' => 'decimal:2',
            'maternity_delivery_pack_fee' => 'decimal:2',
            'paystack_secret_key' => 'encrypted',
        ];
    }

    /**
     * Resolve settings for the active tenant, creating that tenant's settings
     * row when first accessed; the fallback is retained for unscoped system use.
     */
    public static function current(): self
    {
        $hospitalId = app(HospitalContext::class)->id() ?? auth()->user()?->hospital_id;

        if ($hospitalId === null) {
            return static::withoutGlobalScope('hospital')->orderBy('id')->first()
                ?? new static(['name' => config('app.name', 'Hospital')]);
        }

        return static::query()
            ->where('hospital_id', $hospitalId)
            ->first()
            ?? static::create([
                'hospital_id' => $hospitalId,
                'name' => config('app.name', 'Hospital'),
            ]);
    }

    /** The tenant whose branding and billing configuration this row stores. */
    public function hospital(): BelongsTo
    {
        return $this->belongsTo(Hospital::class);
    }

    /** Convert a stored public-disk logo path into a browser-ready URL. */
    protected function logoUrl(): Attribute
    {
        return Attribute::get(fn () => $this->logo_path ? asset('storage/'.$this->logo_path) : null);
    }
}
