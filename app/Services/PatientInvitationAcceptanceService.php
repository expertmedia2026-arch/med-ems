<?php

namespace App\Services;

use App\Enums\Role;
use App\Models\PatientInvitation;
use App\Models\User;
use App\Support\HospitalContext;
use Illuminate\Support\Facades\DB;

/**
 * Resolves patient invitation tokens and creates the linked portal account.
 *
 * Resolving a valid invitation establishes its hospital context before loading the
 * patient. Acceptance rechecks the invitation and account uniqueness while holding
 * a row lock inside a transaction.
 */
class PatientInvitationAcceptanceService
{
    /** @param HospitalContext $context Tenant context selected from a matched invitation. */
    public function __construct(private readonly HospitalContext $context) {}

    /**
     * Find an unexpired, unused invitation and select its hospital context.
     *
     * Tokens are looked up through the invitation's token-hash helper. Invitations
     * are rejected if unusable or if their email or patient already has an account.
     *
     * @param  string  $token  Plain invitation token received from the acceptance link.
     * @return PatientInvitation|null Usable invitation with its patient loaded, or null when rejected.
     */
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

    /**
     * Create and activate the patient portal account for an invitation.
     *
     * The invitation is reloaded with a row lock and validated again in the
     * transaction to prevent concurrent acceptance. The account is marked active
     * and email-verified, and the invitation is marked accepted atomically.
     *
     * @param  PatientInvitation  $invitation  Invitation selected by the acceptance flow.
     * @param  string  $password  New account password, hashed by the User model's cast.
     * @return User Newly created patient portal account.
     */
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
