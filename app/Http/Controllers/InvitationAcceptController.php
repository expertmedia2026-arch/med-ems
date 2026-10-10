<?php

namespace App\Http\Controllers;

use App\Http\Requests\AcceptStaffInvitationRequest;
use App\Services\StaffInvitationService;
use Illuminate\Support\Facades\Auth;

class InvitationAcceptController extends Controller
{
    public function show(string $token, StaffInvitationService $invitations)
    {
        $invitation = $invitations->findUsable($token);

        if (! $invitation) {
            return view('invitations.invalid');
        }

        return view('invitations.accept', compact('invitation', 'token'));
    }

    public function store(AcceptStaffInvitationRequest $request, string $token, StaffInvitationService $invitations)
    {
        $invitation = $invitations->findUsable($token);

        if (! $invitation) {
            return view('invitations.invalid');
        }

        $data = $request->validated();
        $user = $invitations->accept($invitation, $data['name'], $data['password']);

        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->route('dashboard')->with('success', 'Welcome aboard. Your account is ready.');
    }
}
