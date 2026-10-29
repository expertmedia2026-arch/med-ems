<?php

namespace App\Services;

use App\Enums\Role;
use App\Enums\VisitStatus;
use App\Models\EmergencyProgressNote;
use App\Models\User;
use App\Models\Visit;

/**
 * Records clinical progress notes during active emergency-department care.
 */
class EmergencyProgressNoteService
{
    /**
     * Add an emergency note after enforcing A&E access, active-visit, and clinician-assignment rules.
     *
     * Nurses with emergency access may add nursing notes; doctors must be the visit's attending doctor
     * to add a medical note. The new note is returned; the visit itself is not changed.
     */
    public function add(Visit $visit, string $note, User $author): EmergencyProgressNote
    {
        abort_unless($visit->is_emergency, 404);
        abort_unless($author->hasRole(Role::Doctor, Role::Nurse) && $author->canAccessEmergency(), 403);
        abort_unless(in_array($visit->status, [
            VisitStatus::WaitingTriage,
            VisitStatus::WaitingDoctor,
            VisitStatus::InConsultation,
        ], true), 422, 'A&E progress notes can only be added while emergency care is active.');

        if ($author->hasRole(Role::Doctor)) {
            abort_unless($visit->isOpenFor($author), 403, 'Only the attending A&E doctor can add a medical progress note.');
        }

        return $visit->emergencyProgressNotes()->create([
            'author_id' => $author->id,
            'note_type' => $author->hasRole(Role::Nurse) ? 'nursing' : 'medical',
            'note' => $note,
        ]);
    }
}
