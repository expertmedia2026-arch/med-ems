<?php

namespace App\Http\Controllers;

use App\Http\Requests\PortalAppointmentFiltersRequest;
use App\Http\Requests\PortalAppointmentRequest;
use App\Models\Appointment;
use App\Services\AppointmentBookingService;
use Illuminate\Http\Request;

class PortalAppointmentController extends Controller
{
    public function index(Request $request, AppointmentBookingService $booking)
    {
        $patient = $this->patient($request);

        return view('portal.appointments.index', $booking->portalIndexData($patient));
    }

    public function create(PortalAppointmentFiltersRequest $request, AppointmentBookingService $booking)
    {
        $this->patient($request);

        return view('portal.appointments.create', $booking->portalFormData($request->validated()));
    }

    public function store(PortalAppointmentRequest $request, AppointmentBookingService $booking)
    {
        $patient = $this->patient($request);
        $data = $request->validated();
        $result = $booking->bookForPatient($patient, $data, $request->user()->id);
        $type = $result['type'];
        $when = $result['when'];

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

        if ($result['error']) {
            return $back($result['error']);
        }

        $success = $type === 'consultation'
            ? 'Appointment booked for '.$when->format('l, d M Y').'. Please arrive early; patients are seen in the order they arrive.'
            : 'Appointment booked for '.$when->format('l, d M Y').' at '.$when->format('g:i A').'. Please arrive a few minutes early and check in at the front desk.';

        return redirect()
            ->route('portal.appointments.index')
            ->with('success', $success);
    }

    public function cancel(Request $request, Appointment $appointment, AppointmentBookingService $booking)
    {
        $patient = $this->patient($request);

        if (! $booking->cancelForPatient($patient, $appointment)) {
            return back()->with('error', 'This appointment can no longer be cancelled.');
        }

        return back()->with('success', 'Appointment cancelled.');
    }

    private function patient(Request $request)
    {
        $patient = $request->user()->patient;
        abort_unless($patient, 403);

        return $patient;
    }
}
