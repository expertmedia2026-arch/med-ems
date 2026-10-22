<x-app-layout>
    <x-slot name="header">Laboratory</x-slot>

    @include('lab._tabs')

    <form method="GET" action="{{ route('lab.index') }}" class="mb-6 rounded-xl border bg-white p-4 shadow-sm">
        <label for="patient-search" class="mb-2 block text-sm font-medium text-slate-700">Find patient</label>
        <div class="flex flex-col gap-2 sm:flex-row">
            <input id="patient-search" name="q" type="search" value="{{ $search }}"
                   placeholder="Name, phone number, or hospital number"
                   class="w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-teal-600 focus:ring-teal-600"
                   autocomplete="off">
            <button type="submit" class="rounded-lg bg-teal-700 px-4 py-2 text-sm font-medium text-white hover:bg-teal-800">
                Search
            </button>
            @if ($search !== '')
                <a href="{{ route('lab.index') }}" class="rounded-lg border border-slate-300 px-4 py-2 text-center text-sm font-medium text-slate-700 hover:bg-slate-50">
                    Clear
                </a>
            @endif
        </div>
    </form>

    @if (auth()->user()->hasRole('admin', 'lab'))
        <div class="mb-6">
            <a href="{{ route('lab.referrals.create') }}"
               class="inline-block rounded-lg border border-teal-700 px-4 py-2 text-sm font-medium text-teal-700 hover:bg-teal-50">
                + External lab request
            </a>
        </div>
    @endif

    {{-- Pending --}}
    <h2 class="mb-3 font-semibold">Pending requests ({{ $pending->total() }})</h2>
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

                            @if ($req->visit->isLabOnly())
                                <x-badge color="blue" class="mt-1">{{ $req->visit->referred_from ? 'Referral' : 'Walk-in' }}</x-badge>
                                @if ($req->visit->referred_from)
                                    <span class="block text-xs text-slate-400">From {{ $req->visit->referred_from }}</span>
                                @endif
                            @endif
                        </td>
                        <td class="px-4 py-3 font-medium text-teal-800">{{ $req->test->name }}</td>
                        <td class="px-4 py-3">{{ $req->requester->name }}</td>
                        <td class="px-4 py-3">{{ $req->created_at->diffForHumans(short: true) }}</td>
                        <td class="px-4 py-3 text-right">
                            @if ($canProcess && ! $req->is_paid && ! $req->visit->is_emergency)
                                <x-badge color="amber">Awaiting payment</x-badge>
                            @else
                                @if ($canProcess && $req->visit->is_emergency && ! $req->is_paid)
                                    <x-badge color="red">ER · bill later</x-badge>
                                @endif
                                <a href="{{ route('lab.show', $req) }}"
                                   class="rounded-lg {{ $canProcess ? 'bg-teal-700 text-white hover:bg-teal-800' : 'border border-teal-700 text-teal-700 hover:bg-teal-50' }} px-3 py-1.5 text-xs font-medium">
                                    {{ $canProcess ? 'Enter result' : 'View' }}
                                </a>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="px-4 py-10 text-center text-slate-500">{{ $search !== '' ? 'No pending requests match this patient search.' : 'No pending lab requests.' }}</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="mb-10">{{ $pending->links() }}</div>

    {{-- Recent results --}}
    <h2 class="mb-3 font-semibold">Recent results ({{ $completed->total() }})</h2>
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
                        <td class="px-4 py-3">
                            <span class="font-medium">{{ $req->visit->patient->full_name }}</span>

                            @if ($req->visit->isLabOnly())
                                <x-badge color="blue" class="ml-1">{{ $req->visit->referred_from ? 'Referral' : 'Walk-in' }}</x-badge>
                                @if ($req->visit->referred_from)
                                    <span class="block text-xs text-slate-400">From {{ $req->visit->referred_from }}</span>
                                @endif
                            @endif
                        </td>
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
                    <tr><td colspan="5" class="px-4 py-8 text-center text-slate-500">{{ $search !== '' ? 'No completed results match this patient search.' : 'No results yet.' }}</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="mt-4">{{ $completed->links() }}</div>
</x-app-layout>
