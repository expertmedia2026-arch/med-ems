<?php

namespace App\Services;

use App\Enums\Role;
use App\Models\Department;
use Illuminate\Support\Collection;

/**
 * Manages department records and the department data used by staff forms.
 */
class DepartmentService
{
    /** Return all departments in display order for selection controls. */
    public function allForSelection(): Collection
    {
        return Department::orderBy('name')->get();
    }

    /** Paginate departments with staff, pending invitations, service, and active-admission counts. */
    public function index()
    {
        return Department::with('headOfDepartment')->withCount([
            'users' => fn ($query) => $query->where('role', '!=', Role::Patient->value),
            'staffInvitations' => fn ($query) => $query->pending(),
            'consultationTypes',
            'admissions' => fn ($query) => $query->where('status', 'active'),
        ])->orderBy('name')->paginate(15);
    }

    /**
     * Create a department from validated form attributes.
     *
     * @param array<string, mixed> $data Validated department fields.
     */
    public function create(array $data): Department
    {
        return Department::create($data);
    }

    /**
     * Update a department and return its refreshed persisted state.
     *
     * @param array<string, mixed> $data Validated department fields.
     */
    public function update(Department $department, array $data): Department
    {
        $department->update($data);

        return $department->refresh();
    }

    /** Determine whether the department has no staff, pending invitations, or consultation types. */
    public function canDelete(Department $department): bool
    {
        return ! $department->users()->exists()
            && ! $department->staffInvitations()->pending()->exists()
            && ! $department->consultationTypes()->exists();
    }

    /** Delete the department; callers should first enforce the result of {@see canDelete()}. */
    public function delete(Department $department): void
    {
        $department->delete();
    }
}
