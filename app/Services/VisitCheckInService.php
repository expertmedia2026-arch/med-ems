<?php

namespace App\Services;

use App\Enums\AppointmentStatus;
use App\Enums\VisitStatus;
use App\Models\Appointment;
use App\Models\ConsultationType;
use App\Models\Patient;
use App\Models\Visit;
use Illuminate\Support\Facades\DB;

class VisitCheckInService
{
    public function __construct(private readonly BillingService $billing) {}

    /** Return null if the patient already has an active consultation visit. */
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

    /** Return null when no active tests remain on the lab appointment. */
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

    public function doctorDepartmentId(Appointment $appointment): ?int
    {
        $appointment->loadMissing('doctor');

        return $appointment->doctor?->department_id;
    }

    private function lockScheduledAppointment(Appointment $appointment): Appointment
    {
        $appointment = Appointment::query()->lockForUpdate()->findOrFail($appointment->id);

        abort_unless($appointment->status === AppointmentStatus::Scheduled, 422);
        abort_unless($appointment->scheduled_at->isToday(), 422, 'An appointment can only be checked in on its scheduled date.');

        return $appointment;
    }
}
