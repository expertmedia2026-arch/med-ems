<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreDoctorAvailabilityRequest;
use App\Models\DoctorAvailability;
use App\Services\DoctorAvailabilityService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Presents and updates the signed-in doctor's bookable dates through DoctorAvailabilityService.
 */
class DoctorAvailabilityController extends Controller
{
    /**
     * Retrieves the current doctor's availability and renders the scheduling page.
     */
    public function index(Request $request, DoctorAvailabilityService $availability): View
    {
        $availabilities = $availability->forDoctor($request->user());

        return view('doctor.availability', [
            'availabilities' => $availabilities,
            'maxDate' => today()->addDays(config('hms.booking.max_days_ahead'))->toDateString(),
        ]);
    }

    /**
     * Saves a validated availability date for the current doctor and redirects to the schedule.
     */
    public function store(StoreDoctorAvailabilityRequest $request, DoctorAvailabilityService $availability): RedirectResponse
    {
        $availability->add($request->user(), $request->validated('date'));

        return redirect()->route('doctor.availability.index')->with('success', 'Availability date saved.');
    }

    /**
     * Removes the selected availability date for the current doctor and redirects to the schedule.
     */
    public function destroy(Request $request, DoctorAvailability $doctorAvailability, DoctorAvailabilityService $availability): RedirectResponse
    {
        $availability->remove($request->user(), $doctorAvailability);

        return redirect()->route('doctor.availability.index')->with('success', 'Availability date removed.');
    }
}
