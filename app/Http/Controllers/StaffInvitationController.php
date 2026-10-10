<?php

namespace App\Http\Controllers;

use App\Enums\Role;
use App\Http\Requests\StoreStaffInvitationRequest;
use App\Models\StaffInvitation;
use App\Services\StaffInvitationService;
use Illuminate\Http\Request;

class StaffInvitationController extends Controller
{
    public function create(StaffInvitationService $invitations)
    {
        return view('staff.invite', ['departments' => $invitations->departments()]);
    }

    public function store(StoreStaffInvitationRequest $request, StaffInvitationService $invitations)
    {
        $data = $request->validated();
        $result = $invitations->issue($data['email'], Role::from($data['role']), $data['department_id'] ?? null, $request->user());

        return redirect()->route('staff.index')
            ->with($result['sent'] ? 'success' : 'error', $result['message'])
            ->with('invite_link', $result['link']);
    }

    public function resend(Request $request, StaffInvitation $invitation, StaffInvitationService $invitations)
    {
        $result = $invitations->resend($invitation, $request->user());

        return redirect()->route('staff.index')
            ->with($result['sent'] ? 'success' : 'error', $result['message'])
            ->with('invite_link', $result['link']);
    }

    public function destroy(StaffInvitation $invitation, StaffInvitationService $invitations)
    {
        $invitations->revoke($invitation);

        return back()->with('success', 'Invitation revoked.');
    }
}
