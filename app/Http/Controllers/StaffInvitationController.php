<?php

namespace App\Http\Controllers;

use App\Enums\Role;
use App\Http\Requests\StoreStaffInvitationRequest;
use App\Models\StaffInvitation;
use App\Services\StaffInvitationService;
use Illuminate\Http\Request;

/**
 * Coordinates staff invitation creation, delivery, resending, and revocation with StaffInvitationService.
 */
class StaffInvitationController extends Controller
{
    /**
     * Loads department choices from the invitation service and renders the invitation form.
     */
    public function create(StaffInvitationService $invitations)
    {
        return view('staff.invite', ['departments' => $invitations->departments()]);
    }

    /**
     * Issues an invitation from validated role and email details, returning to the staff list with delivery status.
     */
    public function store(StoreStaffInvitationRequest $request, StaffInvitationService $invitations)
    {
        $data = $request->validated();
        $result = $invitations->issue($data['email'], Role::from($data['role']), $data['department_id'] ?? null, $request->user());

        return redirect()->route('staff.index')
            ->with($result['sent'] ? 'success' : 'error', $result['message'])
            ->with('invite_link', $result['link']);
    }

    /**
     * Delegates invitation resend for the acting user and returns delivery status and any invitation link.
     */
    public function resend(Request $request, StaffInvitation $invitation, StaffInvitationService $invitations)
    {
        $result = $invitations->resend($invitation, $request->user());

        return redirect()->route('staff.index')
            ->with($result['sent'] ? 'success' : 'error', $result['message'])
            ->with('invite_link', $result['link']);
    }

    /**
     * Revokes the selected invitation through the service and returns to the prior page.
     */
    public function destroy(StaffInvitation $invitation, StaffInvitationService $invitations)
    {
        $invitations->revoke($invitation);

        return back()->with('success', 'Invitation revoked.');
    }
}
