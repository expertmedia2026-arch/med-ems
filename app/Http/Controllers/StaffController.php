<?php

namespace App\Http\Controllers;

use App\Enums\Role;
use App\Models\Department;
use App\Models\StaffInvitation;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class StaffController extends Controller
{
    public function index(Request $request)
    {
        $staff = User::query()
            ->with('department')
            ->where('role', '!=', Role::Patient->value)
            ->when($request->filled('q'), function ($q) use ($request) {
                $term = $request->query('q');
                $q->where(fn ($q) => $q->whereLike('name', "%{$term}%")->orWhereLike('email', "%{$term}%"));
            })
            ->when($request->filled('role'), fn ($q) => $q->where('role', $request->query('role')))
            ->when($request->filled('department_id'), fn ($q) => $q->where('department_id', $request->query('department_id')))
            ->when($request->query('status') === 'active', fn ($q) => $q->where('is_active', true))
            ->when($request->query('status') === 'inactive', fn ($q) => $q->where('is_active', false))
            ->orderBy('name')
            ->paginate(15)
            ->withQueryString();

        return view('staff.index', [
            'staff' => $staff,
            'departments' => Department::orderBy('name')->get(),
            'invitations' => StaffInvitation::pending()->with(['inviter', 'department'])->latest()->get(),
        ]);
    }

    public function create()
    {
        return view('staff.create', ['departments' => Department::orderBy('name')->get()]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'role' => ['required', Rule::enum(Role::class)->except([Role::Patient])],
            'department_id' => ['nullable', 'integer', 'exists:departments,id'],
            'password' => ['required', 'confirmed', Password::defaults()],
        ]);

        $user = User::create($data + ['is_active' => true, 'email_verified_at' => now()]);

        // Any open invitation for this address is now pointless
        StaffInvitation::where('email', $user->email)->delete();

        return redirect()->route('staff.index')->with('success', "{$user->name} added as {$user->role->label()}.");
    }

    public function edit(User $staff)
    {
        abort_if($staff->role === Role::Patient, 404);

        return view('staff.edit', [
            'staff' => $staff,
            'departments' => Department::orderBy('name')->get(),
        ]);
    }

    public function update(Request $request, User $staff)
    {
        abort_if($staff->role === Role::Patient, 404);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($staff->id)],
            'role' => ['required', Rule::enum(Role::class)->except([Role::Patient])],
            'department_id' => ['nullable', 'integer', 'exists:departments,id'],
            'password' => ['nullable', 'confirmed', Password::defaults()],
        ]);

        // Admins can't change their own role (prevents locking everyone out)
        if ($staff->is($request->user())) {
            $data['role'] = $staff->role->value;
        }

        if (empty($data['password'])) {
            unset($data['password']);
        }

        $staff->update($data);

        return redirect()->route('staff.index')->with('success', "{$staff->name} updated.");
    }

    public function toggle(Request $request, User $staff)
    {
        abort_if($staff->role === Role::Patient, 404);
        abort_if($staff->is($request->user()), 403, 'You cannot deactivate your own account.');

        $staff->update(['is_active' => ! $staff->is_active]);

        return back()->with('success', $staff->name.($staff->is_active ? ' reactivated.' : ' deactivated. They can no longer log in.'));
    }
}
