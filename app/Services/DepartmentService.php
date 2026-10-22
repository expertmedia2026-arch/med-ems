<?php

namespace App\Services;

use App\Enums\Role;
use App\Models\Department;
use Illuminate\Support\Collection;

class DepartmentService
{
    public function allForSelection(): Collection
    {
        return Department::orderBy('name')->get();
    }

    public function index()
    {
        return Department::with('headOfDepartment')->withCount([
            'users' => fn ($query) => $query->where('role', '!=', Role::Patient->value),
            'staffInvitations' => fn ($query) => $query->pending(),
            'consultationTypes',
            'admissions' => fn ($query) => $query->where('status', 'active'),
        ])->orderBy('name')->paginate(15);
    }

    public function create(array $data): Department
    {
        return Department::create($data);
    }

    public function update(Department $department, array $data): Department
    {
        $department->update($data);

        return $department->refresh();
    }

    public function canDelete(Department $department): bool
    {
        return ! $department->users()->exists()
            && ! $department->staffInvitations()->pending()->exists()
            && ! $department->consultationTypes()->exists();
    }

    public function delete(Department $department): void
    {
        $department->delete();
    }
}
