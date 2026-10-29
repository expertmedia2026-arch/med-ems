<?php

namespace App\Http\Controllers;

use App\Http\Requests\PatientDetailsRequest;
use App\Http\Requests\PatientIndexRequest;
use App\Models\Patient;
use App\Services\PatientRegistrationService;
use App\Services\PatientService;
use App\Services\PortalInvitationService;

/**
 * Coordinates patient directory and profile pages with registration and patient services.
 */
class PatientController extends Controller
{
    /**
     * Uses the validated search term to retrieve matching patient records and render the directory.
     */
    public function index(PatientIndexRequest $request, PatientService $patients)
    {
        $records = $patients->index($request->validated('q'));

        return view('patients.index', ['patients' => $records]);
    }

    public function create()
    {
        return view('patients.create');
    }

    /**
     * Registers a patient from validated details, optionally sends a portal invitation, and redirects to the patient record.
     */
    public function store(PatientDetailsRequest $request, PatientRegistrationService $registration, PortalInvitationService $portal)
    {
        ['patient' => $patient, 'invoice' => $invoice] = $registration->register($request->validated());

        $message = "Patient registered: {$patient->hospital_number}.";

        if ($invoice) {
            $message .= ' Registration fee of ₦'.number_format((float) $invoice->total).' is waiting at the billing desk.';
        }

        $flash = [];

        // Patients with an email get a verify-and-set-password invitation straight away
        if (filled($patient->email)) {
            $result = $portal->send($patient);

            if ($result['ok']) {
                $message .= ' '.$result['message'];
                $flash['portal_invitation_sent'] = true;
            } else {
                $flash['error'] = $result['message'];
            }
        }

        return redirect()->route('patients.show', $patient)->with(['success' => $message] + $flash);
    }

    /**
     * Loads patient details through PatientService and renders the profile with enabled hospital modules.
     */
    public function show(Patient $patient, PatientService $patients)
    {
        return view('patients.show', $patients->showData($patient) + [
            'patientIdCardEnabled' => auth()->user()->hospital?->hasModule('patient_id_card') ?? false,
            'appointmentsEnabled' => auth()->user()->hospital?->hasModule('appointments') ?? false,
            'externalLabEnabled' => auth()->user()->hospital?->hasModule('laboratory')
                && auth()->user()->hospital?->hasModule('external_lab'),
            'patientPortalEnabled' => auth()->user()->hospital?->hasModule('patient_portal') ?? false,
        ]);
    }

    /**
     * Builds the selected patient's ID-card data through the service and renders the printable card.
     */
    public function card(Patient $patient, PatientService $patients)
    {
        return view('patients.card', $patients->cardData($patient));
    }

    public function edit(Patient $patient)
    {
        return view('patients.edit', compact('patient'));
    }

    /**
     * Applies validated profile changes through PatientService and redirects to the patient's record.
     */
    public function update(PatientDetailsRequest $request, Patient $patient, PatientService $patients)
    {
        $patients->update($patient, $request->validated());

        return redirect()->route('patients.show', $patient)->with('success', 'Patient updated.');
    }
}
