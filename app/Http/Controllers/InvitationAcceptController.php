<?php

namespace App\Http\Controllers;

use App\Models\StaffInvitation;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rules\Password;

class InvitationAcceptController extends Controller
{
    public function show(string $token)
    {
        $invitation = StaffInvitation::findByToken($token);

        if (! $invitation?->isUsable() || User::where('email', $invitation->email)->exists()) {
            return view('invitations.invalid');
        }

        return view('invitations.accept', compact('invitation', 'token'));
    }

    public function store(Request $request, string $token)
    {
        $invitation = StaffInvitation::findByToken($token);

        if (! $invitation?->isUsable() || User::where('email', $invitation->email)->exists()) {
            return view('invitations.invalid');
        }

        $data = $request->validate([
            'name'     => ['required', 'string', 'max:150'],
            'password' => ['required', 'confirmed', Password::defaults()],
        ]);

        $user = DB::transaction(function () use ($invitation, $data) {
            $user = User::create([
                'name'              => $data['name'],
                'email'             => $invitation->email,
                'password'          => $data['password'],
                'role'              => $invitation->role,
                'is_active'         => true,
                'email_verified_at' => now(),
            ]);

            $invitation->update(['accepted_at' => now()]);

            return $user;
        });

        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->route('dashboard')->with('success', 'Welcome aboard. Your account is ready.');
    }
}