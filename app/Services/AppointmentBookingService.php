<?php

namespace App\Services;

use App\Enums\AppointmentStatus;
use App\Enums\Role;
use App\Models\Appointment;
use App\Models\ConsultationType;
use App\Models\Department;
use App\Models\DoctorAvailability;
use App\Models\LabTest;
use App\Models\Patient;
use App\Models\User;
use App\Notifications\HospitalActionNotification;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AppointmentBookingService
{
    public function doctorsForDepartment(?int $departmentId = null): Collection
    {
        return User::ofRole(Role::Doctor)
            ->where('is_active', true)
            ->when($departmentId, fn ($query) => $query->where('department_id', $departmentId))
            ->with('department:id,name')
            ->orderBy('name')
            ->get(['id', 'name', 'department_id']);
    }

    public function appointmentDetails(Appointment $appointment): Appointment
    {
        return $appointment->load([
            'patient', 'doctor.department', 'labTests',
            'reassignments.fromDoctor', 'reassignments.toDoctor', 'reassignments.reassignedBy',
        ]);
    }

    public function indexData(Carbon $date, User $user, ?int $doctorId = null, ?string $hospitalNumber = null, ?int $departmentId = null): array
    {
        $appointments = Appointment::with([
            'patient', 'doctor.department', 'labTests',
            'reassignments.fromDoctor', 'reassignments.toDoctor', 'reassignments.reassignedBy',
        ])
            ->whereDate('scheduled_at', $date)
            ->when($user->hasRole(Role::Doctor), fn ($q) => $q->where('doctor_id', $user->id))
            ->when($doctorId && ! $user->hasRole(Role::Doctor), fn ($q) => $q->where('doctor_id', $doctorId))
            ->when($departmentId, fn ($q) => $q->whereHas('doctor', fn ($doctorQuery) => $doctorQuery->where('department_id', $departmentId)))
            ->when(filled($hospitalNumber), fn ($q) => $q->whereHas('patient', fn ($patientQuery) => $patientQuery->whereLike('hospital_number', '%'.trim($hospitalNumber).'%')))
            ->orderBy('scheduled_at')->get();

        $activeDoctors = User::ofRole(Role::Doctor)->where('is_active', true)->with('department:id,name')->orderBy('name')->get(['id', 'name', 'department_id']);
        $availableDoctorIds = DoctorAvailability::query()
            ->whereIn('doctor_id', $activeDoctors->pluck('id'))
            ->whereDate('date', $date->toDateString())
            ->pluck('doctor_id')->all();
        $reassignmentDoctors = $appointments->mapWithKeys(function (Appointment $appointment) use ($activeDoctors, $availableDoctorIds, $date) {
            $departmentId = $appointment->doctor?->department_id;
            $isReassignable = $appointment->status === AppointmentStatus::Scheduled
                && ! $appointment->isLab()
                && $date->toDateString() >= today()->toDateString();

            return [$appointment->id => $isReassignable && $departmentId
                ? $activeDoctors->filter(fn (User $doctor) => $doctor->id !== $appointment->doctor_id
                    && (int) $doctor->department_id === (int) $departmentId
                    && in_array($doctor->id, $availableDoctorIds, true))->values()
                : collect()];
        });

        return [
            'appointments' => $appointments,
            'reassignmentDoctors' => $reassignmentDoctors,
            'consultationTypesByDepartment' => ConsultationType::active()->orderBy('name')->get()->groupBy('department_id'),
            'appointmentDoctors' => $user->hasRole(Role::Admin, Role::Receptionist)
                ? User::ofRole(Role::Doctor)->orderBy('name')->get(['id', 'name'])
                : collect(),
            'appointmentDepartments' => $user->hasRole(Role::Admin, Role::Receptionist)
                ? Department::orderBy('name')->get(['id', 'name'])
                : collect(),
        ];
    }

    public function reassignDoctor(Appointment $appointment, int $doctorId, User $actor, string $reason): Appointment
    {
        return DB::transaction(function () use ($appointment, $doctorId, $actor, $reason) {
            $appointment = Appointment::query()->lockForUpdate()->findOrFail($appointment->id);
            $appointment->load('doctor');

            if ($appointment->status !== AppointmentStatus::Scheduled || $appointment->isLab() || $appointment->scheduled_at->lt(today())) {
                throw ValidationException::withMessages(['appointment' => 'Only upcoming scheduled doctor appointments can be reassigned.']);
            }

            $newDoctor = User::ofRole(Role::Doctor)->where('is_active', true)->find($doctorId);

            if (! $newDoctor) {
                throw ValidationException::withMessages(['doctor_id' => 'Choose an active doctor.']);
            }

            if (! $appointment->doctor || (int) $appointment->doctor->department_id !== (int) $newDoctor->department_id) {
                throw ValidationException::withMessages(['doctor_id' => 'Choose an active doctor in the same department.']);
            }

            if ($appointment->doctor_id === $newDoctor->id) {
                throw ValidationException::withMessages(['doctor_id' => 'Choose a different doctor from the current assignment.']);
            }

            if (! DoctorAvailability::where('doctor_id', $newDoctor->id)->whereDate('date', $appointment->scheduled_at->toDateString())->exists()) {
                throw ValidationException::withMessages(['doctor_id' => 'That doctor is not available on the appointment date.']);
            }

            $alreadyBooked = Appointment::query()
                ->where('patient_id', $appointment->patient_id)
                ->where('doctor_id', $newDoctor->id)
                ->whereDate('scheduled_at', $appointment->scheduled_at->toDateString())
                ->where('status', '!=', AppointmentStatus::Cancelled->value)
                ->where('id', '!=', $appointment->id)
                ->exists();

            if ($alreadyBooked) {
                throw ValidationException::withMessages(['doctor_id' => 'This patient already has an appointment with that doctor on this date.']);
            }

            $oldDoctor = $appointment->doctor;
            $appointment->update(['doctor_id' => $newDoctor->id]);
            $appointment->reassignments()->create([
                'from_doctor_id' => $oldDoctor->id,
                'to_doctor_id' => $newDoctor->id,
                'reassigned_by' => $actor->id,
                'reason' => $reason,
            ]);

            $newDoctor->notify(new HospitalActionNotification(
                'Appointment assigned to you',
                'A patient appointment was assigned to you for '.$appointment->scheduled_at->format('D, d M Y').'.',
                route('appointments.show', $appointment),
                'calendar',
            ));
            $oldDoctor->notify(new HospitalActionNotification(
                'Appointment reassigned',
                'An appointment on '.$appointment->scheduled_at->format('D, d M Y').' was reassigned to another doctor.',
                route('appointments.show', $appointment),
                'calendar',
            ));
            $appointment->patient?->user?->notify(new HospitalActionNotification(
                'Appointment doctor changed',
                'Your appointment on '.$appointment->scheduled_at->format('D, d M Y').' has a new doctor.',
                route('portal.appointments.index'),
                'calendar',
            ));

            return $appointment->refresh()->load([
                'patient', 'doctor.department', 'labTests',
                'reassignments.fromDoctor', 'reassignments.toDoctor', 'reassignments.reassignedBy',
            ]);
        });
    }

    public function formData(array $query): array
    {
        $doctors = $this->doctorsForDepartment();
        $availabilityByDoctor = DoctorAvailability::query()
            ->whereIn('doctor_id', $doctors->pluck('id'))
            ->whereBetween('date', [today()->toDateString(), today()->addDays(config('hms.booking.max_days_ahead'))->toDateString()])
            ->orderBy('date')->get(['doctor_id', 'date'])
            ->groupBy('doctor_id')
            ->map(fn ($dates) => $dates->map(fn ($availability) => $availability->date->toDateString())->values());

        return [
            'patients' => Patient::orderBy('last_name')->limit(500)->get(['id', 'hospital_number', 'first_name', 'last_name']),
            'doctors' => $doctors,
            'departments' => Department::whereHas('users', fn ($query) => $query->where('role', Role::Doctor->value)->where('is_active', true))->orderBy('name')->get(),
            'availabilityByDoctor' => $availabilityByDoctor,
            'selectedDepartment' => $query['department_id'] ?? null,
            'selected' => $query['patient'] ?? null,
        ];
    }

    public function portalIndexData(Patient $patient): array
    {
        return [
            'upcoming' => $patient->appointments()->with(['doctor', 'labTests'])
                ->where('status', AppointmentStatus::Scheduled->value)
                ->where(fn ($query) => $query
                    ->where(fn ($q) => $q->where('type', 'consultation')->whereDate('scheduled_at', '>=', today()))
                    ->orWhere(fn ($q) => $q->where('type', 'lab')->where('scheduled_at', '>=', now())))
                ->orderBy('scheduled_at')->get(),
            'past' => $patient->appointments()->with(['doctor', 'labTests'])
                ->where(fn ($query) => $query->where('status', '!=', AppointmentStatus::Scheduled->value)
                    ->orWhere(fn ($dateQuery) => $dateQuery->where('type', 'consultation')->whereDate('scheduled_at', '<', today()))
                    ->orWhere(fn ($dateQuery) => $dateQuery->where('type', 'lab')->where('scheduled_at', '<', now())))
                ->latest('scheduled_at')->limit(15)->get(),
        ];
    }

    public function portalAppointments(Patient $patient)
    {
        return $patient->appointments()
            ->with(['doctor.department', 'labTests'])
            ->latest('scheduled_at')
            ->paginate(10);
    }

    public function portalFormData(array $query): array
    {
        $type = ($query['type'] ?? null) === 'lab' ? 'lab' : 'consultation';
        $date = filled($query['date'] ?? null) ? Carbon::parse($query['date'])->startOfDay() : null;
        $departmentId = (int) ($query['department_id'] ?? 0) ?: null;
        $doctorId = (int) ($query['doctor_id'] ?? 0) ?: null;
        $doctors = User::ofRole(Role::Doctor)->where('is_active', true)->with('department')->orderBy('name')->get();
        $departments = Department::whereHas('users', fn ($builder) => $builder->where('role', Role::Doctor->value)->where('is_active', true))
            ->orderBy('name')->get();
        $availability = DoctorAvailability::query()->whereIn('doctor_id', $doctors->pluck('id'))
            ->whereBetween('date', [today()->toDateString(), today()->addDays(config('hms.booking.max_days_ahead'))->toDateString()])
            ->orderBy('date')->get(['doctor_id', 'date'])->groupBy('doctor_id')
            ->map(fn ($dates) => $dates->map(fn ($item) => $item->date->toDateString())->values());

        return [
            'type' => $type,
            'date' => $date,
            'departmentId' => $departmentId,
            'doctorId' => $doctorId,
            'slots' => $date && $type === 'lab' ? $this->availableSlots($type, $date, $doctorId) : null,
            'doctors' => $doctors,
            'departments' => $departments,
            'availabilityByDoctor' => $availability,
            'tests' => $this->activeLabTests(),
            'maxDate' => now()->addDays(config('hms.booking.max_days_ahead'))->toDateString(),
        ];
    }

    public function activeLabTests(): Collection
    {
        return LabTest::active()->orderBy('name')->get();
    }

    /** @return array{tests: Collection, slots: Collection<int, Carbon>} */
    public function labBookingOptions(Carbon $date): array
    {
        return [
            'tests' => $this->activeLabTests(),
            'slots' => $this->availableSlots('lab', $date->copy()->startOfDay(), null),
        ];
    }

    public function bookForStaff(array $data, int $createdBy): Appointment
    {
        $patient = Patient::where('hospital_number', $data['hospital_number'])->firstOrFail();
        $when = Carbon::parse($data['appointment_date'])->startOfDay();

        if (! DoctorAvailability::where('doctor_id', $data['doctor_id'])->whereDate('date', $when)->exists()) {
            throw ValidationException::withMessages(['appointment_date' => 'That doctor is not available on the selected date.']);
        }

        if (Appointment::where('patient_id', $patient->id)->where('doctor_id', $data['doctor_id'])
            ->whereDate('scheduled_at', $when->toDateString())->where('status', '!=', AppointmentStatus::Cancelled->value)->exists()) {
            throw ValidationException::withMessages(['appointment_date' => 'This patient already has an appointment with that doctor on this date.']);
        }

        $appointment = $patient->appointments()->create([
            'doctor_id' => $data['doctor_id'],
            'type' => 'consultation',
            'scheduled_at' => $when,
            'status' => AppointmentStatus::Scheduled,
            'reason' => $data['reason'] ?? null,
            'created_by' => $createdBy,
        ]);

        $this->notifyNewAppointment($appointment);

        return $appointment;
    }

    public function cancel(Appointment $appointment): void
    {
        abort_unless($appointment->status === AppointmentStatus::Scheduled, 422);
        $appointment->update(['status' => AppointmentStatus::Cancelled]);
        $this->notifyAppointmentCancelled($appointment);
    }

    public function cancelForPatient(Patient $patient, Appointment $appointment): bool
    {
        abort_unless($appointment->patient_id === $patient->id, 404);

        $isPast = $appointment->isLab()
            ? $appointment->scheduled_at->isPast()
            : $appointment->scheduled_at->lt(today());

        if ($appointment->status !== AppointmentStatus::Scheduled || $isPast) {
            return false;
        }

        $appointment->update(['status' => AppointmentStatus::Cancelled]);
        $this->notifyAppointmentCancelled($appointment);

        return true;
    }

    /** @return array{appointment: Appointment|null, type: string, when: Carbon, error: string|null, field: string} */
    public function bookForPatient(Patient $patient, array $data, int $createdBy): array
    {
        $type = $data['type'];
        $when = $type === 'consultation'
            ? Carbon::parse($data['appointment_date'])->startOfDay()
            : Carbon::createFromFormat('Y-m-d H:i', $data['slot']);
        $field = $type === 'consultation' ? 'appointment_date' : 'slot';

        if ($type === 'consultation') {
            $free = DoctorAvailability::where('doctor_id', $data['doctor_id'])->whereDate('date', $when->toDateString())->exists();
            if (! $free) {
                return compact('type', 'when', 'field') + ['appointment' => null, 'error' => 'That doctor is not available on the selected date. Please choose another date.'];
            }

            $duplicate = $patient->appointments()->where('status', AppointmentStatus::Scheduled->value)
                ->where('doctor_id', $data['doctor_id'])->whereDate('scheduled_at', $when->toDateString())->exists();
            if ($duplicate) {
                return compact('type', 'when', 'field') + ['appointment' => null, 'error' => 'You already have an appointment with this doctor on that date.'];
            }
        } else {
            $free = $this->availableSlots('lab', $when->copy()->startOfDay(), null)->contains(fn (Carbon $slot) => $slot->equalTo($when));
            if (! $free) {
                return compact('type', 'when', 'field') + ['appointment' => null, 'error' => 'That time is no longer available. Please pick another.'];
            }

            if ($patient->appointments()->where('status', AppointmentStatus::Scheduled->value)->where('scheduled_at', $when)->exists()) {
                return compact('type', 'when', 'field') + ['appointment' => null, 'error' => 'You already have an appointment at that time.'];
            }
        }

        $upcoming = $patient->appointments()->where('status', AppointmentStatus::Scheduled->value)
            ->where(fn ($query) => $query
                ->where(fn ($q) => $q->where('type', 'consultation')->whereDate('scheduled_at', '>=', today()))
                ->orWhere(fn ($q) => $q->where('type', 'lab')->where('scheduled_at', '>=', now())))
            ->count();

        if ($upcoming >= config('hms.booking.max_upcoming')) {
            return compact('type', 'when', 'field') + [
                'appointment' => null,
                'error' => 'You already have '.config('hms.booking.max_upcoming').' upcoming appointments. Cancel one or attend before booking more.',
            ];
        }

        $appointment = DB::transaction(function () use ($patient, $data, $type, $when, $createdBy) {
            $appointment = $patient->appointments()->create([
                'doctor_id' => $type === 'consultation' ? $data['doctor_id'] : null,
                'type' => $type,
                'scheduled_at' => $when,
                'status' => AppointmentStatus::Scheduled,
                'reason' => $data['reason'] ?? null,
                'created_by' => $createdBy,
            ]);

            if ($type === 'lab') {
                $appointment->labTests()->sync(array_unique($data['lab_test_ids']));
            }

            return $appointment;
        });

        $this->notifyNewAppointment($appointment);

        return compact('appointment', 'type', 'when', 'field') + ['error' => null];
    }

    private function notifyNewAppointment(Appointment $appointment): void
    {
        $appointment->loadMissing(['patient.user', 'doctor']);
        $when = $appointment->scheduled_at->format('D, d M Y'.($appointment->isLab() ? ', g:i A' : ''));

        $appointment->doctor?->notify(new HospitalActionNotification(
            'New appointment scheduled',
            'A patient appointment was scheduled for '.$when.'.',
            route('appointments.show', $appointment),
            'calendar',
        ));

        $appointment->patient?->user?->notify(new HospitalActionNotification(
            'Appointment scheduled',
            'Your appointment is scheduled for '.$when.'.',
            route('portal.appointments.index'),
            'calendar',
        ));
    }

    private function notifyAppointmentCancelled(Appointment $appointment): void
    {
        $appointment->loadMissing(['patient.user', 'doctor']);
        $when = $appointment->scheduled_at->format('D, d M Y'.($appointment->isLab() ? ', g:i A' : ''));

        $appointment->doctor?->notify(new HospitalActionNotification(
            'Appointment cancelled',
            'An appointment scheduled for '.$when.' was cancelled.',
            route('appointments.index'),
            'calendar',
        ));

        $appointment->patient?->user?->notify(new HospitalActionNotification(
            'Appointment cancelled',
            'Your appointment scheduled for '.$when.' was cancelled.',
            route('portal.appointments.index'),
            'calendar',
        ));
    }

    /** @return Collection<int, Carbon> */
    public function availableSlots(string $type, Carbon $date, ?int $doctorId): Collection
    {
        $cfg = config('hms.booking');

        if ($type === 'consultation' && (! $doctorId || ! DoctorAvailability::where('doctor_id', $doctorId)->whereDate('date', $date->toDateString())->exists())) {
            return collect();
        }

        if ($type === 'lab' && ! in_array($date->dayOfWeek, $cfg['days'], true)) {
            return collect();
        }

        [$startHour, $startMinute] = array_map('intval', explode(':', $cfg['start']));
        [$endHour, $endMinute] = array_map('intval', explode(':', $cfg['end']));
        $cursor = $date->copy()->setTime($startHour, $startMinute);
        $end = $date->copy()->setTime($endHour, $endMinute);
        $slots = collect();

        while ($cursor < $end) {
            $slots->push($cursor->copy());
            $cursor->addMinutes($cfg['interval']);
        }

        $slots = $slots->filter(fn (Carbon $slot) => $slot->gte(now()->addMinutes($cfg['lead_minutes'])));
        $taken = Appointment::query()->whereDate('scheduled_at', $date->toDateString())
            ->where('status', '!=', AppointmentStatus::Cancelled->value)
            ->when($type === 'lab', fn ($q) => $q->where('type', 'lab'), fn ($q) => $q->where('type', 'consultation')->where('doctor_id', $doctorId))
            ->pluck('scheduled_at')->countBy(fn ($date) => $date->format('H:i'));
        $capacity = $type === 'lab' ? $cfg['lab_capacity'] : 1;

        return $slots->filter(fn (Carbon $slot) => ($taken[$slot->format('H:i')] ?? 0) < $capacity)->values();
    }
}
