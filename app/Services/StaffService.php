<?php

namespace App\Services;

use App\Enums\Role;
use App\Models\Department;
use App\Models\StaffInvitation;
use App\Models\User;

class StaffService
{
    public function __construct(private readonly GlobalAdminNotificationService $globalAdminNotifications) {}

    public function indexData(array $filters, ?User $actor = null): array
    {
        $isDepartmentViewer = $actor?->role === Role::ChiefAccountant;

        return [
            'staff' => User::query()->with('department')->where('role', '!=', Role::Patient->value)
                ->when($isDepartmentViewer, fn ($query) => $query->where('department_id', $actor?->department_id ?? 0))
                ->when($filters['q'] ?? null, fn ($query, $term) => $query->where(fn ($search) => $search
                    ->whereLike('name', "%{$term}%")->orWhereLike('email', "%{$term}%")))
                ->when($filters['role'] ?? null, fn ($query, $role) => $query->where('role', $role))
                ->when(! $isDepartmentViewer && ($filters['department_id'] ?? null), fn ($query) => $query->where('department_id', $filters['department_id']))
                ->when(($filters['status'] ?? null) === 'active', fn ($query) => $query->where('is_active', true))
                ->when(($filters['status'] ?? null) === 'inactive', fn ($query) => $query->where('is_active', false))
                ->orderBy('name')->paginate(15)->withQueryString(),
            'departments' => $isDepartmentViewer ? collect() : Department::orderBy('name')->get(),
            'invitations' => $isDepartmentViewer ? collect() : StaffInvitation::pending()->with(['inviter', 'department'])->latest()->get(),
            'isDepartmentViewer' => $isDepartmentViewer,
        ];
    }

    public function departments()
    {
        return Department::orderBy('name')->get();
    }

    public function create(array $data): User
    {
        $this->assignFinanceDepartment($data);
        $user = User::create($data + ['is_active' => true, 'email_verified_at' => now()]);
        StaffInvitation::where('email', $user->email)->delete();

        return $user;
    }

    public function update(User $staff, array $data, User $actor): User
    {
        $this->assertStaffRecord($staff);
        $previousEmail = $staff->email;
        $this->assignFinanceDepartment($data);
        if ($staff->is($actor)) {
            $data['role'] = $staff->role->value;
        }
        if (empty($data['password'] ?? null)) {
            unset($data['password']);
        }
        $staff->update($data);

        if ($staff->wasChanged('email')) {
            $this->globalAdminNotifications->hospitalAdminEmailChanged($staff, $previousEmail);
        }

        return $staff->refresh();
    }

    public function toggle(User $staff, User $actor): User
    {
        $this->assertStaffRecord($staff);
        abort_if($staff->is($actor), 403, 'You cannot deactivate your own account.');
        $staff->update(['is_active' => ! $staff->is_active]);

        if (! $staff->is_active) {
            $staff->tokens()->delete();
        }

        return $staff->refresh();
    }

    public function assertStaffRecord(User $staff): void
    {
        abort_if($staff->role === Role::Patient, 404);
    }

    private function assignFinanceDepartment(array &$data): void
    {
        $role = isset($data['role']) ? Role::tryFrom($data['role']) : null;

        if ($role?->isFinanceStaff()) {
            $data['department_id'] = Department::where('name', Department::FINANCE_NAME)->value('id');
        } elseif ($role?->isPharmacyStaff()) {
            $data['department_id'] = Department::where('name', Department::PHARMACY_NAME)->value('id');
        }
    }
}
