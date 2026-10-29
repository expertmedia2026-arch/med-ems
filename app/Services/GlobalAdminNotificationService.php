<?php

namespace App\Services;

use App\Enums\Role;
use App\Models\Hospital;
use App\Models\User;
use App\Notifications\HospitalActionNotification;

class GlobalAdminNotificationService
{
    public function hospitalNameChanged(Hospital $hospital, string $previousName, string $newName): void
    {
        $this->notifyPlatformAdmins(
            $hospital,
            'Hospital name updated',
            "The hospital name changed from {$previousName} to {$newName}.",
        );
    }

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
