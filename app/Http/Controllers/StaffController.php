<?php

namespace App\Http\Controllers;

use App\Enums\Role;
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
            ->when($request->filled('q'), function ($q) use ($request) {
                $term = $request->query('q');
                $q->where(fn ($q) => $q->where('name', 'like', "%{$term}%")->orWhere('email', 'like', "%{$term}%"));
            })
            ->when($request->filled('role'), fn ($q) => $q->where('role', $request->query('role')))
            ->when($request->query('status') === 'active', fn ($q) => $q->where('is_active', true))
            ->when($request->query('status') === 'inactive', fn ($q) => $q->where('is_active', false))
            ->orderBy('name')
            ->paginate(15)
            ->withQueryString();

        return view('staff.index', [
            'staff'       => $staff,
            'invitations' => StaffInvitation::pending()->with('inviter')->latest()->get(),
        ]);
    }

    public function create()
    {
        return view('staff.create');
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name'     => ['required', 'string', 'max:150'],
            'email'    => ['required', 'email', 'max:255', 'unique:users,email'],
            'role'     => ['required', Rule::enum(Role::class)],
            'password' => ['required', 'confirmed', Password::defaults()],
        ]);

        $user = User::create($data + ['is_active' => true, 'email_verified_at' => now()]);

        // Any open invitation for this address is now pointless
        StaffInvitation::where('email', $user->email)->delete();

        return redirect()->route('staff.index')->with('success', "{$user->name} added as {$user->role->label()}.");
    }

    public function edit(User $staff)
    {
        return view('staff.edit', ['staff' => $staff]);
    }

    public function update(Request $request, User $staff)
    {
        $data = $request->validate([
            'name'     => ['required', 'string', 'max:150'],
            'email'    => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($staff->id)],
            'role'     => ['required', Rule::enum(Role::class)],
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
        abort_if($staff->is($request->user()), 403, 'You cannot deactivate your own account.');

        $staff->update(['is_active' => ! $staff->is_active]);

        return back()->with('success', $staff->name . ($staff->is_active ? ' reactivated.' : ' deactivated. They can no longer log in.'));
    }
}