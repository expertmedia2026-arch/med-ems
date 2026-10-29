<?php

namespace App\Services;

use App\Enums\Role;
use App\Enums\VisitStatus;
use App\Models\Admission;
use App\Models\MedicationAdministration;
use App\Models\Prescription;
use App\Models\Visit;
use App\Models\User;
use App\Notifications\HospitalActionNotification;
use Illuminate\Support\Collection;

/**
 * Sends workflow notifications to active staff in the relevant hospital and role.
 *
 * Recipient queries are constrained by hospital ID, role, activity, and workflow
 * assignment where applicable. Notifications are delivered through Laravel's user
 * notification channel and are not wrapped in a database transaction here.
 */
class WorkflowNotificationService
{
    /** Notify hospital staff who can work on billing records. */
    /**
     * Notify active administrators, accountants, and receptionists about billing work.
     *
     * @param  int  $hospitalId  Hospital whose billing staff may receive the alert.
     * @param  string  $title  Notification title.
     * @param  string  $message  Notification body.
     */
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
    /**
     * Notify nurses assigned to the visit department or central OPD triage.
     *
     * Recipients are restricted to active nurses in the visit's hospital.
     *
     * @param  Visit  $visit  Visit that determines hospital and department recipients.
     * @param  string  $title  Notification title.
     * @param  string  $message  Notification body.
     */
    public function nursesFor(Visit $visit, string $title, string $message): void
    {
        $nurses = $this->staff((int) $visit->hospital_id, [Role::Nurse])
            ->loadMissing(['hospital', 'department'])
            ->filter(fn (User $nurse) => ($visit->department_id !== null && (int) $nurse->department_id === (int) $visit->department_id)
                || $nurse->isCentralVitalsNurse());

        $this->notify($nurses, $title, $message, route('triage.index'), 'activity');
    }

    /** Notify nurses who administer medication in the visit's care department. */
    /**
     * Notify nurses responsible for medication administration in the care department.
     *
     * For inpatient visits, the active admission's ward department takes precedence.
     * No notification is sent when neither the visit nor admission has a department.
     *
     * @param  Visit  $visit  Visit whose care department determines recipients.
     * @param  string  $title  Notification title.
     * @param  string  $message  Notification body.
     */
    public function medicationNursesFor(Visit $visit, string $title, string $message): void
    {
        $departmentId = $visit->department_id;
        if ($visit->status === VisitStatus::Inpatient) {
            $departmentId = $visit->admission()->where('status', 'active')->value('department_id') ?? $departmentId;
        }

        if (! $departmentId) {
            return;
        }

        $nurses = $this->staff((int) $visit->hospital_id, [Role::Nurse])
            ->where('department_id', (int) $departmentId);

        $this->notify($nurses, $title, $message, route('nursing.administrations.show', $visit), 'pill');
    }

    /**
     * Notify medication nurses that an administration requirement was waived.
     *
     * Patient and drug details are loaded when needed to compose the message.
     *
     * @param  Visit  $visit  Visit containing the prescription.
     * @param  Prescription  $prescription  Prescription whose administration was waived.
     */
    public function medicationWaived(Visit $visit, Prescription $prescription): void
    {
        $visit->loadMissing('patient');
        $prescription->loadMissing('drug');

        $this->medicationNursesFor(
            $visit,
            'Medication administration waived',
            "The administration requirement for {$prescription->drug?->display_name} for {$visit->patient->full_name} ({$visit->patient->hospital_number}) was waived. Reason: {$prescription->administration_waiver_reason}.",
        );
    }

    /** Notify the attending doctor (or department doctors) and hospital admins after a nurse records an outcome. */
    public function medicationOutcome(MedicationAdministration $record): void
    {
        $record->loadMissing(['visit.patient', 'prescription.drug', 'nurse']);
        $visit = $record->visit;
        $patient = $visit?->patient;
        $prescription = $record->prescription;

        if (! $visit || ! $patient || ! $prescription) {
            return;
        }

        $outcome = ucfirst(str_replace('_', ' ', (string) $record->status));
        $dose = filled($record->dose) ? " ({$record->dose})" : '';
        $message = "{$record->nurse?->name} recorded {$outcome} for {$prescription->drug?->display_name}{$dose} for {$patient->full_name} ({$patient->hospital_number}).";
        $title = 'Medication administration recorded';

        $admins = $this->staff((int) $visit->hospital_id, [Role::Admin]);
        $this->notify($admins, $title, $message, route('nursing.administrations.show', $visit), 'pill');

        $doctors = $this->staff((int) $visit->hospital_id, [Role::Doctor]);
        if ($visit->doctor_id) {
            $doctors = $doctors->where('id', (int) $visit->doctor_id);
        } elseif ($visit->department_id) {
            $doctors = $doctors->where('department_id', (int) $visit->department_id);
        } else {
            $doctors = collect();
        }

        $doctorUrl = $visit->status === VisitStatus::Inpatient
            ? route('admissions.index')
            : ($visit->status === VisitStatus::Completed
                ? route('consultations.history', $visit)
                : route('consultations.show', $visit));

        $this->notify($doctors, $title, $message, $doctorUrl, 'pill');
    }

