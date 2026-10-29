<?php

namespace App\Services;

use App\Enums\Role;
use App\Enums\VisitStatus;
use App\Models\LabAttachment;
use App\Models\User;
use Illuminate\Support\Facades\Storage;

/**
 * Applies role- and department-based access checks to laboratory result attachments.
 *
 * The attachment and related records are loaded through normal tenant-aware model queries;
 * successful deletion removes both the private-disk file and its database row.
 */
class LabAttachmentService
{
    /**
     * Determine whether a user may view a result file based on their role and clinical assignment.
     *
     * Administrators may view attachments in their accessible tenant; laboratory staff must match
     * the test department, nurses must match the admitted ward, and doctors must have requested
     * the test, have a completed visit, or be assigned to the linked inpatient care.
     */
    public function canView(User $user, LabAttachment $attachment): bool
    {
        if ($user->hasRole(Role::Admin)) {
            return true;
        }

        if ($user->hasRole(Role::Lab)) {
            $labRequest = $attachment->labRequest()->with('test')->first();

            return (bool) ($labRequest && $user->department_id
                && (int) $labRequest->test?->department_id === (int) $user->department_id);
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

    /**
     * Delete an attachment's private stored file and database record.
     *
     * Only an administrator or laboratory user who can view the attachment may remove it. File
     * deletion is attempted before the model row is deleted.
     */
    public function delete(LabAttachment $attachment, User $user): void
    {
        abort_unless($user->hasRole(Role::Admin)
            || ($user->hasRole(Role::Lab) && $this->canView($user, $attachment)), 403);

        Storage::disk('local')->delete($attachment->path);
        $attachment->delete();
    }
}
