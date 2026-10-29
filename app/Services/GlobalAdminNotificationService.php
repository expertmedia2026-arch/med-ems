<?php

namespace App\Services;

use App\Enums\Role;
use App\Models\Hospital;
use App\Models\User;
use App\Notifications\HospitalActionNotification;

/**
 * Sends platform-level notifications for selected hospital and hospital-administrator changes.
 *
 * Recipient lookup deliberately bypasses the hospital tenant scope, then restricts recipients to
 * active global administrators without a hospital assignment.
 */
class GlobalAdminNotificationService
{
    /**
     * Notify platform administrators that a hospital's display name has changed.
     *
     * @param  string  $previousName  Hospital name before the settings update.
     * @param  string  $newName  Hospital name after the settings update.
     */
    public function hospitalNameChanged(Hospital $hospital, string $previousName, string $newName): void
    {
        $this->notifyPlatformAdmins(
            $hospital,
            'Hospital name updated',
            "The hospital name changed from {$previousName} to {$newName}.",
        );
    }

    /**
     * Notify platform administrators when a hospital-scoped administrator changes email.
     *
     * Global administrators, non-administrator users, and administrators without a hospital are
     * intentionally ignored. The related hospital is resolved before delivering notifications.
     *
     * @param  string  $previousEmail  Email address before the update.
     */
    public function hospitalAdminEmailChanged(User $admin, string $previousEmail): void
    {
        if ($admin->role !== Role::Admin || $admin->hospital_id === null || $admin->is_global_admin) {
            return;
        }

        $hospital = $admin->hospital()->firstOrFail();

        $this->notifyPlatformAdmins(
            $hospital,
            'Hospital admin email updated',
            "{$hospital->name} changed its administrator email from {$previousEmail} to {$admin->email}.",
        );
    }

    /**
     * Deliver a hospital-action notification to every active platform administrator.
     *
     * The notification links to the affected hospital's global-admin edit screen.
     */
    private function notifyPlatformAdmins(Hospital $hospital, string $title, string $message): void
    {
        User::withoutGlobalScope('hospital')
            ->where('is_global_admin', true)
            ->whereNull('hospital_id')
            ->where('is_active', true)
            ->get()
            ->each(fn (User $admin) => $admin->notify(new HospitalActionNotification(
                $title,
                $message,
                route('global-admin.hospitals.edit', $hospital),
            )));
    }
}
