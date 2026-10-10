<?php

namespace App\Http\Controllers;

use App\Enums\AppointmentStatus;
use App\Enums\Role;
use App\Models\Appointment;
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
            ->where('scheduled_at', '>=', now())
            ->orderBy('scheduled_at')
            ->get();

        $past = $patient->appointments()
            ->with(['doctor', 'labTests'])
            ->where(fn ($q) => $q->where('status', '!=', AppointmentStatus::Scheduled->value)
                                 ->orWhere('scheduled_at', '<', now()))
            ->latest('scheduled_at')
            ->limit(15)
            ->get();

        return view('portal.appointments.index', compact('upcoming', 'past'));
    }

    public function create(Request $request)
    {
        $this->patient($request);

        $request->validate([
            'type'      => ['nullable', 'in:consultation,lab'],
            'doctor_id' => ['nullable', 'integer'],
            'date'      => [
                'nullable', 'date',
                'after_or_equal:today',
                'before_or_equal:' . now()->addDays(config('hms.booking.max_days_ahead'))->toDateString(),
            ],
        ]);

        $type     = $request->query('type') === 'lab' ? 'lab' : 'consultation';
        $date     = $request->filled('date') ? Carbon::parse($request->query('date'))->startOfDay() : null;
        $doctorId = $request->integer('doctor_id') ?: null;

        $slots = null;
        if ($date && ($type === 'lab' || $doctorId)) {
            $slots = $this->availableSlots($type, $date, $doctorId);
        }

        return view('portal.appointments.create', [
            'type'     => $type,
            'date'     => $date,
            'doctorId' => $doctorId,
            'slots'    => $slots,
            'doctors'  => User::ofRole(Role::Doctor)->where('is_active', true)->orderBy('name')->get(),
            'tests'    => LabTest::active()->orderBy('name')->get(),
            'maxDate'  => now()->addDays(config('hms.booking.max_days_ahead'))->toDateString(),
        ]);
    }

    public function store(Request $request)
    {
        $patient = $this->patient($request);

        $data = $request->validate([
            'type'           => ['required', 'in:consultation,lab'],
            'doctor_id'      => ['required_if:type,consultation', 'nullable', Rule::exists('users', 'id')->where('role', Role::Doctor->value)->where('is_active', true)],
            'slot'           => ['required', 'date_format:Y-m-d H:i'],
            'reason'         => ['nullable', 'string', 'max:255'],
            'lab_test_ids'   => ['required_if:type,lab', 'nullable', 'array'],
            'lab_test_ids.*' => [Rule::exists('lab_tests', 'id')->where('is_active', true)],
        ], [
            'doctor_id.required_if'    => 'Please choose a doctor.',
            'lab_test_ids.required_if' => 'Please select at least one test.',
            'slot.required'            => 'Please pick a time.',
        ]);

        $type = $data['type'];
        $when = Carbon::createFromFormat('Y-m-d H:i', $data['slot']);

        // Back to the time-picker with the same choices if something is wrong
        $back = fn (string $message) => redirect()
            ->route('portal.appointments.create', array_filter([
                'type'      => $type,
                'doctor_id' => $data['doctor_id'] ?? null,
                'date'      => $when->toDateString(),
            ]))
            ->withInput()
            ->withErrors(['slot' => $message]);

        $free = $this->availableSlots($type, $when->copy()->startOfDay(), $data['doctor_id'] ?? null)
            ->contains(fn (Carbon $slot) => $slot->equalTo($when));

        if (! $free) {
            return $back('That time is no longer available. Please pick another.');
        }

        if ($patient->appointments()->where('status', AppointmentStatus::Scheduled->value)->where('scheduled_at', $when)->exists()) {
            return $back('You already have an appointment at that time.');
        }

        $upcoming = $patient->appointments()
            ->where('status', AppointmentStatus::Scheduled->value)
            ->where('scheduled_at', '>=', now())
            ->count();

        if ($upcoming >= config('hms.booking.max_upcoming')) {
            return $back('You already have ' . config('hms.booking.max_upcoming') . ' upcoming appointments. Cancel one or attend before booking more.');
        }

        DB::transaction(function () use ($patient, $data, $type, $when, $request) {
            $appointment = Appointment::create([
                'patient_id'   => $patient->id,
                'doctor_id'    => $type === 'consultation' ? $data['doctor_id'] : null,
                'type'         => $type,
                'scheduled_at' => $when,
                'reason'       => $data['reason'] ?? null,
                'created_by'   => $request->user()->id,
            ]);

            if ($type === 'lab') {
                $appointment->labTests()->sync(array_unique($data['lab_test_ids']));
            }
        });

        return redirect()
            ->route('portal.appointments.index')
            ->with('success', 'Appointment booked for ' . $when->format('l, d M Y \a\t g:i A') . '. Please arrive a few minutes early and check in at the front desk.');
    }

    public function cancel(Request $request, Appointment $appointment)
    {
        $patient = $this->patient($request);

        abort_unless($appointment->patient_id === $patient->id, 404);

        if ($appointment->status !== AppointmentStatus::Scheduled || $appointment->scheduled_at->isPast()) {
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

        if (! in_array($date->dayOfWeek, $cfg['days'], true)) {
            return collect();
        }

        [$sh, $sm] = array_map('intval', explode(':', $cfg['start']));
        [$eh, $em] = array_map('intval', explode(':', $cfg['end']));

        $cursor = $date->copy()->setTime($sh, $sm);
        $end    = $date->copy()->setTime($eh, $em);
        $slots  = collect();

        while ($cursor < $end) {
            $slots->push($cursor->copy());
            $cursor->addMinutes($cfg['interval']);
        }

        $earliest = now()->addMinutes($cfg['lead_minutes']);
        $slots    = $slots->filter(fn (Carbon $slot) => $slot->gte($earliest));

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