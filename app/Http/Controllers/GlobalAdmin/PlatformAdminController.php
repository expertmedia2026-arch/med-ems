<?php

namespace App\Http\Controllers\GlobalAdmin;

use App\Enums\Role;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class PlatformAdminController extends Controller
{
    public function index(): View
    {
        return view('global-admin.admins.index', [
            'admins' => User::withoutGlobalScope('hospital')
                ->where('is_global_admin', true)
                ->whereNull('hospital_id')
                ->orderBy('name')
                ->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $admin = new User;
        $admin->name = $data['name'];
        $admin->email = $data['email'];
        $admin->role = Role::Admin;
        $admin->hospital_id = null;
        $admin->department_id = null;
        $admin->is_global_admin = true;
        $admin->is_active = true;
        $admin->email_verified_at = now();
        $admin->password = $data['password'];
        $admin->save();

        return redirect()->route('global-admin.admins.index')
            ->with('success', "Platform admin {$admin->name} was added. They can sign in with the password you set.");
    }

    public function toggle(Request $request, User $user): RedirectResponse
    {
        abort_unless($user->is_global_admin && $user->hospital_id === null, 404);
        abort_if((int) $request->user()->id === (int) $user->id, 422, 'You cannot deactivate your own platform admin account.');

        DB::table('users')->where('id', $user->id)->update([
            'is_active' => ! $user->is_active,
            'updated_at' => now(),
        ]);

        $status = $user->is_active ? 'deactivated' : 'activated';

        return redirect()->route('global-admin.admins.index')->with('success', "Platform admin {$user->name} was {$status}.");
    }
}
