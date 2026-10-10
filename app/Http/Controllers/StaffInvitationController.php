<?php

namespace App\Http\Controllers;

use App\Enums\Role;
use App\Mail\StaffInvitationMail;
use App\Models\HospitalSetting;
use App\Models\StaffInvitation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class StaffInvitationController extends Controller
{
    public function create()
    {
        return view('staff.invite');
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'role' => ['required', Rule::enum(Role::class)->except([Role::Patient])],
        ], ['email.unique' => 'A staff member with this email already exists.']);

        return $this->issue($data['email'], Role::from($data['role']), $request);
    }

    public function resend(Request $request, StaffInvitation $invitation)
    {
        abort_if($invitation->accepted_at, 422, 'This invitation was already accepted.');

        return $this->issue($invitation->email, $invitation->role, $request);
    }

    public function destroy(StaffInvitation $invitation)
    {
        $invitation->delete();

        return back()->with('success', 'Invitation revoked.');
    }

    /** Create a fresh invitation (replacing any open one for this email) and email the link. */
    private function issue(string $email, Role $role, Request $request)
    {
        StaffInvitation::where('email', $email)->whereNull('accepted_at')->delete();

        // Only a hash is stored; the plain token exists only in the link
        $token = Str::random(48);

        $invitation = StaffInvitation::create([
            'email'      => $email,
            'role'       => $role,
            'token_hash' => StaffInvitation::hashToken($token),
            'invited_by' => $request->user()->id,
            'expires_at' => now()->addDays(7),
        ]);

        $url = route('invitations.accept', $token);

        try {
            Mail::to($email)->send(new StaffInvitationMail($invitation, $url, HospitalSetting::current()->name));
            $flash = ['success' => "Invitation sent to {$email}."];
        } catch (\Throwable $e) {
            report($e);
            $flash = ['error' => "The email could not be sent, but the invitation is valid. Share the link below with {$email} manually."];
        }

        return redirect()->route('staff.index')->with($flash + ['invite_link' => $url]);
    }
}