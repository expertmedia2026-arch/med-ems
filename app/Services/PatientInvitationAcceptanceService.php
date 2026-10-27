<?php

namespace App\Services;

use App\Enums\Role;
use App\Models\PatientInvitation;
use App\Models\User;
use App\Support\HospitalContext;
use Illuminate\Support\Facades\DB;

class PatientInvitationAcceptanceService
{
    public function __construct(private readonly HospitalContext $context) {}

    public function findUsable(string $token): ?PatientInvitation
    {
        $invitation = PatientInvitation::findByToken($token);

        if ($invitation) {
            $this->context->set((int) $invitation->hospital_id);
        }

        if (! $invitation
            || ! $invitation->isUsable()
            || User::where('email', $invitation->email)->orWhere('patient_id', $invitation->patient_id)->exists()) {
            return null;
        }

        return $invitation->load('patient');
    }

    public function accept(PatientInvitation $invitation, string $password): User
    {
        return DB::transaction(function () use ($invitation, $password) {
            $invitation = PatientInvitation::lockForUpdate()->with('patient')->findOrFail($invitation->id);
            $patient = $invitation->patient;

            if (! $invitation->isUsable() || User::where('email', $invitation->email)->orWhere('patient_id', $patient->id)->exists()) {
                abort(422, 'This invitation is no longer valid.');
            }

            $user = User::create([
                'name' => $patient->full_name,
                'email' => $invitation->email,
                'password' => $password,
                'role' => Role::Patient,
                'patient_id' => $patient->id,
                'is_active' => true,
                'email_verified_at' => now(),
            ]);
            $invitation->update(['accepted_at' => now()]);

            return $user;
        });
    }
}
