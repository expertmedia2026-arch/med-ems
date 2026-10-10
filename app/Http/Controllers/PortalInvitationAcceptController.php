<?php

namespace App\Http\Controllers;

use App\Enums\Role;
use App\Models\PatientInvitation;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rules\Password;

class PortalInvitationAcceptController extends Controller
{
    public function show(string $token)
    {
        $invitation = PatientInvitation::findByToken($token);

        if (! $this->usable($invitation)) {
            return view('portal.invitation-invalid');
        }

        return view('portal.invitation-accept', [
            'invitation' => $invitation->load('patient'),
            'token'      => $token,
        ]);
    }

    public function store(Request $request, string $token)
    {
        $invitation = PatientInvitation::findByToken($token);

        if (! $this->usable($invitation)) {
            return view('portal.invitation-invalid');
        }

        $data = $request->validate([
            'password' => ['required', 'confirmed', Password::defaults()],
        ]);

        $patient = $invitation->patient;

        DB::transaction(function () use ($invitation, $patient, $data) {
            User::create([
                'name'              => $patient->full_name,
                'email'             => $invitation->email,
                'password'          => $data['password'],
                'role'              => Role::Patient,
                'patient_id'        => $patient->id,
                'is_active'         => true,
                'email_verified_at' => now(), // the link reached their inbox
            ]);

            $invitation->update(['accepted_at' => now()]);
        });

        $message = $patient->registration_paid_at
            ? 'Your account is ready. Log in with your email and password.'
            : 'Your account has been created. You can log in once your registration fee has been paid at the cashier.';

        return redirect()->route('login')->with('status', $message);
    }

    private function usable(?PatientInvitation $invitation): bool
    {
        return $invitation
            && $invitation->isUsable()
            && ! User::where('email', $invitation->email)->orWhere('patient_id', $invitation->patient_id)->exists();
    }
}