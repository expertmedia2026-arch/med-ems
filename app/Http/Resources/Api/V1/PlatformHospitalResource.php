<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PlatformHospitalResource extends JsonResource
{
    /**
     * Serialize the hospital's platform-level configuration and aggregate summary.
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'facility_type' => $this->facility_type,
            'is_active' => (bool) $this->is_active,
            'enabled_modules' => $this->enabled_modules ?? [],
            'admission_template' => $this->admission_template ?? 'basic',
            'admin_emails' => $this->admin_emails ?? [],
            'staff_count' => (int) ($this->staff_count ?? 0),
            'patient_count' => (int) ($this->patient_count ?? 0),
        ];
    }
}
