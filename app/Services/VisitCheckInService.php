<?php

namespace App\Services;

use App\Enums\AppointmentStatus;
use App\Enums\VisitStatus;
use App\Models\Appointment;
use App\Models\ConsultationType;
use App\Models\Patient;
use App\Models\Visit;
use Illuminate\Support\Facades\DB;

/**
 * Converts walk-ins and scheduled appointments into workflow visits.
 *
 * Check-in operations lock the patient and, for appointments, the scheduled
 * appointment inside a transaction to prevent duplicate concurrent visits. Billing
 * records and workflow notifications are created as part of or after check-in.
 */
class VisitCheckInService
{
    /** @param BillingService $billing Creates or synchronizes invoices for checked-in visits. */
    public function __construct(private readonly BillingService $billing) {}

    /**
     * Create a walk-in consultation visit unless the patient is admitted or already active.
     *
     * The patient row is locked during creation and invoice generation. Nurses are
     * notified after the transaction when a visit was created.
     *
     * @param  Patient  $patient  Patient checking in without an appointment.
     * @param  array<string, mixed>  $attributes  Validated visit fields such as department, doctor, or complaint.
     * @return Visit|null New visit, or null when an admission or active visit already exists.
     */
    public function walkIn(Patient $patient, array $attributes): ?Visit
    {
        $visit = DB::transaction(function () use ($patient, $attributes) {
            $lockedPatient = Patient::query()->lockForUpdate()->findOrFail($patient->id);

            if ($lockedPatient->activeAdmission()->exists() || $lockedPatient->currentVisit()) {
                return null;
            }

            $visit = $lockedPatient->visits()->create(array_merge($attributes, [
                'type' => 'consultation',
                'status' => VisitStatus::WaitingTriage,
                'checked_in_at' => now(),
            ]));
            $this->billing->invoiceFor($visit);

            return $visit->load('consultationType');
        });

        if ($visit) {
            $visit->loadMissing('patient');
            app(WorkflowNotificationService::class)->nursesFor(
                $visit,
                'New walk-in patient',
                "{$visit->patient->full_name} ({$visit->patient->hospital_number}) checked in and is awaiting consultation payment before triage.",
            );
        }

        return $visit;
    }

    /**
     * Create a lab-only visit for the active tests on a scheduled lab appointment.
     *
     * The scheduled appointment and patient are locked during the transaction.
     * Returns null when no tests remain active or the patient is admitted; otherwise
     * creates lab requests, marks the appointment checked in, and synchronizes billing.
     * Lab staff are notified after commit.
     *
     * @param  Appointment  $appointment  Scheduled lab appointment being checked in.
     * @param  int  $requestedBy  User ID recorded as requester for each lab test.
     * @return Visit|null Created lab-only visit, or null when the appointment cannot create one.
     */
    public function fromLabAppointment(Appointment $appointment, int $requestedBy): ?Visit
    {
        $visit = DB::transaction(function () use ($appointment, $requestedBy) {
            $appointment = $this->lockScheduledAppointment($appointment);
            $tests = $appointment->labTests()->where('is_active', true)->get();

            if ($tests->isEmpty()) {
                return null;
            }

            $lockedPatient = Patient::query()->lockForUpdate()->findOrFail($appointment->patient_id);
            if ($lockedPatient->activeAdmission()->exists()) {
                return null;
            }

            $visit = $lockedPatient->visits()->create([
                'appointment_id' => $appointment->id,
                'type' => 'lab_only',
                'status' => VisitStatus::LabOnly,
                'checked_in_at' => now(),
                'complaint' => $appointment->reason,
            ]);

            foreach ($tests as $test) {
                $visit->labRequests()->create([
                    'lab_test_id' => $test->id,
                    'requested_by' => $requestedBy,
                ]);
            }

            $appointment->update(['status' => AppointmentStatus::CheckedIn]);
            $this->billing->syncFor($visit);

            return $visit->load('patient');
        });

        if ($visit) {
            $testDepartments = $visit->labRequests()->with('test')->get()->pluck('test.department_id');
            app(WorkflowNotificationService::class)->labStaff(
                (int) $visit->hospital_id,
                $testDepartments,
                "A lab appointment for {$visit->patient->full_name} ({$visit->patient->hospital_number}) has been checked in.",
            );
        }

        return $visit;
    }

    /**
     * Create a consultation visit from a scheduled doctor appointment.
     *
     * The appointment and patient are locked and revalidated inside the transaction.
     * The doctor's department must match an active consultation type; an existing
     * admission or visit returns null. Billing and nurse notification follow creation.
     *
     * @param  Appointment  $appointment  Scheduled doctor appointment being checked in.
     * @param  int  $consultationTypeId  Active consultation type selected for the visit.
     * @return Visit|null Created visit, or null when the patient already has an admission or active visit.
     */
    public function fromDoctorAppointment(
        Appointment $appointment,
        int $consultationTypeId
    ): ?Visit {
        $visit = DB::transaction(function () use ($appointment, $consultationTypeId) {
            $appointment = $this->lockScheduledAppointment($appointment);
            $lockedPatient = Patient::query()->lockForUpdate()->findOrFail($appointment->patient_id);

            if ($lockedPatient->activeAdmission()->exists() || $lockedPatient->currentVisit()) {
                return null;
            }

            $appointment->loadMissing('doctor');
            $departmentId = $appointment->doctor?->department_id;
            abort_unless($departmentId, 422, 'Assign this doctor to a department before checking in the appointment.');
            $consultationType = ConsultationType::findOrFail($consultationTypeId);
            abort_unless($consultationType->department_id === $departmentId && $consultationType->is_active, 422);

            $visit = $lockedPatient->visits()->create([
                'appointment_id' => $appointment->id,
                'doctor_id' => $appointment->doctor_id,
                'type' => 'consultation',
                'status' => VisitStatus::WaitingTriage,
                'checked_in_at' => now(),
                'department_id' => $departmentId,
                'consultation_type_id' => $consultationType->id,
                'complaint' => $appointment->reason,
            ]);

            $appointment->update(['status' => AppointmentStatus::CheckedIn]);
            $this->billing->invoiceFor($visit);

            return $visit->load(['consultationType', 'patient']);
        });

        if ($visit) {
            app(WorkflowNotificationService::class)->nursesFor(
                $visit,
                'Patient checked in',
                "{$visit->patient->full_name} ({$visit->patient->hospital_number}) checked in for an appointment and is awaiting consultation payment before triage.",
            );
        }

        return $visit;
    }

    /**
     * Return the department assigned to an appointment's doctor, if available.
     *
     * @param  Appointment  $appointment  Appointment whose doctor determines the department.
     * @return int|null Doctor's department ID, or null when no doctor/department is assigned.
     */
    public function doctorDepartmentId(Appointment $appointment): ?int
    {
        $appointment->loadMissing('doctor');

        return $appointment->doctor?->department_id;
    }

    /**
     * Lock and validate an appointment before converting it into a visit.
     *
     * Only scheduled appointments may be checked in, and only on their scheduled date.
     *
     * @param  Appointment  $appointment  Appointment candidate for check-in.
     * @return Appointment Fresh locked appointment record.
     */
    private function lockScheduledAppointment(Appointment $appointment): Appointment
    {
        $appointment = Appointment::query()->lockForUpdate()->findOrFail($appointment->id);

        abort_unless($appointment->status === AppointmentStatus::Scheduled, 422);
        abort_unless($appointment->scheduled_at->isToday(), 422, 'An appointment can only be checked in on its scheduled date.');

        return $appointment;
    }
}
