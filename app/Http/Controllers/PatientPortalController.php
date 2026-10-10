<?php

namespace App\Http\Controllers;

use App\Models\Patient;
use App\Services\PortalInvitationService;

class PatientPortalController extends Controller
{
    public function invite(Patient $patient, PortalInvitationService $portal)
    {
        $result = $portal->send($patient);

        $flash = $result['ok']
            ? ['success' => $result['message'], 'invite_link' => $result['link']]
            : ['error' => $result['message']];

        return redirect()->route('patients.show', $patient)->with($flash);
    }
}