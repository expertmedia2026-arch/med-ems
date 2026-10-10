<x-portal-layout>
    <x-slot name="header">My Visits</x-slot>

    <div class="space-y-3">
        @forelse ($visits as $visit)
            <a href="{{ route('portal.visits.show', $visit) }}"
               class="block rounded-xl border bg-white p-5 shadow-sm transition hover:border-teal-600 hover:shadow">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <p class="font-semibold">{{ $visit->checked_in_at->format('D, d M Y') }}
                            <span class="font-normal text-slate-400">· {{ $visit->checked_in_at->format('g:i A') }}</span>
                        </p>
                        <p class="mt-1 text-sm text-slate-500">
                            {{ $visit->isLabOnly() ? 'Laboratory visit' : 'Consultation' }}
                            @if ($visit->doctor) · {{ $visit->doctor->name }} @endif
                        </p>
                    </div>
                    <x-badge :color="$visit->status->color()">{{ $visit->status->label() }}</x-badge>
                </div>

                @if ($visit->diagnosis)
                    <p class="mt-3 text-sm text-slate-700"><span class="text-slate-500">Diagnosis:</span> {{ $visit->diagnosis }}</p>
                @endif

                <p class="mt-3 text-xs text-slate-400">
                    {{ $visit->lab_requests_count }} lab test(s) · {{ $visit->prescriptions_count }} drug(s) prescribed
                </p>
            </a>
        @empty
            <div class="rounded-xl border bg-white p-10 text-center text-sm text-slate-500">
                You have no visits yet.
            </div>
        @endforelse
    </div>

    <div class="mt-6">{{ $visits->links() }}</div>
</x-portal-layout>