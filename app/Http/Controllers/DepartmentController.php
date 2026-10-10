<?php

namespace App\Http\Controllers;

use App\Enums\Role;
use App\Models\Department;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class DepartmentController extends Controller
{
    public function index(): View
    {
        $departments = Department::withCount([
            'users' => fn ($query) => $query->where('role', '!=', Role::Patient->value),
            'staffInvitations' => fn ($query) => $query->pending(),
        ])->orderBy('name')->paginate(15);

        return view('departments.index', compact('departments'));
    }

    public function create(): View
    {
        return view('departments.create');
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120', 'unique:departments,name'],
            'description' => ['nullable', 'string', 'max:500'],
        ]);

        Department::create($data);

        return redirect()->route('departments.index')->with('success', 'Department added.');
    }

    public function edit(Department $department): View
    {
        return view('departments.edit', compact('department'));
    }

    public function update(Request $request, Department $department): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120', Rule::unique('departments', 'name')->ignore($department->id)],
            'description' => ['nullable', 'string', 'max:500'],
        ]);

        $department->update($data);

        return redirect()->route('departments.index')->with('success', 'Department updated.');
    }

    public function destroy(Department $department): RedirectResponse
    {
        if ($department->users()->exists() || $department->staffInvitations()->pending()->exists()) {
            return back()->with('error', 'Remove staff assignments and pending invitations before deleting this department.');
        }

        $department->delete();

        return redirect()->route('departments.index')->with('success', 'Department deleted.');
    }
}
