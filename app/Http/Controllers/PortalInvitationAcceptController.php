<?php

namespace App\Http\Controllers;

use App\Http\Requests\AcceptPatientInvitationRequest;
use App\Services\PatientInvitationAcceptanceService;

/**
 * Validates patient portal invitation links and delegates account activation to the acceptance service.
 */
class PortalInvitationAcceptController extends Controller
{
    /**
     * Resolves a usable invitation token and renders the acceptance form or invalid-link page.
     */
    public function show(string $token, PatientInvitationAcceptanceService $acceptance)
    {
        $invitation = $acceptance->findUsable($token);

        if (! $invitation) {
            return view('portal.invitation-invalid');
        }

        return view('portal.invitation-accept', [
            'invitation' => $invitation,
            'token' => $token,
        ]);
    }

    /**
     * Validates a password, accepts the invitation, and redirects to sign-in with registration-payment status.
     */
    public function store(AcceptPatientInvitationRequest $request, string $token, PatientInvitationAcceptanceService $acceptance)
    {
        $invitation = $acceptance->findUsable($token);

        if (! $invitation) {
            return view('portal.invitation-invalid');
        }

        $data = $request->validated();

        $patient = $invitation->patient;

        $acceptance->accept($invitation, $data['password']);

        $message = $patient->registration_paid_at
            ? 'Your account is ready. Log in with your email and password.'
            : 'Your account has been created. You can log in once your registration fee has been paid at the billing desk.';

        return redirect()->route('login')->with('status', $message);
    }
}
