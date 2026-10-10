<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreDoctorAvailabilityRequest;
use App\Models\DoctorAvailability;
use App\Services\DoctorAvailabilityService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class DoctorAvailabilityController extends Controller
{
    public function index(Request $request, DoctorAvailabilityService $availability): View
    {
        $availabilities = $availability->forDoctor($request->user());

        return view('doctor.availability', [
            'availabilities' => $availabilities,
            'maxDate' => today()->addDays(config('hms.booking.max_days_ahead'))->toDateString(),
        ]);
    }

    public function store(StoreDoctorAvailabilityRequest $request, DoctorAvailabilityService $availability): RedirectResponse
    {
        $availability->add($request->user(), $request->validated('date'));

        return redirect()->route('doctor.availability.index')->with('success', 'Availability date saved.');
    }

    public function destroy(Request $request, DoctorAvailability $doctorAvailability, DoctorAvailabilityService $availability): RedirectResponse
    {
        $availability->remove($request->user(), $doctorAvailability);

        return redirect()->route('doctor.availability.index')->with('success', 'Availability date removed.');
    }
}
