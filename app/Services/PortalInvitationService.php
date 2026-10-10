<?php

namespace App\Services;

use App\Mail\PatientInvitationMail;
use App\Models\HospitalSetting;
use App\Models\Patient;
use App\Models\PatientInvitation;
use App\Models\User;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class PortalInvitationService
{
    /** @return array{ok: bool, message: string, link: ?string} */
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

    private function fail(string $message): array
    {
        return ['ok' => false, 'message' => $message, 'link' => null];
    }
}