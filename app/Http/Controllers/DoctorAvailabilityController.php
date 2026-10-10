<?php

namespace App\Http\Controllers;

use App\Models\DoctorAvailability;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class DoctorAvailabilityController extends Controller
{
    public function index(Request $request): View
    {
        $availabilities = $request->user()->doctorAvailabilities()
            ->whereDate('date', '>=', today())
            ->orderBy('date')
            ->get();

        return view('doctor.availability', [
            'availabilities' => $availabilities,
            'maxDate' => today()->addDays(config('hms.booking.max_days_ahead'))->toDateString(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'date' => [
                'required',
                'date',
                'after_or_equal:today',
                'before_or_equal:'.today()->addDays(config('hms.booking.max_days_ahead'))->toDateString(),
            ],
        ]);

        $request->user()->doctorAvailabilities()->firstOrCreate(['date' => $data['date']]);

        return redirect()->route('doctor.availability.index')->with('success', 'Availability date saved.');
    }

    public function destroy(Request $request, DoctorAvailability $doctorAvailability): RedirectResponse
    {
        abort_unless($doctorAvailability->doctor_id === $request->user()->id, 404);

        $doctorAvailability->delete();

        return redirect()->route('doctor.availability.index')->with('success', 'Availability date removed.');
    }
}