    /** Notify the assigned doctor, or doctors who can pick up an unassigned visit in that department. */
    /**
     * Notify the assigned doctor, or department doctors for an unassigned visit.
     *
     * @param  Visit  $visit  Visit whose doctor or department determines recipients.
     * @param  string  $title  Notification title.
     * @param  string  $message  Notification body.
     */
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

    /**
     * Notify active nurses assigned to the admission's ward department.
     *
     * @param  Admission  $admission  Admission determining hospital and ward recipients.
     * @param  string  $title  Notification title.
     * @param  string  $message  Notification body.
     */
    public function wardNursesFor(Admission $admission, string $title, string $message): void
    {
        $nurses = $this->staff((int) $admission->hospital_id, [Role::Nurse])
            ->where('department_id', (int) $admission->department_id);

        $this->notify($nurses, $title, $message, route('admissions.index'), 'activity');
    }

    /**
     * Notify the admission's assigned attending doctor when active in its hospital.
     *
     * @param  Admission  $admission  Admission determining the attending doctor and hospital.
     * @param  string  $title  Notification title.
     * @param  string  $message  Notification body.
     */
    public function attendingDoctorFor(Admission $admission, string $title, string $message): void
    {
        $doctors = $this->staff((int) $admission->hospital_id, [Role::Doctor])
            ->where('id', (int) $admission->attending_doctor_id);

        $this->notify($doctors, $title, $message, route('admissions.index'), 'stethoscope');
    }

    /**
     * Notify active lab staff in the departments responsible for requested tests.
     *
     * Empty department IDs are removed and duplicate IDs collapsed; no message is
     * sent if no eligible department remains.
     *
     * @param  int  $hospitalId  Hospital whose lab staff may receive the alert.
     * @param  iterable<int|string|null>  $departmentIds  Departments responsible for the lab requests.
     * @param  string  $message  Notification body.
     */
    public function labStaff(int $hospitalId, iterable $departmentIds, string $message): void
    {
        $departmentIds = collect($departmentIds)->filter()->unique()->values();
        if ($departmentIds->isEmpty()) {
            return;
        }

        $labStaff = $this->staff($hospitalId, [Role::Lab])->whereIn('department_id', $departmentIds);
        $this->notify($labStaff, 'New lab request', $message, route('lab.index'), 'flask');
    }

    /**
     * Notify staff who can process orders in the main pharmacy queue.
     *
     * Includes administrators and chief pharmacists, plus pharmacists who are not
     * assigned to the emergency department.
     *
     * @param  Visit  $visit  Visit whose hospital scopes pharmacy recipients.
     * @param  string  $message  Notification body.
     */
    public function pharmacy(Visit $visit, string $message): void
    {
        $staff = $this->staff((int) $visit->hospital_id, [Role::Admin, Role::Pharmacist, Role::ChiefPharmacist])
            ->filter(fn (User $user) => $user->hasRole(Role::Admin, Role::ChiefPharmacist)
                || ($user->hasRole(Role::Pharmacist) && ! $user->isEmergencyDepartmentStaff()));

        $this->notify($staff, 'New pharmacy order', $message, route('pharmacy.index'), 'pill');
    }

    /**
     * Load active users for one hospital and a set of staff roles.
     *
     * @param  int  $hospitalId  Hospital scope for the recipient query.
     * @param  array<int, Role>  $roles  Roles permitted to receive the notification.
     * @return Collection<int, User> Active users with departments loaded, or an empty collection for an invalid hospital ID.
     */
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

    /**
     * Dispatch the same hospital action notification to each supplied user.
     *
     * @param  iterable<User>  $users  Notification recipients already selected by workflow rules.
     * @param  string  $title  Notification title.
     * @param  string  $message  Notification body.
     * @param  string  $url  Destination URL included in the notification.
     * @param  string  $icon  Icon identifier included in the notification.
     */
    private function notify(iterable $users, string $title, string $message, string $url, string $icon): void
    {
        foreach ($users as $user) {
            $user->notify(new HospitalActionNotification($title, $message, $url, $icon));
        }
    }
}
