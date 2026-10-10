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
        return DB::transaction(function () use ($patient, $attributes) {
            $lockedPatient = Patient::query()->lockForUpdate()->findOrFail($patient->id);

            if ($lockedPatient->currentVisit()) {
                return null;
            }

            $visit = $lockedPatient->visits()->create($attributes);
            $this->billing->invoiceFor($visit);

            return $visit->load('consultationType');
        });
    }

    /** Return null when no active tests remain on the lab appointment. */
    public function fromLabAppointment(Appointment $appointment, int $requestedBy): ?Visit
    {
        return DB::transaction(function () use ($appointment, $requestedBy) {
            $appointment = $this->lockScheduledAppointment($appointment);
            $tests = $appointment->labTests()->where('is_active', true)->get();

            if ($tests->isEmpty()) {
                return null;
            }

            $visit = $appointment->patient->visits()->create([
                'appointment_id' => $appointment->id,
                'type' => 'lab_only',
                'status' => VisitStatus::LabOnly,
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
    }

    public function fromDoctorAppointment(
        Appointment $appointment,
        int $consultationTypeId
    ): ?Visit {
        return DB::transaction(function () use ($appointment, $consultationTypeId) {
            $appointment = $this->lockScheduledAppointment($appointment);
            $lockedPatient = Patient::query()->lockForUpdate()->findOrFail($appointment->patient_id);

            if ($lockedPatient->currentVisit()) {
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
                'department_id' => $departmentId,
                'consultation_type_id' => $consultationType->id,
                'complaint' => $appointment->reason,
            ]);

            $appointment->update(['status' => AppointmentStatus::CheckedIn]);
            $this->billing->invoiceFor($visit);

            return $visit->load(['consultationType', 'patient']);
        });
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

        return $appointment;
    }
}
