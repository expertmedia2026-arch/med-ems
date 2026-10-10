<?php

namespace App\Services;

use App\Models\ConsultationType;
use App\Models\Department;

class ConsultationTypeService
{
    public function index()
    {
        return ConsultationType::with('department')->orderBy('department_id')->orderBy('name')->paginate(15);
    }

    public function departments()
    {
        return Department::orderBy('name')->get();
    }

    public function create(array $data): ConsultationType
    {
        return ConsultationType::create($data);
    }

    public function update(ConsultationType $consultationType, array $data): ConsultationType
    {
        $consultationType->update($data);

        return $consultationType->refresh();
    }
}
