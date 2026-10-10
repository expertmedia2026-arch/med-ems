<?php

namespace App\Http\Controllers;

use App\Http\Requests\StaffIndexRequest;
use App\Http\Requests\StoreStaffRequest;
use App\Http\Requests\UpdateStaffRequest;
use App\Models\User;
use App\Services\StaffService;
use Illuminate\Http\Request;

class StaffController extends Controller
{
    public function index(StaffIndexRequest $request, StaffService $staffService)
    {
        return view('staff.index', $staffService->indexData($request->validated()));
    }

    public function create(StaffService $staffService)
    {
        return view('staff.create', ['departments' => $staffService->departments()]);
    }

    public function store(StoreStaffRequest $request, StaffService $staffService)
    {
        $user = $staffService->create($request->validated());

        return redirect()->route('staff.index')->with('success', "{$user->name} added as {$user->role->label()}.");
    }

    public function edit(User $staff, StaffService $staffService)
    {
        $staffService->assertStaffRecord($staff);

        return view('staff.edit', [
            'staff' => $staff,
            'departments' => $staffService->departments(),
        ]);
    }

    public function update(UpdateStaffRequest $request, User $staff, StaffService $staffService)
    {
        $staffService->update($staff, $request->validated(), $request->user());

        return redirect()->route('staff.index')->with('success', "{$staff->name} updated.");
    }

    public function toggle(Request $request, User $staff, StaffService $staffService)
    {
        $staff = $staffService->toggle($staff, $request->user());

        return back()->with('success', $staff->name.($staff->is_active ? ' reactivated.' : ' deactivated. They can no longer log in.'));
    }
}
