<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PatientProfileResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'hospital_number' => $this->hospital_number,
            'first_name' => $this->first_name,
            'last_name' => $this->last_name,
            'full_name' => $this->full_name,
            'gender' => $this->gender,
            'date_of_birth' => $this->date_of_birth?->toDateString(),
            'age' => $this->age,
            'phone' => $this->phone,
            'email' => $this->email,
            'address' => $this->address,
            'blood_group' => $this->blood_group,
            'genotype' => $this->genotype,
            'allergies' => $this->allergies,
            'is_nhis' => $this->is_nhis,
            'nhis_number' => $this->nhis_number,
            'next_of_kin' => [
                'name' => $this->next_of_kin_name,
                'phone' => $this->next_of_kin_phone,
            ],
            'registration_paid' => (bool) $this->registration_paid_at,
        ];
    }
}
