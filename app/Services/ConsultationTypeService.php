<?php

namespace App\Services;

use App\Models\ConsultationType;
use App\Models\Department;
use Illuminate\Support\Collection;

/**
 * Provides the consultation-price catalogue used by staff and appointment workflows.
 */
class ConsultationTypeService
{
    /** Return the paginated catalogue with its department for the management screen. */
    public function index()
    {
        return ConsultationType::with('department')->orderBy('department_id')->orderBy('name')->paginate(15);
    }

    /** List departments that can be assigned to consultation types. */
    public function departments()
    {
        return Department::orderBy('name')->get();
    }

    /** Get active consultation types for the department selected during visit registration. */
    public function activeForDepartment(Department $department): Collection
    {
        return ConsultationType::where('department_id', $department->id)
            ->where('is_active', true)
            ->orderBy('name')
            ->get();
    }

    /**
     * Create a consultation type from validated catalogue attributes.
     *
     * @param array<string, mixed> $data Validated department, name, description, price, and activity fields.
     */
    public function create(array $data): ConsultationType
    {
        return ConsultationType::create($data);
    }

    /**
     * Apply validated catalogue changes and return the persisted model.
     *
     * @param array<string, mixed> $data Validated consultation-type attributes.
     */
    public function update(ConsultationType $consultationType, array $data): ConsultationType
    {
        $consultationType->update($data);

        return $consultationType->refresh();
    }
}
