<?php

namespace App\Http\Controllers;

use App\Http\Requests\SaveDepartmentRequest;
use App\Models\Department;
use App\Services\DepartmentService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use App\Enums\Role;

/**
 * Handles department administration and delegates creation, updates, and deletion to DepartmentService.
 */
class DepartmentController extends Controller
{
    /**
     * Loads department records through the service and renders the management list.
     */
    public function index(DepartmentService $departments): View
    {
        $records = $departments->index();

        return view('departments.index', ['departments' => $records]);
    }

    public function create(): View
    {
        return view('departments.create');
    }

    /**
     * Creates a department from authorized and validated form input, then redirects to the list.
     */
    public function store(SaveDepartmentRequest $request, DepartmentService $departments): RedirectResponse
    {
        $departments->create($request->validated());

        return redirect()->route('departments.index')->with('success', 'Department added.');
    }

    public function edit(Department $department): View
    {
        $hodOptions = $department->users()->where('is_active', true)->where('role', '!=', Role::Patient->value)->orderBy('name')->get();

        return view('departments.edit', compact('department', 'hodOptions'));
    }

    /**
     * Applies validated department changes through the service and redirects to the list.
     */
    public function update(SaveDepartmentRequest $request, Department $department, DepartmentService $departments): RedirectResponse
    {
        $departments->update($department, $request->validated());

        return redirect()->route('departments.index')->with('success', 'Department updated.');
    }

    /**
     * Checks service deletion rules before deleting the department or returning an explanatory error.
     */
    public function destroy(Department $department, DepartmentService $departments): RedirectResponse
    {
        if (! $departments->canDelete($department)) {
            return back()->with('error', 'Remove staff assignments, pending invitations, and consultation types before deleting this department.');
        }

        $departments->delete($department);

        return redirect()->route('departments.index')->with('success', 'Department deleted.');
    }
}
