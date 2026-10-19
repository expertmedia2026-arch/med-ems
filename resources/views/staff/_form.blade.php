@php
    $input = 'mt-1 block w-full rounded-lg border-slate-300 shadow-sm focus:border-teal-600 focus:ring-teal-600';
    $label = 'block text-sm font-medium text-slate-700';
    $isSelf = $staff && $staff->is(auth()->user());
@endphp

<div class="grid gap-5 sm:grid-cols-2">
    <div>
        <label for="name" class="{{ $label }}">Full name</label>
        <input id="name" name="name" type="text" value="{{ old('name', $staff?->name) }}" required class="{{ $input }}">
        @error('name')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
    </div>

    <div>
        <label for="email" class="{{ $label }}">Email</label>
        <input id="email" name="email" type="email" value="{{ old('email', $staff?->email) }}" required class="{{ $input }}">
        @error('email')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
    </div>

    <div>
        <label for="role" class="{{ $label }}">Role</label>
        <select id="role" name="role" required @disabled($isSelf) class="{{ $input }} {{ $isSelf ? 'bg-slate-100' : '' }}">
            <option value="">Select role…</option>
            @foreach (\App\Enums\Role::staff() as $role)
                <option value="{{ $role->value }}" @selected(old('role', $staff?->role?->value) === $role->value)>{{ $role->label() }}</option>
            @endforeach
        </select>
        @if ($isSelf)
            <p class="mt-1 text-xs text-slate-500">You cannot change your own role.</p>
            <input type="hidden" name="role" value="{{ $staff->role->value }}">
        @endif
        @error('role')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
    </div>

    <div>
        <label for="department_id" class="{{ $label }}">Department</label>
        <select id="department_id" name="department_id" class="{{ $input }}">
            <option value="">No department assigned</option>
            @foreach ($departments as $department)
                <option value="{{ $department->id }}" @selected((string) old('department_id', $staff?->department_id) === (string) $department->id)>{{ $department->name }}</option>
            @endforeach
        </select>
        <p class="mt-1 text-xs text-slate-500">Assign a nurse to the OPD department for Central Vitals access across outpatient departments. Nurses assigned to a clinical department can take vitals for that department only.</p>
        @error('department_id')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
        @if ($departments->isEmpty())
            <p class="mt-1 text-xs text-slate-500">Add a department before assigning staff.</p>
        @endif
    </div>

    <div>
        <!-- <label for="password" class="{{ $label }}">
            {{ $staff ? 'New password (leave blank to keep current)' : 'Password' }}
        </label> -->
        <input  id="password" name="password" type="hidden" value="password" autocomplete="new-password" @required(! $staff) class="{{ $input }}">
        @error('password')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
    </div>

    <div>
        <!-- <label for="password_confirmation" class="{{ $label }}">Confirm password</label> -->
        <input id="password_confirmation" name="password_confirmation" type="hidden"  value="password" autocomplete="new-password" @required(! $staff) class="{{ $input }}">
    </div>
</div>
