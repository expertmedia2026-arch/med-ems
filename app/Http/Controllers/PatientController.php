<?php

namespace App\Http\Controllers;

use App\Http\Requests\PatientDetailsRequest;
use App\Http\Requests\PatientIndexRequest;
use App\Models\Patient;
use App\Services\PatientRegistrationService;
use App\Services\PatientService;
use App\Services\PortalInvitationService;

class PatientController extends Controller
{
    public function index(PatientIndexRequest $request, PatientService $patients)
    {
        $records = $patients->index($request->validated('q'));

        return view('patients.index', ['patients' => $records]);
    }

    public function create()
    {
        return view('patients.create');
    }

    public function store(PatientDetailsRequest $request, PatientRegistrationService $registration, PortalInvitationService $portal)
    {
        ['patient' => $patient, 'invoice' => $invoice] = $registration->register($request->validated());

        $message = "Patient registered: {$patient->hospital_number}.";

        if ($invoice) {
            $message .= ' Registration fee of ₦'.number_format((float) $invoice->total).' is waiting at the cashier.';
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

    public function show(Patient $patient, PatientService $patients)
    {
        return view('patients.show', $patients->showData($patient));
    }

    public function card(Patient $patient, PatientService $patients)
    {
        return view('patients.card', $patients->cardData($patient));
    }

    public function edit(Patient $patient)
    {
        return view('patients.edit', compact('patient'));
    }

    public function update(PatientDetailsRequest $request, Patient $patient, PatientService $patients)
    {
        $patients->update($patient, $request->validated());

        return redirect()->route('patients.show', $patient)->with('success', 'Patient updated.');
    }
}
