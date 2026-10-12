<?php

namespace App\Http\Controllers;

use App\Http\Requests\AppointmentFormRequest;
use App\Http\Requests\AppointmentIndexRequest;
use App\Http\Requests\ReassignAppointmentRequest;
use App\Http\Requests\StoreAppointmentRequest;
use App\Models\Appointment;
use App\Services\AppointmentBookingService;
use Illuminate\Support\Carbon;
use Illuminate\Http\RedirectResponse;

class AppointmentController extends Controller
{
    public function index(AppointmentIndexRequest $request, AppointmentBookingService $appointments)
    {
        $date = Carbon::parse($request->validated('date') ?? today()->toDateString());
        $filters = $request->validated();
        $data = $appointments->indexData(
            $date,
            $request->user(),
            $filters['doctor_id'] ?? null,
            $filters['hospital_number'] ?? null,
            $filters['department_id'] ?? null,
        );

        return view('appointments.index', $data + [
            'date' => $date,
            'departmentId' => $filters['department_id'] ?? null,
            'doctorId' => $filters['doctor_id'] ?? null,
            'hospitalNumber' => $filters['hospital_number'] ?? '',
        ]);
    }

    public function show(Appointment $appointment, AppointmentBookingService $appointments)
    {
        $appointment = $appointments->appointmentDetails($appointment);

        return view('appointments.show', compact('appointment'));
    }

    public function create(AppointmentFormRequest $request, AppointmentBookingService $appointments)
    {
        return view('appointments.create', $appointments->formData($request->validated()));
    }

    public function store(StoreAppointmentRequest $request, AppointmentBookingService $appointments)
    {
        $data = $request->validated();
        $appointment = $appointments->bookForStaff($data, $request->user()->id);
        $when = $appointment->scheduled_at;

        return redirect()
            ->route('appointments.index', ['date' => $when->toDateString()])
            ->with('success', "Appointment booked for {$appointment->patient->full_name}.");
    }

    public function reassignDoctor(ReassignAppointmentRequest $request, Appointment $appointment, AppointmentBookingService $appointments): RedirectResponse
    {
        $appointment = $appointments->reassignDoctor(
            $appointment,
            (int) $request->validated('doctor_id'),
            $request->user(),
            $request->validated('reason'),
        );

        return back()->with('success', "Appointment reassigned to {$appointment->doctor->name}.");
    }

    public function cancel(Appointment $appointment, AppointmentBookingService $appointments)
    {
        $appointments->cancel($appointment);

        return back()->with('success', 'Appointment cancelled.');
    }
}
