<?php

namespace App\Http\Controllers;

use App\Http\Requests\AppointmentFormRequest;
use App\Http\Requests\AppointmentIndexRequest;
use App\Http\Requests\StoreAppointmentRequest;
use App\Models\Appointment;
use App\Services\AppointmentBookingService;
use Illuminate\Support\Carbon;

class AppointmentController extends Controller
{
    public function index(AppointmentIndexRequest $request, AppointmentBookingService $appointments)
    {
        $date = Carbon::parse($request->validated('date') ?? today()->toDateString());
        $data = $appointments->indexData($date, $request->user());

        return view('appointments.index', $data + compact('date'));
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

    public function cancel(Appointment $appointment, AppointmentBookingService $appointments)
    {
        $appointments->cancel($appointment);

        return back()->with('success', 'Appointment cancelled.');
    }
}
