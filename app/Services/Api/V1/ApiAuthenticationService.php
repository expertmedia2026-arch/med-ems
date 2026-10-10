<?php

namespace App\Services\Api\V1;

use App\Models\User;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\PersonalAccessToken;

class ApiAuthenticationService
{
    public function login(string $email, string $password, string $deviceName): array
    {
        $user = User::where('email', $email)->first();

        if (! $user || ! Hash::check($password, $user->password)) {
            throw new AuthenticationException('The provided credentials are incorrect.');
        }

        abort_unless($user->is_active, 403, 'Your account is inactive. Please contact the administrator.');

        return [
            'user' => $user,
            'token' => $user->createToken($deviceName)->plainTextToken,
        ];
    }

    public function logout(User $user): void
    {
        $token = $user->currentAccessToken();

        abort_unless($token instanceof PersonalAccessToken, 401, 'A bearer token is required.');
        $token->delete();
    }
}
