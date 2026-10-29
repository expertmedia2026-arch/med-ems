<?php

namespace App\Http\Controllers;

use App\Http\Requests\DeleteProfileRequest;
use App\Http\Requests\ProfileUpdateRequest;
use App\Services\ProfileService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Redirect;
use Illuminate\View\View;

/**
 * Validates profile and account-deletion requests and delegates account changes to ProfileService.
 */
class ProfileController extends Controller
{
    /**
     * Display the user's profile form.
     */
    public function edit(Request $request): View
    {
        return view('profile.edit', [
            'user' => $request->user(),
        ]);
    }

    /**
     * Updates the authenticated user's profile from validated input and redirects to the edit form.
     */
    public function update(ProfileUpdateRequest $request, ProfileService $profile): RedirectResponse
    {
        $profile->update($request->user(), $request->validated());

        return Redirect::route('profile.edit')->with('status', 'profile-updated');
    }

    /**
     * Confirms account-deletion authorization, logs out, deletes the user through the service, and clears the session.
     */
    public function destroy(DeleteProfileRequest $request, ProfileService $profile): RedirectResponse
    {
        $user = $request->user();

        Auth::logout();

        $profile->delete($user);

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return Redirect::to('/');
    }
}
