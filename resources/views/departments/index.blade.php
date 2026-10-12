<x-app-layout>
    <x-slot name="header">Departments</x-slot>

    <div class="mb-6"><x-csv-transfer-actions dataset="departments" label="departments" /></div>

    <div class="mb-6 flex flex-wrap items-center justify-between gap-3">
        <p class="text-sm text-slate-600">Manage hospital departments and assign staff from the staff form.</p>
        <a href="{{ route('departments.create') }}" class="rounded-lg bg-teal-700 px-4 py-2 text-sm font-medium text-white hover:bg-teal-800">+ Add department</a>
    </div>

    <div class="overflow-x-auto rounded-xl border bg-white shadow-sm">
        <table class="min-w-full divide-y divide-slate-200 text-sm">
            <thead class="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500">
                <tr>
                    <th class="px-4 py-3">Department</th>
                    <th class="px-4 py-3">Staff</th>
                    <th class="px-4 py-3">Consultation types</th>
                    <th class="px-4 py-3"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($departments as $department)
                    <tr>
                        <td class="px-4 py-3">
                            <span class="font-medium">{{ $department->name }}</span>
                            @if ($department->description)
                                <span class="mt-1 block text-xs text-slate-500">{{ $department->description }}</span>
                            @endif
                        </td>
                        <td class="px-4 py-3">
                            <span>{{ $department->users_count }} assigned</span>
                            @if ($department->staff_invitations_count)
                                <span class="block text-xs text-slate-500">{{ $department->staff_invitations_count }} pending {{ \Illuminate\Support\Str::plural('invitation', $department->staff_invitations_count) }}</span>
                            @endif
                        </td>
                        <td class="px-4 py-3">{{ $department->consultation_types_count }}</td>
                        <td class="px-4 py-3">
                            <div class="flex justify-end gap-4">
                                <a href="{{ route('departments.edit', $department) }}" class="text-teal-700 hover:underline">Edit</a>
                                @if ($department->users_count === 0 && $department->staff_invitations_count === 0 && $department->consultation_types_count === 0)
                                    <form method="POST" action="{{ route('departments.destroy', $department) }}" onsubmit="return confirm('Delete this department?')">
                                        @csrf
                                        @method('DELETE')
                                        <button class="text-red-600 hover:underline">Delete</button>
                                    </form>
                                @else
                                    <span class="cursor-not-allowed text-slate-300" title="Remove staff assignments, pending invitations, and consultation types first">Delete</span>
                                @endif
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="px-4 py-10 text-center text-slate-500">No departments added yet.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">{{ $departments->links() }}</div>
</x-app-layout>
