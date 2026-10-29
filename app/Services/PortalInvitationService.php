<?php

namespace App\Services;

use App\Mail\PatientInvitationMail;
use App\Models\HospitalSetting;
use App\Models\Patient;
use App\Models\PatientInvitation;
use App\Models\User;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

/**
 * Issues patient portal invitations and emails their acceptance links.
 *
 * Only a hash of the random token is persisted. Any existing unaccepted invitations
 * for the patient are removed before a replacement is created.
 */
class PortalInvitationService
{
    /**
     * Create a seven-day portal invitation for an eligible patient and email its link.
     *
     * Rejects patients without an email or with an existing patient account/email
     * account. Mail transport failure is reported but leaves the valid invitation
     * available for manual link sharing.
     *
     * @param  Patient  $patient  Patient to invite.
     * @return array{ok: bool, message: string, link: ?string} Outcome, user-facing message, and acceptance URL when created.
     */
    public function send(Patient $patient): array
    {
        if (blank($patient->email)) {
            return $this->fail('This patient has no email address on file.');
        }

        if (User::where('patient_id', $patient->id)->exists()) {
            return $this->fail('This patient already has a portal account.');
        }

        if (User::where('email', $patient->email)->exists()) {
            return $this->fail("The email {$patient->email} already belongs to another account.");
        }

        // A new invitation replaces any open one
        PatientInvitation::where('patient_id', $patient->id)->whereNull('accepted_at')->delete();

        // Only a hash is stored; the plain token exists only in the link
        $token = Str::random(48);

        $invitation = PatientInvitation::create([
            'patient_id' => $patient->id,
            'email'      => $patient->email,
            'token_hash' => PatientInvitation::hashToken($token),
            'expires_at' => now()->addDays(7),
        ]);

        $url = route('portal.invitations.accept', $token);

        try {
            Mail::to($patient->email)->send(
                new PatientInvitationMail($invitation, $url, HospitalSetting::current()->name, $patient)
            );

            $message = "Portal invitation emailed to {$patient->email}.";
        } catch (\Throwable $e) {
            report($e);

            $message = 'The invitation email could not be sent. Share the link shown below with the patient.';
        }

        return ['ok' => true, 'message' => $message, 'link' => $url];
    }

    /**
     * Return a standard unsuccessful invitation result.
     *
     * @param  string  $message  Reason the invitation could not be sent.
     * @return array{ok: false, message: string, link: null} Failure result for the invitation flow.
     */
    private function fail(string $message): array
    {
        return ['ok' => false, 'message' => $message, 'link' => null];
    }
}