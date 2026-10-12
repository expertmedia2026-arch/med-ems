<x-app-layout>
    <x-slot name="header">My consultation history</x-slot>

    <div class="mb-5 rounded-xl border bg-white p-4 shadow-sm">
        <form method="GET" action="{{ route('consultations.history.index') }}" class="flex flex-wrap items-end gap-3">
            <div class="min-w-64 flex-1">
                <label for="search" class="mb-1 block text-sm font-medium text-slate-700">Find a patient</label>
                <input id="search" name="search" value="{{ request('search') }}" placeholder="Patient name or hospital number" class="w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-teal-600 focus:ring-teal-600">
            </div>
            <button class="rounded-lg bg-teal-700 px-4 py-2 text-sm font-medium text-white hover:bg-teal-800">Search</button>
            @if (request('search'))<a href="{{ route('consultations.history.index') }}" class="px-2 py-2 text-sm text-slate-600 hover:underline">Clear</a>@endif
        </form>
    </div>

    <div class="overflow-x-auto rounded-xl border bg-white shadow-sm">
        <table class="min-w-full divide-y divide-slate-200 text-sm">
            <thead class="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500">
                <tr><th class="px-4 py-3">Patient</th><th class="px-4 py-3">Department</th><th class="px-4 py-3">Diagnosis</th><th class="px-4 py-3">Consultation date</th><th class="px-4 py-3"></th></tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($visits as $visit)
                    <tr>
                        <td class="px-4 py-3">
                            <span class="font-medium">{{ $visit->patient->full_name }}</span>
                            <span class="block font-mono text-xs text-slate-400">{{ $visit->patient->hospital_number }}</span>
                        </td>
                        <td class="px-4 py-3">{{ $visit->department?->name ?? '—' }}</td>
                        <td class="px-4 py-3">{{ $visit->diagnosis ?: '—' }}</td>
                        <td class="px-4 py-3">{{ $visit->closed_at?->format('d M Y, g:i A') ?? '—' }}</td>
                        <td class="px-4 py-3 text-right"><a href="{{ route('consultations.history', $visit) }}" class="font-medium text-teal-700 hover:underline">View consultation →</a></td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="px-4 py-10 text-center text-slate-500">No completed consultations match your search.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-5">{{ $visits->links() }}</div>
</x-app-layout>
