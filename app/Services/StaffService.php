<?php

namespace App\Services;

use App\Enums\Role;
use App\Models\Department;
use App\Models\StaffInvitation;
use App\Models\User;

class StaffService
{
    public function indexData(array $filters): array
    {
        return [
            'staff' => User::query()->with('department')->where('role', '!=', Role::Patient->value)
                ->when($filters['q'] ?? null, fn ($query, $term) => $query->where(fn ($search) => $search
                    ->whereLike('name', "%{$term}%")->orWhereLike('email', "%{$term}%")))
                ->when($filters['role'] ?? null, fn ($query, $role) => $query->where('role', $role))
                ->when($filters['department_id'] ?? null, fn ($query, $departmentId) => $query->where('department_id', $departmentId))
                ->when(($filters['status'] ?? null) === 'active', fn ($query) => $query->where('is_active', true))
                ->when(($filters['status'] ?? null) === 'inactive', fn ($query) => $query->where('is_active', false))
                ->orderBy('name')->paginate(15)->withQueryString(),
            'departments' => Department::orderBy('name')->get(),
            'invitations' => StaffInvitation::pending()->with(['inviter', 'department'])->latest()->get(),
        ];
    }

    public function departments()
    {
        return Department::orderBy('name')->get();
    }

    public function create(array $data): User
    {
        $user = User::create($data + ['is_active' => true, 'email_verified_at' => now()]);
        StaffInvitation::where('email', $user->email)->delete();

        return $user;
    }

    public function update(User $staff, array $data, User $actor): User
    {
        $this->assertStaffRecord($staff);
        if ($staff->is($actor)) {
            $data['role'] = $staff->role->value;
        }
        if (empty($data['password'] ?? null)) {
            unset($data['password']);
        }
        $staff->update($data);

        return $staff->refresh();
    }

    public function toggle(User $staff, User $actor): User
    {
        $this->assertStaffRecord($staff);
        abort_if($staff->is($actor), 403, 'You cannot deactivate your own account.');
        $staff->update(['is_active' => ! $staff->is_active]);

        return $staff->refresh();
    }

    public function assertStaffRecord(User $staff): void
    {
        abort_if($staff->role === Role::Patient, 404);
    }
}
