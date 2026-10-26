<x-app-layout>
    <x-slot name="header">Department services</x-slot>

    @include('lab._tabs')

    <div class="mb-6"><x-csv-transfer-actions dataset="lab-catalog" label="department services and procedures" /></div>

    <form method="GET" class="mb-6 flex flex-wrap items-center gap-3">
        <input type="search" name="q" value="{{ request('q') }}" placeholder="Search service name or code"
               class="w-full rounded-lg border-slate-300 shadow-sm focus:border-teal-600 focus:ring-teal-600 sm:max-w-xs">

        <select name="category" class="rounded-lg border-slate-300 text-sm shadow-sm focus:border-teal-600 focus:ring-teal-600">
            <option value="">All categories</option>
            @foreach ($categories as $cat)
                <option value="{{ $cat }}" @selected(request('category') === $cat)>{{ $cat }}</option>
            @endforeach
        </select>

        <select name="status" class="rounded-lg border-slate-300 text-sm shadow-sm focus:border-teal-600 focus:ring-teal-600">
            <option value="">Active and inactive</option>
            <option value="active" @selected(request('status') === 'active')>Active only</option>
            <option value="inactive" @selected(request('status') === 'inactive')>Inactive only</option>
        </select>

        @if (auth()->user()->hasRole('admin'))
            <select name="department_id" class="rounded-lg border-slate-300 text-sm shadow-sm focus:border-teal-600 focus:ring-teal-600">
                <option value="">All departments</option>
                @foreach ($departments as $department)
                    <option value="{{ $department->id }}" @selected((string) request('department_id') === (string) $department->id)>{{ $department->name }}</option>
                @endforeach
            </select>
        @endif

        <button class="rounded-lg bg-teal-700 px-4 py-2 text-sm font-medium text-white hover:bg-teal-800">Filter</button>

        <a href="{{ route('lab.tests.create') }}"
           class="rounded-lg border border-teal-700 px-4 py-2 text-sm font-medium text-teal-700 hover:bg-teal-50 sm:ml-auto">
            + Add service
        </a>
    </form>

    <div class="overflow-x-auto rounded-xl border bg-white shadow-sm">
        <table class="min-w-full divide-y divide-slate-200 text-sm">
            <thead class="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500">
                <tr>
                    <th class="px-4 py-3">Service / procedure</th>
                    <th class="px-4 py-3">Department</th>
                    <th class="px-4 py-3">Category</th>
                    <th class="px-4 py-3">Sample</th>
                    <th class="px-4 py-3">Turnaround</th>
                    <th class="px-4 py-3 text-right">Price</th>
                    <th class="px-4 py-3">Status</th>
                    <th class="px-4 py-3"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($tests as $test)
                    <tr class="{{ $test->is_active ? '' : 'bg-slate-50 text-slate-400' }}">
                        <td class="px-4 py-3">
                            <span class="font-medium">{{ $test->name }}</span>
                            @if ($test->code)
                                <span class="ml-1 font-mono text-xs text-slate-400">{{ $test->code }}</span>
                            @endif
                            @if ($test->description)
                                <span class="block text-xs text-slate-400">{{ \Illuminate\Support\Str::limit($test->description, 60) }}</span>
                            @endif
                        </td>
                        <td class="px-4 py-3">{{ $test->department?->name ?? 'Unassigned' }}</td>
                        <td class="px-4 py-3">{{ $test->category ?: '—' }}</td>
                        <td class="px-4 py-3">{{ $test->sample_type ?: '—' }}</td>
                        <td class="px-4 py-3">{{ $test->turnaround_hours ? $test->turnaround_hours . ' hr' . ($test->turnaround_hours > 1 ? 's' : '') : '—' }}</td>
                        <td class="px-4 py-3 text-right font-medium">₦{{ number_format($test->price, 2) }}</td>
                        <td class="px-4 py-3">
                            <x-badge :color="$test->is_active ? 'green' : 'slate'">{{ $test->is_active ? 'Active' : 'Inactive' }}</x-badge>
                        </td>
                        <td class="px-4 py-3">
                            <div class="flex justify-end gap-4">
                                <a href="{{ route('lab.tests.edit', $test) }}" class="text-teal-700 hover:underline">Edit</a>
                                <form method="POST" action="{{ route('lab.tests.toggle', $test) }}">
                                    @csrf @method('PATCH')
                                    <button class="{{ $test->is_active ? 'text-red-600' : 'text-green-700' }} hover:underline">
                                        {{ $test->is_active ? 'Deactivate' : 'Activate' }}
                                    </button>
                                </form>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="8" class="px-4 py-10 text-center text-slate-500">No services found.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">{{ $tests->links() }}</div>
</x-app-layout>
