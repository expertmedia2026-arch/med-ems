<?php

namespace App\Services;

use App\Enums\Role;
use App\Models\Department;

class DepartmentService
{
    public function index()
    {
        return Department::withCount([
            'users' => fn ($query) => $query->where('role', '!=', Role::Patient->value),
            'staffInvitations' => fn ($query) => $query->pending(),
            'consultationTypes',
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
