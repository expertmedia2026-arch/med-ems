<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\AcceptPatientInvitationRequest;
use App\Http\Requests\AcceptStaffInvitationRequest;
use App\Http\Requests\Api\V1\LoginRequest;
use App\Http\Resources\Api\V1\UserResource;
use App\Services\Api\V1\ApiAuthenticationService;
use App\Services\PatientInvitationAcceptanceService;
use App\Services\StaffInvitationService;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password as PasswordRule;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function forgotPassword(Request $request): JsonResponse
    {
        $request->validate(['email' => ['required', 'email']]);
        Password::sendResetLink($request->only('email'));

        return response()->json(['message' => 'If an account exists for that email, a reset link has been sent.']);
    }

    public function resetPassword(Request $request): JsonResponse
    {
        $request->validate([
            'token' => ['required', 'string'],
            'email' => ['required', 'email'],
            'password' => ['required', 'confirmed', PasswordRule::defaults()],
        ]);

        $status = Password::reset($request->only('email', 'password', 'password_confirmation', 'token'), function ($user) use ($request) {
            $user->forceFill(['password' => Hash::make($request->password), 'remember_token' => Str::random(60)])->save();
            $user->tokens()->delete();
            event(new PasswordReset($user));
        });

        if ($status !== Password::PASSWORD_RESET) {
            throw ValidationException::withMessages(['email' => [__($status)]]);
        }

        return response()->json(['message' => __($status)]);
    }

    public function acceptStaffInvitation(AcceptStaffInvitationRequest $request, string $token, StaffInvitationService $invitations): JsonResponse
    {
        $invitation = $invitations->findUsable($token);
        abort_unless($invitation, 404, 'This invitation is no longer valid.');
        $user = $invitations->accept($invitation, $request->validated('name'), $request->validated('password'));

        return response()->json(['message' => 'Invitation accepted. Sign in to continue.', 'user' => UserResource::make($user)->resolve($request)]);
    }

    public function acceptPatientInvitation(AcceptPatientInvitationRequest $request, string $token, PatientInvitationAcceptanceService $invitations): JsonResponse
    {
        $invitation = $invitations->findUsable($token);
        abort_unless($invitation, 404, 'This invitation is no longer valid.');
        $user = $invitations->accept($invitation, $request->validated('password'));

        return response()->json(['message' => 'Invitation accepted. Sign in to continue.', 'user' => UserResource::make($user)->resolve($request)]);
    }

    public function store(LoginRequest $request, ApiAuthenticationService $authentication): JsonResponse
    {
        $credentials = $request->validated();
        $result = $authentication->login(
            $credentials['email'],
            $credentials['password'],
            $credentials['device_name'],
        );

        return response()->json([
            'message' => 'Authenticated.',
            'token_type' => 'Bearer',
            'token' => $result['token'],
            'user' => UserResource::make($result['user'])->resolve($request),
            'registration_fee_notice' => $result['registration_fee_notice'],
        ]);
    }

    public function show(Request $request): UserResource
    {
        return UserResource::make($request->user());
    }

    public function destroy(Request $request, ApiAuthenticationService $authentication): JsonResponse
    {
        $authentication->logout($request->user());

        return response()->json(['message' => 'Signed out.']);
    }
}
