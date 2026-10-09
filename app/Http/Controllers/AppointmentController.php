<?php

namespace App\Http\Controllers;

use App\Enums\AppointmentStatus;
use App\Enums\Role;
use App\Models\Appointment;
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

        $appointments = Appointment::with(['patient', 'doctor'])
            ->whereDate('scheduled_at', $date)
            ->when($user->hasRole(Role::Doctor), fn ($q) => $q->where('doctor_id', $user->id))
            ->orderBy('scheduled_at')
            ->get();

        return view('appointments.index', compact('appointments', 'date'));
    }

    public function create(Request $request)
    {
        return view('appointments.create', [
            'patients' => Patient::orderBy('last_name')->limit(500)
                ->get(['id', 'hospital_number', 'first_name', 'last_name']),
            'doctors'  => User::ofRole(Role::Doctor)->orderBy('name')->get(),
            'selected' => $request->query('patient'), // hospital number
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'hospital_number' => ['required', 'exists:patients,hospital_number'],
            'doctor_id'       => ['required', Rule::exists('users', 'id')->where('role', Role::Doctor->value)],
            'scheduled_at'    => ['required', 'date', 'after_or_equal:today'],
            'reason'          => ['nullable', 'string', 'max:255'],
        ]);

        $patient = Patient::where('hospital_number', $data['hospital_number'])->firstOrFail();
        $when    = Carbon::parse($data['scheduled_at']);

        $clash = Appointment::where('doctor_id', $data['doctor_id'])
            ->where('scheduled_at', $when)
            ->where('status', '!=', AppointmentStatus::Cancelled->value)
            ->exists();

        if ($clash) {
            return back()->withInput()->withErrors([
                'scheduled_at' => 'That doctor already has an appointment at this time.',
            ]);
        }

        Appointment::create([
            'patient_id'   => $patient->id,
            'doctor_id'    => $data['doctor_id'],
            'scheduled_at' => $when,
            'reason'       => $data['reason'] ?? null,
            'created_by'   => $request->user()->id,
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