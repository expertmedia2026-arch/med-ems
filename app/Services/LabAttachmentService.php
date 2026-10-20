<?php

namespace App\Services;

use App\Enums\Role;
use App\Enums\VisitStatus;
use App\Models\LabAttachment;
use App\Models\User;
use Illuminate\Support\Facades\Storage;

class LabAttachmentService
{
    public function canView(User $user, LabAttachment $attachment): bool
    {
        if ($user->hasRole(Role::Admin, Role::Lab)) {
            return true;
        }

        if ($user->hasRole(Role::Nurse)) {
            $labRequest = $attachment->labRequest()->with(['visit.admission'])->first();

            return (bool) ($labRequest?->visit?->admission
                && (int) $labRequest->visit->admission->department_id === (int) $user->department_id);
        }

        if (! $user->hasRole(Role::Doctor)) {
            return false;
        }

        $labRequest = $attachment->labRequest()->with('visit.admission')->first();

        return (bool) ($labRequest
            && ($labRequest->requested_by === $user->id
                || $labRequest->visit->status === VisitStatus::Completed
                || ($labRequest->visit->admission
                    && ((int) $labRequest->visit->admission->attending_doctor_id === (int) $user->id
                        || ($user->department_id && (int) $labRequest->visit->admission->department_id === (int) $user->department_id)))));
    }

    public function delete(LabAttachment $attachment): void
    {
        Storage::disk('local')->delete($attachment->path);
        $attachment->delete();
    }
}
