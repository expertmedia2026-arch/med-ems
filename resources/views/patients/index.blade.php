<x-app-layout>
    <x-slot name="header">Patients</x-slot>

    <div class="mb-6 flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
        <form method="GET" class="flex w-full min-w-0 gap-2 lg:max-w-md">
            <input type="search" name="q" value="{{ request('q') }}" placeholder="Search name, hospital no. or phone"
                   class="w-full rounded-lg border-slate-300 shadow-sm focus:border-teal-600 focus:ring-teal-600">
            <button class="rounded-lg bg-teal-700 px-4 text-sm font-medium text-white hover:bg-teal-800">Search</button>
        </form>

        @if (auth()->user()->hasRole('admin', 'receptionist'))
            <a href="{{ route('patients.create') }}"
               class="shrink-0 whitespace-nowrap rounded-lg bg-teal-700 px-4 py-2.5 text-center text-sm font-medium text-white hover:bg-teal-800">
                + Register patient
            </a>
        @endif
    </div>

    <div class="overflow-x-auto rounded-xl border bg-white shadow-sm">
        <table class="min-w-full divide-y divide-slate-200 whitespace-nowrap text-sm">
            <thead class="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500">
                <tr>
                    <th class="px-4 py-3">Hospital No.</th>
                    <th class="px-4 py-3">Name</th>
                    <th class="px-4 py-3">Gender / Age</th>
                    <th class="px-4 py-3">Phone</th>
                    <th class="px-4 py-3"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($patients as $patient)
                    <tr class="hover:bg-slate-50">
                        <td class="px-4 py-3 font-mono text-teal-800">{{ $patient->hospital_number }}</td>
                        <td class="px-4 py-3 font-medium">{{ $patient->full_name }}</td>
                        <td class="px-4 py-3">{{ ucfirst($patient->gender) }}, {{ $patient->age }}</td>
                        <td class="px-4 py-3">{{ $patient->phone ?: '—' }}</td>
                        <td class="px-4 py-3 text-right">
                            <a href="{{ route('patients.show', $patient) }}" class="text-teal-700 hover:underline">View</a>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="px-4 py-10 text-center text-slate-500">No patients found.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">{{ $patients->links() }}</div>
</x-app-layout>