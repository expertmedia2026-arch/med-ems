<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DepartmentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'description' => $this->description,
            'staff_count' => $this->when(isset($this->users_count), $this->users_count),
            'consultation_type_count' => $this->when(isset($this->consultation_types_count), $this->consultation_types_count),
        ];
    }
}
