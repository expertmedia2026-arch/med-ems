<?php

namespace App\Services;

use App\Enums\Role;
use App\Models\Department;
use App\Models\StaffInvitation;
use App\Models\User;

/**
 * Provides staff directory data and manages hospital staff accounts.
 *
 * Department viewers are restricted to their own department and cannot see
 * invitations or the full department list. Role-specific finance and pharmacy
 * assignments are normalized to their designated departments.
 */
class StaffService
{
    /** @param GlobalAdminNotificationService $globalAdminNotifications Notifies global administrators about hospital-admin email changes. */
    public function __construct(private readonly GlobalAdminNotificationService $globalAdminNotifications) {}

    /**
     * Build filtered staff, department, and invitation data for the staff directory.
     *
     * Chief-accountant viewers see only their own department's staff and receive no
     * department or invitation lists. Other filters apply only to the staff query.
     *
     * @param  array<string, mixed>  $filters  Optional q, role, department_id, and status filters.
     * @param  User|null  $actor  Viewer whose role and department constrain the result.
     * @return array{staff: mixed, departments: \Illuminate\Support\Collection, invitations: \Illuminate\Support\Collection, isDepartmentViewer: bool} Directory view data.
     */
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

    /**
     * Return departments sorted by name for staff-management forms.
     *
     * @return \Illuminate\Database\Eloquent\Collection<int, Department> Department records.
     */
    public function departments()
    {
        return Department::orderBy('name')->get();
    }

    /**
     * Create an active, verified staff account and clear invitations for its email.
     *
     * Finance and pharmacy roles are assigned to their designated departments
     * before persistence.
     *
     * @param  array<string, mixed>  $data  Validated staff account fields.
     * @return User Newly created staff account.
     */
    public function create(array $data): User
    {
        $this->assignFinanceDepartment($data);
        $user = User::create($data + ['is_active' => true, 'email_verified_at' => now()]);
        StaffInvitation::where('email', $user->email)->delete();

        return $user;
    }

    /**
     * Update a staff account while preserving self-service and role constraints.
     *
     * Patients cannot be edited as staff. A user cannot change their own role, an
     * empty password is ignored, and changed email addresses trigger an admin notice.
     *
     * @param  User  $staff  Staff account being updated.
     * @param  array<string, mixed>  $data  Validated account fields.
     * @param  User  $actor  Account performing the update.
     * @return User Refreshed staff account.
     */
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

    /**
     * Toggle staff activity and revoke tokens when deactivating an account.
     *
     * Staff cannot deactivate their own account, and patient accounts are not valid
     * staff records.
     *
     * @param  User  $staff  Account whose active state is toggled.
     * @param  User  $actor  Account performing the toggle.
     * @return User Refreshed staff account.
     */
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

    /**
     * Reject a patient account where a staff account is required.
     *
     * @param  User  $staff  Candidate staff account.
     */
    public function assertStaffRecord(User $staff): void
    {
        abort_if($staff->role === Role::Patient, 404);
    }

    /**
     * Force finance and pharmacy roles into their designated departments.
     *
     * Other roles retain the department supplied in the input data.
     *
     * @param  array<string, mixed>  $data  Staff attributes updated in place.
     */
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
