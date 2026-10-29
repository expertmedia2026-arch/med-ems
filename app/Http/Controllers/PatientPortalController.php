<?php

namespace App\Http\Controllers;

use App\Models\Patient;
use App\Services\PortalInvitationService;

/**
 * Sends patient portal invitations through PortalInvitationService from the staff patient record.
 */
class PatientPortalController extends Controller
{
    /**
     * Sends or refreshes an invitation for the selected patient and returns with the service result.
     */
    public function invite(Patient $patient, PortalInvitationService $portal)
    {
        $result = $portal->send($patient);

        $flash = $result['ok']
            ? ['success' => $result['message'], 'portal_invitation_sent' => true]
            : ['error' => $result['message']];

        return redirect()->route('patients.show', $patient)->with($flash);
    }
}
