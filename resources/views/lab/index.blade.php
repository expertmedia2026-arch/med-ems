<x-app-layout>
    <x-slot name="header">Laboratory</x-slot>
    @include('lab._tabs')

    <h2 class="mb-3 font-semibold">Pending requests ({{ $pending->count() }})</h2>
    <div class="mb-10 overflow-x-auto rounded-xl border bg-white shadow-sm">
        <table class="min-w-full divide-y divide-slate-200 text-sm">
            <thead class="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500">
                <tr>
                    <th class="px-4 py-3">Patient</th>
                    <th class="px-4 py-3">Test</th>
                    <th class="px-4 py-3">Requested by</th>
                    <th class="px-4 py-3">Waiting</th>
                    <th class="px-4 py-3"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($pending as $req)
                    <tr>
                        <td class="px-4 py-3">
                            <span class="font-medium">{{ $req->visit->patient->full_name }}</span>
                            <span class="block font-mono text-xs text-slate-400">{{ $req->visit->patient->hospital_number }}</span>
                        </td>
                        <td class="px-4 py-3 font-medium text-teal-800">{{ $req->test->name }}</td>
                        <td class="px-4 py-3">{{ $req->requester->name }}</td>
                        <td class="px-4 py-3">{{ $req->created_at->diffForHumans(short: true) }}</td>
                        
                        <td class="px-4 py-3 text-right">
                            @if ($canProcess && ! $req->is_paid)
                                <x-badge color="amber">Awaiting payment</x-badge>
                            @else
                                <a href="{{ route('lab.show', $req) }}"
                                class="rounded-lg {{ $canProcess ? 'bg-teal-700 text-white hover:bg-teal-800' : 'border border-teal-700 text-teal-700 hover:bg-teal-50' }} px-3 py-1.5 text-xs font-medium">
                                    {{ $canProcess ? 'Enter result' : 'View' }}
                                </a>
                            @endif
                        </td>
                        
                    </tr>
                @empty
                    <tr><td colspan="5" class="px-4 py-10 text-center text-slate-500">No pending lab requests.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

        <h2 class="mb-3 font-semibold">Recent results</h2>
        <div class="overflow-x-auto rounded-xl border bg-white shadow-sm">
            <table class="min-w-full divide-y divide-slate-200 text-sm">
                <thead class="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500">
                    <tr>
                        <th class="px-4 py-3">Patient</th>
                        <th class="px-4 py-3">Test</th>
                        <th class="px-4 py-3">Result</th>
                        <th class="px-4 py-3">Completed</th>
                        <th class="px-4 py-3"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse ($completed as $req)
                        <tr>
                            <td class="px-4 py-3 font-medium">{{ $req->visit->patient->full_name }}</td>
                            <td class="px-4 py-3">{{ $req->test->name }}</td>
                            <td class="px-4 py-3 text-slate-600">
                                {{ \Illuminate\Support\Str::limit($req->result, 50) }}
                                @if ($req->attachments_count)
                                    <span class="ml-1 text-xs text-teal-700">📎 {{ $req->attachments_count }}</span>
                                @endif
                            </td>
                            <td class="px-4 py-3">
                                {{ $req->completed_at->format('d M, g:i A') }}
                                <span class="block text-xs text-slate-400">{{ $req->processor?->name }}</span>
                            </td>
                            <td class="px-4 py-3 text-right">
                                <a href="{{ route('lab.show', $req) }}" class="text-teal-700 hover:underline">View</a>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="px-4 py-8 text-center text-slate-500">No results yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
</x-app-layout>