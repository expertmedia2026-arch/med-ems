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

/**
 * Handles staff appointment screens and delegates booking operations to the appointment service.
 */
class AppointmentController extends Controller
{
    /**
     * Validates schedule filters, asks the booking service for the user's appointment queue, and renders the filtered schedule.
     */
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

    /**
     * Resolves the selected appointment through the booking service and renders its detailed staff view.
     */
    public function show(Appointment $appointment, AppointmentBookingService $appointments)
    {
        $appointment = $appointments->appointmentDetails($appointment);

        return view('appointments.show', compact('appointment'));
    }

    /**
     * Validates optional appointment-form context and renders the booking form with service-provided choices.
     */
    public function create(AppointmentFormRequest $request, AppointmentBookingService $appointments)
    {
        return view('appointments.create', $appointments->formData($request->validated()));
    }

    /**
     * Books an appointment from validated form data for the signed-in staff member and redirects to its schedule date.
     */
    public function store(StoreAppointmentRequest $request, AppointmentBookingService $appointments)
    {
        $data = $request->validated();
        $appointment = $appointments->bookForStaff($data, $request->user()->id);
        $when = $appointment->scheduled_at;

        return redirect()
            ->route('appointments.index', ['date' => $when->toDateString()])
            ->with('success', "Appointment booked for {$appointment->patient->full_name}.");
    }

    /**
     * Validates a reassignment and reason, delegates authorization and reassignment to the service, then returns to the prior page.
     */
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

    /**
     * Cancels the route-bound appointment through the booking service and redirects back with a status message.
     */
    public function cancel(Appointment $appointment, AppointmentBookingService $appointments)
    {
        $appointments->cancel($appointment);

        return back()->with('success', 'Appointment cancelled.');
    }
}
