<?php

namespace App\Http\Controllers;

use App\Http\Requests\StaffIndexRequest;
use App\Http\Requests\StoreStaffRequest;
use App\Http\Requests\UpdateStaffRequest;
use App\Models\User;
use App\Services\StaffService;
use Illuminate\Http\Request;

/**
 * Coordinates staff directory, account, and role-management pages with StaffService.
 */
class StaffController extends Controller
{
    /**
     * Uses validated filters and the current user to retrieve and render the authorized staff directory.
     */
    public function index(StaffIndexRequest $request, StaffService $staffService)
    {
        return view('staff.index', $staffService->indexData($request->validated(), $request->user()));
    }

    /**
     * Loads department options through the service and renders the staff creation form.
     */
    public function create(StaffService $staffService)
    {
        return view('staff.create', ['departments' => $staffService->departments()]);
    }

    /**
     * Creates a staff account from validated input and redirects with its role-specific confirmation.
     */
    public function store(StoreStaffRequest $request, StaffService $staffService)
    {
        $user = $staffService->create($request->validated());

        return redirect()->route('staff.index')->with('success', "{$user->name} added as {$user->role->label()}.");
    }

    /**
     * Checks that the selected user is a staff record and renders it with available departments.
     */
    public function edit(User $staff, StaffService $staffService)
    {
        $staffService->assertStaffRecord($staff);

        return view('staff.edit', [
            'staff' => $staff,
            'departments' => $staffService->departments(),
        ]);
    }

    /**
     * Updates the selected staff record from validated input and the acting user, then returns to the directory.
     */
    public function update(UpdateStaffRequest $request, User $staff, StaffService $staffService)
    {
        $staffService->update($staff, $request->validated(), $request->user());

        return redirect()->route('staff.index')->with('success', "{$staff->name} updated.");
    }

    /**
     * Delegates activation changes for the selected staff member and returns the resulting sign-in status.
     */
    public function toggle(Request $request, User $staff, StaffService $staffService)
    {
        $staff = $staffService->toggle($staff, $request->user());

        return back()->with('success', $staff->name.($staff->is_active ? ' reactivated.' : ' deactivated. They can no longer log in.'));
    }
}
