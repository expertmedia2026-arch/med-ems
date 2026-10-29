<?php

namespace App\Services\Api\V1;

use App\Enums\Role;
use App\Models\HospitalSetting;
use App\Models\User;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\PersonalAccessToken;

/**
 * Authenticates API users and manages their Sanctum bearer tokens.
 *
 * Successful patient logins may include a registration-fee notice based on the
 * current hospital settings. Logout revokes only the token used for the request.
 */
class ApiAuthenticationService
{
    /**
     * Validate credentials and issue a new personal access token.
     *
     * Inactive accounts are denied. Patients with an unpaid registration fee receive
     * a notice in the response; the fee is not collected by this method.
     *
     * @param  string  $email  User account email.
     * @param  string  $password  Plain password to verify against the stored hash.
     * @param  string  $deviceName  Label attached to the issued personal access token.
     * @return array{user: User, token: string, registration_fee_notice: array{required: bool, amount: float, currency: string, message: string}|null} Authenticated user, bearer token, and optional payment notice.
     *
     * @throws AuthenticationException When the email or password is incorrect.
     */
    public function login(string $email, string $password, string $deviceName): array
    {
        $user = User::where('email', $email)->first();

        if (! $user || ! Hash::check($password, $user->password)) {
            throw new AuthenticationException('The provided credentials are incorrect.');
        }

        abort_unless($user->is_active, 403, 'Your account is inactive. Please contact the administrator.');

        $patient = $user->patient;
        $registrationFeeNotice = null;

        if ($user->role === Role::Patient && $patient && ! $patient->registration_paid_at) {
            $amount = (float) HospitalSetting::current()->registration_fee;
            $registrationFeeNotice = [
                'required' => true,
                'amount' => $amount,
                'currency' => 'NGN',
                'message' => 'Your registration fee of ₦'.number_format($amount, 2).' is outstanding. Please pay at the billing desk.',
            ];
        }

        return [
            'user' => $user,
            'token' => $user->createToken($deviceName)->plainTextToken,
            'registration_fee_notice' => $registrationFeeNotice,
        ];
    }

    /**
     * Revoke the current request's Sanctum personal access token.
     *
     * Requests without a personal access token are rejected; other tokens belonging
     * to the user remain valid.
     *
     * @param  User  $user  Authenticated account whose current token is being revoked.
     */
    public function logout(User $user): void
    {
        $token = $user->currentAccessToken();

        abort_unless($token instanceof PersonalAccessToken, 401, 'A bearer token is required.');
        $token->delete();
    }
}
