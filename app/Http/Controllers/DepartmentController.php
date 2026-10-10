<?php

namespace App\Http\Controllers;

use App\Http\Requests\SaveDepartmentRequest;
use App\Models\Department;
use App\Services\DepartmentService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;

class DepartmentController extends Controller
{
    public function index(DepartmentService $departments): View
    {
        $records = $departments->index();

        return view('departments.index', ['departments' => $records]);
    }

    public function create(): View
    {
        return view('departments.create');
    }

    public function store(SaveDepartmentRequest $request, DepartmentService $departments): RedirectResponse
    {
        $departments->create($request->validated());

        return redirect()->route('departments.index')->with('success', 'Department added.');
    }

    public function edit(Department $department): View
    {
        return view('departments.edit', compact('department'));
    }

    public function update(SaveDepartmentRequest $request, Department $department, DepartmentService $departments): RedirectResponse
    {
        $departments->update($department, $request->validated());

        return redirect()->route('departments.index')->with('success', 'Department updated.');
    }

    public function destroy(Department $department, DepartmentService $departments): RedirectResponse
    {
        if (! $departments->canDelete($department)) {
            return back()->with('error', 'Remove staff assignments, pending invitations, and consultation types before deleting this department.');
        }

        $departments->delete($department);

        return redirect()->route('departments.index')->with('success', 'Department deleted.');
    }
}
