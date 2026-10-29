<?php

namespace App\Services;

use App\Models\User;

class ProfileService
{
    public function __construct(private readonly GlobalAdminNotificationService $globalAdminNotifications) {}

    public function update(User $user, array $attributes): User
    {
        $previousEmail = $user->email;
        $user->fill($attributes);

        if ($user->isDirty('email')) {
            $user->email_verified_at = null;
        }

        $user->save();

        if ($user->wasChanged('email')) {
            $this->globalAdminNotifications->hospitalAdminEmailChanged($user, $previousEmail);
        }

        return $user->refresh();
    }

    public function delete(User $user): void
    {
        $user->tokens()->delete();
        $user->delete();
    }
}
