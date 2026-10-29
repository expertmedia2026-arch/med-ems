<?php

namespace App\Services;

use App\Models\User;

/**
 * Updates account profile details and revokes an account and its API tokens.
 *
 * A changed email address loses its verified status; hospital administrators are
 * notified when their email changes.
 */
class ProfileService
{
    /** @param GlobalAdminNotificationService $globalAdminNotifications Notifies global administrators about hospital-admin email changes. */
    public function __construct(private readonly GlobalAdminNotificationService $globalAdminNotifications) {}

    /**
     * Save allowed profile attributes and return the refreshed user.
     *
     * If the email changes, verification is cleared before save and the configured
     * notification is sent for a changed hospital administrator email.
     *
     * @param  User  $user  Account being updated.
     * @param  array<string, mixed>  $attributes  Validated profile attributes.
     * @return User Refreshed account after persistence.
     */
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

    /**
     * Revoke all personal access tokens and delete the supplied user account.
     *
     * @param  User  $user  Account to delete.
     */
    public function delete(User $user): void
    {
        $user->tokens()->delete();
        $user->delete();
    }
}
