<?php

namespace App\Http\Controllers;

use App\Enums\AppointmentStatus;
use App\Enums\Role;
use App\Models\Appointment;
use App\Models\Department;
use App\Models\DoctorAvailability;
use App\Models\LabTest;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class PortalAppointmentController extends Controller
{
    public function index(Request $request)
    {
        $patient = $this->patient($request);

        $upcoming = $patient->appointments()
            ->with(['doctor', 'labTests'])
            ->where('status', AppointmentStatus::Scheduled->value)
            ->where(fn ($query) => $query
                ->where(fn ($q) => $q->where('type', 'consultation')->whereDate('scheduled_at', '>=', today()))
                ->orWhere(fn ($q) => $q->where('type', 'lab')->where('scheduled_at', '>=', now())))
            ->orderBy('scheduled_at')
            ->get();

        $past = $patient->appointments()
            ->with(['doctor', 'labTests'])
            ->where(fn ($q) => $q->where('status', '!=', AppointmentStatus::Scheduled->value)
                ->orWhere(fn ($dateQuery) => $dateQuery
                    ->where('type', 'consultation')->whereDate('scheduled_at', '<', today()))
                ->orWhere(fn ($dateQuery) => $dateQuery
                    ->where('type', 'lab')->where('scheduled_at', '<', now())))
            ->latest('scheduled_at')
            ->limit(15)
            ->get();

        return view('portal.appointments.index', compact('upcoming', 'past'));
    }

    public function create(Request $request)
    {
        $this->patient($request);

        $request->validate([
            'type' => ['nullable', 'in:consultation,lab'],
            'department_id' => ['nullable', 'integer'],
            'doctor_id' => ['nullable', 'integer'],
            'date' => [
                'nullable', 'date',
                'after_or_equal:today',
                'before_or_equal:'.now()->addDays(config('hms.booking.max_days_ahead'))->toDateString(),
            ],
        ]);

        $type = $request->query('type') === 'lab' ? 'lab' : 'consultation';
        $date = $request->filled('date') ? Carbon::parse($request->query('date'))->startOfDay() : null;
        $departmentId = $request->integer('department_id') ?: null;
        $doctorId = $request->integer('doctor_id') ?: null;
        $doctors = User::ofRole(Role::Doctor)->where('is_active', true)->with('department')->orderBy('name')->get();
        $departments = Department::whereHas('users', fn ($query) => $query
            ->where('role', Role::Doctor->value)
            ->where('is_active', true))
            ->orderBy('name')
            ->get();
        $availabilityByDoctor = DoctorAvailability::query()
            ->whereIn('doctor_id', $doctors->pluck('id'))
            ->whereBetween('date', [today()->toDateString(), today()->addDays(config('hms.booking.max_days_ahead'))->toDateString()])
            ->orderBy('date')
            ->get(['doctor_id', 'date'])
            ->groupBy('doctor_id')
            ->map(fn ($dates) => $dates->map(fn ($availability) => $availability->date->toDateString())->values());

        $slots = null;
        if ($date && $type === 'lab') {
            $slots = $this->availableSlots($type, $date, $doctorId);
        }

        return view('portal.appointments.create', [
            'type' => $type,
            'date' => $date,
            'departmentId' => $departmentId,
            'doctorId' => $doctorId,
            'slots' => $slots,
            'doctors' => $doctors,
            'departments' => $departments,
            'availabilityByDoctor' => $availabilityByDoctor,
            'tests' => LabTest::active()->orderBy('name')->get(),
            'maxDate' => now()->addDays(config('hms.booking.max_days_ahead'))->toDateString(),
        ]);
    }

    public function store(Request $request)
    {
        $patient = $this->patient($request);

        $data = $request->validate([
            'type' => ['required', 'in:consultation,lab'],
            'department_id' => ['required_if:type,consultation', 'nullable', 'integer', 'exists:departments,id'],
            'doctor_id' => [
                'required_if:type,consultation', 'nullable',
                Rule::exists('users', 'id')->where('role', Role::Doctor->value)->where('is_active', true)
                    ->where('department_id', $request->input('department_id')),
            ],
            'appointment_date' => [
                'required_if:type,consultation', 'nullable', 'date', 'after_or_equal:today',
                'before_or_equal:'.today()->addDays(config('hms.booking.max_days_ahead'))->toDateString(),
            ],
            'slot' => ['required_if:type,lab', 'nullable', 'date_format:Y-m-d H:i'],
            'reason' => ['nullable', 'string', 'max:255'],
            'lab_test_ids' => ['required_if:type,lab', 'nullable', 'array'],
            'lab_test_ids.*' => [Rule::exists('lab_tests', 'id')->where('is_active', true)],
        ], [
            'doctor_id.required_if' => 'Please choose a doctor.',
            'lab_test_ids.required_if' => 'Please select at least one test.',
            'appointment_date.required_if' => 'Please choose an available date.',
            'slot.required_if' => 'Please pick a time.',
        ]);

        $type = $data['type'];
        $when = $type === 'consultation'
            ? Carbon::parse($data['appointment_date'])->startOfDay()
            : Carbon::createFromFormat('Y-m-d H:i', $data['slot']);

        // Return to the selected date and choices if something is wrong.
        $back = fn (string $message) => redirect()
            ->route('portal.appointments.create', array_filter([
                'type' => $type,
                'department_id' => $data['department_id'] ?? null,
                'doctor_id' => $data['doctor_id'] ?? null,
                'date' => $when->toDateString(),
            ]))
            ->withInput()
            ->withErrors([($type === 'consultation' ? 'appointment_date' : 'slot') => $message]);

        if ($type === 'consultation') {
            $free = DoctorAvailability::where('doctor_id', $data['doctor_id'])
                ->whereDate('date', $when->toDateString())
                ->exists();

            if (! $free) {
                return $back('That doctor is not available on the selected date. Please choose another date.');
            }

            if ($patient->appointments()
                ->where('status', AppointmentStatus::Scheduled->value)
                ->where('doctor_id', $data['doctor_id'])
                ->whereDate('scheduled_at', $when->toDateString())
                ->exists()) {
                return $back('You already have an appointment with this doctor on that date.');
            }
        } else {
            $free = $this->availableSlots($type, $when->copy()->startOfDay(), null)
                ->contains(fn (Carbon $slot) => $slot->equalTo($when));

            if (! $free) {
                return $back('That time is no longer available. Please pick another.');
            }

            if ($patient->appointments()->where('status', AppointmentStatus::Scheduled->value)->where('scheduled_at', $when)->exists()) {
                return $back('You already have an appointment at that time.');
            }
        }

        $upcoming = $patient->appointments()
            ->where('status', AppointmentStatus::Scheduled->value)
            ->where(fn ($query) => $query
                ->where(fn ($q) => $q->where('type', 'consultation')->whereDate('scheduled_at', '>=', today()))
                ->orWhere(fn ($q) => $q->where('type', 'lab')->where('scheduled_at', '>=', now())))
            ->count();

        if ($upcoming >= config('hms.booking.max_upcoming')) {
            return $back('You already have '.config('hms.booking.max_upcoming').' upcoming appointments. Cancel one or attend before booking more.');
        }

        DB::transaction(function () use ($patient, $data, $type, $when, $request) {
            $appointment = Appointment::create([
                'patient_id' => $patient->id,
                'doctor_id' => $type === 'consultation' ? $data['doctor_id'] : null,
                'type' => $type,
                'scheduled_at' => $when,
                'reason' => $data['reason'] ?? null,
                'created_by' => $request->user()->id,
            ]);

            if ($type === 'lab') {
                $appointment->labTests()->sync(array_unique($data['lab_test_ids']));
            }
        });

        $success = $type === 'consultation'
            ? 'Appointment booked for '.$when->format('l, d M Y').'. Please arrive early; patients are seen in the order they arrive.'
            : 'Appointment booked for '.$when->format('l, d M Y').' at '.$when->format('g:i A').'. Please arrive a few minutes early and check in at the front desk.';

        return redirect()
            ->route('portal.appointments.index')
            ->with('success', $success);
    }

    public function cancel(Request $request, Appointment $appointment)
    {
        $patient = $this->patient($request);

        abort_unless($appointment->patient_id === $patient->id, 404);

        $isPast = $appointment->isLab()
            ? $appointment->scheduled_at->isPast()
            : $appointment->scheduled_at->lt(today());

        if ($appointment->status !== AppointmentStatus::Scheduled || $isPast) {
            return back()->with('error', 'This appointment can no longer be cancelled.');
        }

        $appointment->update(['status' => AppointmentStatus::Cancelled]);

        return back()->with('success', 'Appointment cancelled.');
    }

    private function patient(Request $request)
    {
        $patient = $request->user()->patient;
        abort_unless($patient, 403);

        return $patient;
    }

    /** Free times on a date: working hours minus the past, minus slots that are full. */
    private function availableSlots(string $type, Carbon $date, ?int $doctorId): Collection
    {
        $cfg = config('hms.booking');

        if ($type === 'consultation' && (! $doctorId || ! DoctorAvailability::where('doctor_id', $doctorId)->whereDate('date', $date->toDateString())->exists())) {
            return collect();
        }

        if ($type === 'lab' && ! in_array($date->dayOfWeek, $cfg['days'], true)) {
            return collect();
        }

        [$sh, $sm] = array_map('intval', explode(':', $cfg['start']));
        [$eh, $em] = array_map('intval', explode(':', $cfg['end']));

        $cursor = $date->copy()->setTime($sh, $sm);
        $end = $date->copy()->setTime($eh, $em);
        $slots = collect();

        while ($cursor < $end) {
            $slots->push($cursor->copy());
            $cursor->addMinutes($cfg['interval']);
        }

        $earliest = now()->addMinutes($cfg['lead_minutes']);
        $slots = $slots->filter(fn (Carbon $slot) => $slot->gte($earliest));

        // How many active bookings already sit on each slot
        $taken = Appointment::query()
            ->whereDate('scheduled_at', $date->toDateString())
            ->where('status', '!=', AppointmentStatus::Cancelled->value)
            ->when(
                $type === 'lab',
                fn ($q) => $q->where('type', 'lab'),
                fn ($q) => $q->where('type', 'consultation')->where('doctor_id', $doctorId)
            )
            ->pluck('scheduled_at')
            ->countBy(fn ($d) => $d->format('H:i'));

        $capacity = $type === 'lab' ? $cfg['lab_capacity'] : 1;

        return $slots->filter(fn (Carbon $slot) => ($taken[$slot->format('H:i')] ?? 0) < $capacity)->values();
    }
}
