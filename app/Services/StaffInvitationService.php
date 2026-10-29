<?php

namespace App\Services;

use App\Enums\Role;
use App\Mail\StaffInvitationMail;
use App\Models\Department;
use App\Models\HospitalSetting;
use App\Models\StaffInvitation;
use App\Models\User;
use App\Support\HospitalContext;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

/**
 * Issues, validates, revokes, and accepts staff invitations.
 *
 * Invitation tokens are stored as hashes, expire after seven days, and are rechecked
 * under a row lock during acceptance. Hospital context is selected from a matched
 * invitation before its hospital-specific data is used.
 */
class StaffInvitationService
{
    /** @param HospitalContext $context Selects the tenant of a resolved invitation. */
    public function __construct(private readonly HospitalContext $context) {}

    /**
     * Resolve a plain token to a usable invitation and select its hospital context.
     *
     * Returns null for invalid, expired, accepted, or already-claimed invitations.
     *
     * @param  string  $token  Plain invitation token from the acceptance URL.
     * @return StaffInvitation|null Usable invitation, or null when it cannot be accepted.
     */
    public function findUsable(string $token): ?StaffInvitation
    {
        $invitation = StaffInvitation::findByToken($token);

        if ($invitation) {
            $this->context->set((int) $invitation->hospital_id);
        }

        if (! $invitation?->isUsable() || User::where('email', $invitation->email)->exists()) {
            return null;
        }

        return $invitation;
    }

    /**
     * Return departments available for assigning invited staff.
     *
     * @return Collection<int, Department> Departments sorted by name.
     */
    public function departments(): Collection
    {
        return Department::orderBy('name')->get();
    }

    /**
     * Delete an invitation so its acceptance link can no longer be used.
     *
     * @param  StaffInvitation  $invitation  Invitation to revoke.
     */
    public function revoke(StaffInvitation $invitation): void
    {
        $invitation->delete();
    }

    /**
     * Replace an unaccepted invitation by issuing a new link for its existing role and department.
     *
     * Accepted invitations cannot be resent. Mail delivery failure leaves the newly
     * issued invitation valid and is reported in the returned result.
     *
     * @param  StaffInvitation  $invitation  Existing invitation whose role and department are retained.
     * @param  User  $inviter  Account recorded as the issuer of the replacement invitation.
     * @return array{invitation: StaffInvitation, link: string, message: string, sent: bool} Replacement invitation and delivery outcome.
     */
    public function resend(StaffInvitation $invitation, User $inviter): array
    {
        abort_if($invitation->accepted_at, 422, 'This invitation was already accepted.');

        return $this->issue($invitation->email, $invitation->role, $invitation->department_id, $inviter);
    }

    /**
     * Create a seven-day staff invitation and email its acceptance link.
     *
     * Finance and pharmacy roles are assigned to their designated departments,
     * regardless of the optional department argument. Any existing unaccepted
     * invitation for the email is deleted before a new hashed token is stored.
     *
     * @param  string  $email  Invitee email address.
     * @param  Role  $role  Role assigned to the new staff account.
     * @param  int|null  $departmentId  Requested department, when applicable to the role.
     * @param  User  $inviter  Account recorded as the issuer.
     * @return array{invitation: StaffInvitation, link: string, message: string, sent: bool} Invitation, plain acceptance URL, result message, and mail status.
     */
    public function issue(string $email, Role $role, ?int $departmentId, User $inviter): array
    {
        if ($role->isFinanceStaff()) {
            $departmentId = Department::where('name', Department::FINANCE_NAME)->value('id');
        } elseif ($role->isPharmacyStaff()) {
            $departmentId = Department::where('name', Department::PHARMACY_NAME)->value('id');
        }

        StaffInvitation::where('email', $email)->whereNull('accepted_at')->delete();

        $token = Str::random(48);
        $invitation = StaffInvitation::create([
            'email' => $email,
            'role' => $role,
            'department_id' => $departmentId,
            'token_hash' => StaffInvitation::hashToken($token),
            'invited_by' => $inviter->id,
            'expires_at' => now()->addDays(7),
        ]);
        $url = route('invitations.accept', $token);

        try {
            Mail::to($email)->send(new StaffInvitationMail($invitation, $url, HospitalSetting::current()->name));

            return ['invitation' => $invitation, 'link' => $url, 'message' => "Invitation sent to {$email}.", 'sent' => true];
        } catch (\Throwable $exception) {
            report($exception);

            return [
                'invitation' => $invitation,
                'link' => $url,
                'message' => "The email could not be sent, but the invitation is valid. Share the link below with {$email} manually.",
                'sent' => false,
            ];
        }
    }

    /**
     * Atomically create the invited staff account and mark the invitation accepted.
     *
     * The invitation is reloaded with a row lock and rejected if unusable or if its
     * email already has an account. The new user is active and email-verified.
     *
     * @param  StaffInvitation  $invitation  Invitation being accepted.
     * @param  string  $name  Name for the created staff account.
     * @param  string  $password  New account password, hashed by the User model's cast.
     * @return User Newly created staff account.
     */
    public function accept(StaffInvitation $invitation, string $name, string $password): User
    {
        return DB::transaction(function () use ($invitation, $name, $password) {
            $invitation = StaffInvitation::lockForUpdate()->findOrFail($invitation->id);

            if (! $invitation->isUsable() || User::where('email', $invitation->email)->exists()) {
                abort(422, 'This invitation is no longer valid.');
            }

            $user = User::create([
                'name' => $name,
                'email' => $invitation->email,
                'password' => $password,
                'role' => $invitation->role,
                'department_id' => $invitation->department_id,
                'is_active' => true,
                'email_verified_at' => now(),
            ]);
            $invitation->update(['accepted_at' => now()]);

            return $user;
        });
    }
}
