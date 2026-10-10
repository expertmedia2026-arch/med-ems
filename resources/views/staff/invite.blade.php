<x-app-layout>
    <x-slot name="header">Invite Staff</x-slot>

    @php
        $input = 'mt-1 block w-full rounded-lg border-slate-300 shadow-sm focus:border-teal-600 focus:ring-teal-600';
        $label = 'block text-sm font-medium text-slate-700';
    @endphp

    <form method="POST" action="{{ route('staff.invite.store') }}" class="max-w-xl space-y-5 rounded-xl border bg-white p-6 shadow-sm">
        @csrf

        <p class="text-sm text-slate-600">
            Enter their email, role, and department. They will receive a link to set their own name and password. The link is valid for 7 days.
        </p>

        <div>
            <label for="email" class="{{ $label }}">Email</label>
            <input id="email" name="email" type="email" value="{{ old('email') }}" required class="{{ $input }}">
            @error('email')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
        </div>

        <div>
            <label for="role" class="{{ $label }}">Role</label>
            <select id="role" name="role" required class="{{ $input }}">
                <option value="">Select role…</option>
                @foreach (\App\Enums\Role::staff() as $role)
                    <option value="{{ $role->value }}" @selected(old('role') === $role->value)>{{ $role->label() }}</option>
                @endforeach
            </select>
            @error('role')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
        </div>

        <div>
            <label for="department_id" class="{{ $label }}">Department</label>
            <select id="department_id" name="department_id" class="{{ $input }}">
                <option value="">No department assigned</option>
                @foreach ($departments as $department)
                    <option value="{{ $department->id }}" @selected((string) old('department_id') === (string) $department->id)>{{ $department->name }}</option>
                @endforeach
            </select>
            @error('department_id')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
        </div>

        <div class="flex items-center gap-3 pt-2">
            <button class="rounded-lg bg-teal-700 px-5 py-2.5 text-sm font-medium text-white hover:bg-teal-800">Send invitation</button>
            <a href="{{ route('staff.index') }}" class="text-sm text-slate-500 hover:underline">Cancel</a>
        </div>
    </form>
</x-app-layout>
