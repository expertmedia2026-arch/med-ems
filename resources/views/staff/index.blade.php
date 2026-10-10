<x-app-layout>
    <x-slot name="header">Staff</x-slot>

    @if (session('invite_link'))
        <div class="mb-6 rounded-lg border border-blue-200 bg-blue-50 px-4 py-3 text-sm text-blue-900" x-data="{ copied: false }">
            <p class="font-medium">Invitation link (valid for 7 days, single use)</p>
            <div class="mt-2 flex gap-2">
                <input readonly value="{{ session('invite_link') }}" x-ref="link"
                       class="flex-1 rounded-lg border-blue-200 bg-white text-xs">
                <button type="button"
                        @click="navigator.clipboard.writeText($refs.link.value); copied = true"
                        class="rounded-lg bg-blue-700 px-4 text-xs font-medium text-white hover:bg-blue-800">
                    <span x-text="copied ? 'Copied' : 'Copy'"></span>
                </button>
            </div>
        </div>
    @endif

    <div class="mb-6 flex flex-wrap items-center gap-3">
        <form method="GET" class="flex flex-1 flex-wrap items-center gap-3">
            <input type="search" name="q" value="{{ request('q') }}" placeholder="Search name or email"
                   class="w-full rounded-lg border-slate-300 shadow-sm focus:border-teal-600 focus:ring-teal-600 sm:max-w-xs">

            <select name="role" class="rounded-lg border-slate-300 text-sm shadow-sm focus:border-teal-600 focus:ring-teal-600">
                <option value="">All roles</option>
                @foreach (\App\Enums\Role::staff() as $role)
                    <option value="{{ $role->value }}" @selected(request('role') === $role->value)>{{ $role->label() }}</option>
                @endforeach
            </select>

            <select name="department_id" class="rounded-lg border-slate-300 text-sm shadow-sm focus:border-teal-600 focus:ring-teal-600">
                <option value="">All departments</option>
                @foreach ($departments as $department)
                    <option value="{{ $department->id }}" @selected((string) request('department_id') === (string) $department->id)>{{ $department->name }}</option>
                @endforeach
            </select>

            <select name="status" class="rounded-lg border-slate-300 text-sm shadow-sm focus:border-teal-600 focus:ring-teal-600">
                <option value="">Active and inactive</option>
                <option value="active" @selected(request('status') === 'active')>Active</option>
                <option value="inactive" @selected(request('status') === 'inactive')>Inactive</option>
            </select>

            <button class="rounded-lg bg-teal-700 px-4 py-2 text-sm font-medium text-white hover:bg-teal-800">Filter</button>
        </form>

        <a href="{{ route('staff.invite') }}"
           class="rounded-lg border border-teal-700 px-4 py-2 text-sm font-medium text-teal-700 hover:bg-teal-50">Invite by email</a>
        <a href="{{ route('staff.create') }}"
           class="rounded-lg bg-teal-700 px-4 py-2 text-sm font-medium text-white hover:bg-teal-800">+ Add staff</a>
    </div>

    {{-- Pending invitations --}}
    @if ($invitations->isNotEmpty())
        <div class="mb-8 rounded-xl border border-blue-200 bg-white shadow-sm">
            <h3 class="border-b border-blue-200 bg-blue-50 px-5 py-3 font-semibold text-blue-900">
                Pending invitations ({{ $invitations->count() }})
            </h3>
            <ul class="divide-y text-sm">
                @foreach ($invitations as $invite)
                    <li class="flex flex-wrap items-center justify-between gap-3 px-5 py-3">
                        <div>
                            <p class="font-medium">{{ $invite->email }}
                                <x-badge color="blue" class="ml-2">{{ $invite->role->label() }}</x-badge>
                                @if ($invite->department)
                                    <x-badge color="slate" class="ml-1">{{ $invite->department->name }}</x-badge>
                                @endif
                            </p>
                            <p class="text-xs text-slate-400">
                                Invited by {{ $invite->inviter?->name }} · expires {{ $invite->expires_at->diffForHumans() }}
                            </p>
                        </div>
                        <div class="flex items-center gap-4">
                            <form method="POST" action="{{ route('staff.invitations.resend', $invite) }}">
                                @csrf
                                <button class="text-teal-700 hover:underline">Resend</button>
                            </form>
                            <form method="POST" action="{{ route('staff.invitations.destroy', $invite) }}"
                                  onsubmit="return confirm('Revoke this invitation?')">
                                @csrf @method('DELETE')
                                <button class="text-red-600 hover:underline">Revoke</button>
                            </form>
                        </div>
                    </li>
                @endforeach
            </ul>
        </div>
    @endif

    {{-- Staff list --}}
    <div class="overflow-x-auto rounded-xl border bg-white shadow-sm">
        <table class="min-w-full divide-y divide-slate-200 text-sm">
            <thead class="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500">
                <tr>
                    <th class="px-4 py-3">Name</th>
                    <th class="px-4 py-3">Role</th>
                    <th class="px-4 py-3">Department</th>
                    <th class="px-4 py-3">Status</th>
                    <th class="px-4 py-3">Joined</th>
                    <th class="px-4 py-3"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($staff as $member)
                    <tr class="{{ $member->is_active ? '' : 'bg-slate-50 text-slate-400' }}">
                        <td class="px-4 py-3">
                            <span class="font-medium">{{ $member->name }}</span>
                            @if ($member->is(auth()->user()))<span class="ml-1 text-xs text-teal-700">(you)</span>@endif
                            <span class="block text-xs text-slate-400">{{ $member->email }}</span>
                        </td>
                        <td class="px-4 py-3"><x-badge color="blue">{{ $member->role->label() }}</x-badge></td>
                        <td class="px-4 py-3">{{ $member->department?->name ?? '—' }}</td>
                        <td class="px-4 py-3"><x-badge :color="$member->is_active ? 'green' : 'slate'">{{ $member->is_active ? 'Active' : 'Inactive' }}</x-badge></td>
                        <td class="px-4 py-3">{{ $member->created_at->format('d M Y') }}</td>
                        <td class="px-4 py-3">
                            <div class="flex justify-end gap-4">
                                <a href="{{ route('staff.edit', $member) }}" class="text-teal-700 hover:underline">Edit</a>
                                @unless ($member->is(auth()->user()))
                                    <form method="POST" action="{{ route('staff.toggle', $member) }}">
                                        @csrf @method('PATCH')
                                        <button class="{{ $member->is_active ? 'text-red-600' : 'text-green-700' }} hover:underline">
                                            {{ $member->is_active ? 'Deactivate' : 'Activate' }}
                                        </button>
                                    </form>
                                @endunless
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="px-4 py-10 text-center text-slate-500">No staff found.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">{{ $staff->links() }}</div>
</x-app-layout>
