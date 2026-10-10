<?php

namespace App\Services;

use App\Enums\Role;
use App\Mail\StaffInvitationMail;
use App\Models\Department;
use App\Models\HospitalSetting;
use App\Models\StaffInvitation;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class StaffInvitationService
{
    public function findUsable(string $token): ?StaffInvitation
    {
        $invitation = StaffInvitation::findByToken($token);

        if (! $invitation?->isUsable() || User::where('email', $invitation->email)->exists()) {
            return null;
        }

        return $invitation;
    }

    public function departments(): Collection
    {
        return Department::orderBy('name')->get();
    }

    public function revoke(StaffInvitation $invitation): void
    {
        $invitation->delete();
    }

    /** @return array{invitation: StaffInvitation, link: string, message: string, sent: bool} */
    public function resend(StaffInvitation $invitation, User $inviter): array
    {
        abort_if($invitation->accepted_at, 422, 'This invitation was already accepted.');

        return $this->issue($invitation->email, $invitation->role, $invitation->department_id, $inviter);
    }

    /** @return array{invitation: StaffInvitation, link: string, message: string, sent: bool} */
    public function issue(string $email, Role $role, ?int $departmentId, User $inviter): array
    {
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
