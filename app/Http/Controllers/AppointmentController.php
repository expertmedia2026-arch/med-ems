<?php

namespace App\Http\Controllers;

use App\Enums\AppointmentStatus;
use App\Enums\Role;
use App\Models\Appointment;
use App\Models\ConsultationType;
use App\Models\Department;
use App\Models\DoctorAvailability;
use App\Models\Patient;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;

class AppointmentController extends Controller
{
    public function index(Request $request)
    {
        $request->validate(['date' => ['nullable', 'date']]);

        $date = $request->filled('date') ? Carbon::parse($request->query('date')) : today();
        $user = $request->user();

        $appointments = Appointment::with(['patient', 'doctor.department', 'labTests'])
            ->whereDate('scheduled_at', $date)
            ->when($user->hasRole(Role::Doctor), fn ($q) => $q->where('doctor_id', $user->id))
            ->orderBy('scheduled_at')
            ->get();

        $consultationTypesByDepartment = ConsultationType::active()
            ->orderBy('name')
            ->get()
            ->groupBy('department_id');

        return view('appointments.index', compact('appointments', 'date', 'consultationTypesByDepartment'));
    }

    public function show(Appointment $appointment)
    {
        $appointment->load(['patient', 'doctor', 'labTests']);

        return view('appointments.show', compact('appointment'));
    }

    public function create(Request $request)
    {
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

        return view('appointments.create', [
            'patients' => Patient::orderBy('last_name')->limit(500)
                ->get(['id', 'hospital_number', 'first_name', 'last_name']),
            'doctors' => $doctors,
            'departments' => $departments,
            'availabilityByDoctor' => $availabilityByDoctor,
            'selectedDepartment' => $request->query('department_id'),
            'selected' => $request->query('patient'), // hospital number
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'hospital_number' => ['required', 'exists:patients,hospital_number'],
            'department_id' => ['required', 'integer', 'exists:departments,id'],
            'doctor_id' => [
                'required',
                Rule::exists('users', 'id')
                    ->where('role', Role::Doctor->value)
                    ->where('is_active', true)
                    ->where('department_id', $request->input('department_id')),
            ],
            'appointment_date' => [
                'required', 'date', 'after_or_equal:today',
                'before_or_equal:'.today()->addDays(config('hms.booking.max_days_ahead'))->toDateString(),
            ],
            'reason' => ['nullable', 'string', 'max:255'],
        ]);

        $patient = Patient::where('hospital_number', $data['hospital_number'])->firstOrFail();
        $availabilityExists = DoctorAvailability::where('doctor_id', $data['doctor_id'])
            ->whereDate('date', $data['appointment_date'])
            ->exists();

        if (! $availabilityExists) {
            return back()->withInput()->withErrors([
                'appointment_date' => 'That doctor is not available on the selected date.',
            ]);
        }

        $when = Carbon::parse($data['appointment_date'])->startOfDay();

        $duplicate = Appointment::where('patient_id', $patient->id)
            ->where('doctor_id', $data['doctor_id'])
            ->whereDate('scheduled_at', $when->toDateString())
            ->where('status', '!=', AppointmentStatus::Cancelled->value)
            ->exists();

        if ($duplicate) {
            return back()->withInput()->withErrors([
                'appointment_date' => 'This patient already has an appointment with that doctor on this date.',
            ]);
        }

        Appointment::create([
            'patient_id' => $patient->id,
            'doctor_id' => $data['doctor_id'],
            'type' => 'consultation',
            'scheduled_at' => $when,
            'reason' => $data['reason'] ?? null,
            'created_by' => $request->user()->id,
        ]);

        return redirect()
            ->route('appointments.index', ['date' => $when->toDateString()])
            ->with('success', "Appointment booked for {$patient->full_name}.");
    }

    public function cancel(Appointment $appointment)
    {
        abort_unless($appointment->status === AppointmentStatus::Scheduled, 422);

        $appointment->update(['status' => AppointmentStatus::Cancelled]);

        return back()->with('success', 'Appointment cancelled.');
    }
}
