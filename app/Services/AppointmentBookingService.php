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
        return $appointment->load(['patient', 'doctor.department', 'labTests']);
    }

    public function indexData(Carbon $date, User $user): array
    {
        return [
            'appointments' => Appointment::with(['patient', 'doctor.department', 'labTests'])
                ->whereDate('scheduled_at', $date)
                ->when($user->hasRole(Role::Doctor), fn ($q) => $q->where('doctor_id', $user->id))
                ->orderBy('scheduled_at')->get(),
            'consultationTypesByDepartment' => ConsultationType::active()->orderBy('name')->get()->groupBy('department_id'),
        ];
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

        return $patient->appointments()->create([
            'doctor_id' => $data['doctor_id'],
            'type' => 'consultation',
            'scheduled_at' => $when,
            'reason' => $data['reason'] ?? null,
            'created_by' => $createdBy,
        ]);
    }

    public function cancel(Appointment $appointment): void
    {
        abort_unless($appointment->status === AppointmentStatus::Scheduled, 422);
        $appointment->update(['status' => AppointmentStatus::Cancelled]);
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
                'reason' => $data['reason'] ?? null,
                'created_by' => $createdBy,
            ]);

            if ($type === 'lab') {
                $appointment->labTests()->sync(array_unique($data['lab_test_ids']));
            }

            return $appointment;
        });

        return compact('appointment', 'type', 'when', 'field') + ['error' => null];
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
