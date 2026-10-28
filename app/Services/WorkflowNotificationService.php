<?php

namespace App\Services;

use App\Enums\Role;
use App\Models\Admission;
use App\Models\Visit;
use App\Models\User;
use App\Notifications\HospitalActionNotification;
use Illuminate\Support\Collection;

class WorkflowNotificationService
{
    /** Notify hospital staff who can work on billing records. */
    public function billing(int $hospitalId, string $title, string $message): void
    {
        $this->notify(
            $this->staff($hospitalId, [Role::Admin, Role::Accountant, Role::Receptionist]),
            $title,
            $message,
            route('billing.index'),
            'receipt',
        );
    }

    /** Notify nurses assigned to the visit's department and central OPD triage nurses. */
    public function nursesFor(Visit $visit, string $title, string $message): void
    {
        $nurses = $this->staff((int) $visit->hospital_id, [Role::Nurse])
            ->loadMissing(['hospital', 'department'])
            ->filter(fn (User $nurse) => ($visit->department_id !== null && (int) $nurse->department_id === (int) $visit->department_id)
                || $nurse->isCentralVitalsNurse());

        $this->notify($nurses, $title, $message, route('triage.index'), 'activity');
    }

    /** Notify the assigned doctor, or doctors who can pick up an unassigned visit in that department. */
    public function doctorsFor(Visit $visit, string $title, string $message): void
    {
        $doctors = $this->staff((int) $visit->hospital_id, [Role::Doctor]);
        if ($visit->doctor_id) {
            $doctors = $doctors->where('id', (int) $visit->doctor_id);
        } elseif ($visit->department_id) {
            $doctors = $doctors->where('department_id', (int) $visit->department_id);
        } else {
            $doctors = collect();
        }

        $this->notify($doctors, $title, $message, route('consultations.index'), 'stethoscope');
    }

    public function wardNursesFor(Admission $admission, string $title, string $message): void
    {
        $nurses = $this->staff((int) $admission->hospital_id, [Role::Nurse])
            ->where('department_id', (int) $admission->department_id);

        $this->notify($nurses, $title, $message, route('admissions.index'), 'activity');
    }

    public function attendingDoctorFor(Admission $admission, string $title, string $message): void
    {
        $doctors = $this->staff((int) $admission->hospital_id, [Role::Doctor])
            ->where('id', (int) $admission->attending_doctor_id);

        $this->notify($doctors, $title, $message, route('admissions.index'), 'stethoscope');
    }

    /** Notify lab staff in the departments that own the newly requested tests. */
    public function labStaff(int $hospitalId, iterable $departmentIds, string $message): void
    {
        $departmentIds = collect($departmentIds)->filter()->unique()->values();
        if ($departmentIds->isEmpty()) {
            return;
        }

        $labStaff = $this->staff($hospitalId, [Role::Lab])->whereIn('department_id', $departmentIds);
        $this->notify($labStaff, 'New lab request', $message, route('lab.index'), 'flask');
    }

    /** Notify staff who can process orders in the main pharmacy queue. */
    public function pharmacy(Visit $visit, string $message): void
    {
        $staff = $this->staff((int) $visit->hospital_id, [Role::Admin, Role::Pharmacist, Role::ChiefPharmacist])
            ->filter(fn (User $user) => $user->hasRole(Role::Admin, Role::ChiefPharmacist)
                || ($user->hasRole(Role::Pharmacist) && ! $user->isEmergencyDepartmentStaff()));

        $this->notify($staff, 'New pharmacy order', $message, route('pharmacy.index'), 'pill');
    }

    private function staff(int $hospitalId, array $roles): Collection
    {
        if ($hospitalId <= 0) {
            return collect();
        }

        return User::query()
            ->where('hospital_id', $hospitalId)
            ->where('is_active', true)
            ->whereIn('role', array_map(fn (Role $role) => $role->value, $roles))
            ->with('department')
            ->get();
    }

    private function notify(iterable $users, string $title, string $message, string $url, string $icon): void
    {
        foreach ($users as $user) {
            $user->notify(new HospitalActionNotification($title, $message, $url, $icon));
        }
    }
}
