<?php

namespace App\Services;

use App\Models\ConsultationType;
use App\Models\Department;
use Illuminate\Support\Collection;

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

    public function activeForDepartment(Department $department): Collection
    {
        return ConsultationType::where('department_id', $department->id)
            ->where('is_active', true)
            ->orderBy('name')
            ->get();
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
