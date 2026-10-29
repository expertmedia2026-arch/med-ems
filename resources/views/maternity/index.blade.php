<x-app-layout>
    <x-slot name="header">Maternity episodes</x-slot>

    <div class="space-y-5">
        {{-- Each row is an admission-linked episode; starting labor begins from the admission page. --}}
        <div>
            <h2 class="text-xl font-semibold text-slate-900">Maternity episodes</h2>
            <p class="mt-1 text-sm text-slate-600">Labor and birth episodes linked to inpatient admissions.</p>
        </div>
        <section class="overflow-hidden rounded-xl border bg-white shadow-sm">
            <div class="divide-y divide-slate-100">
                @forelse ($episodes as $episode)
                    <a href="{{ route('maternity.episodes.show', $episode) }}" class="flex flex-wrap items-center justify-between gap-3 p-4 hover:bg-slate-50 sm:p-5">
                        <span>
                            <span class="block font-semibold text-slate-900">{{ $episode->admission->patient->full_name }}</span>
                            <span class="mt-1 block text-xs text-slate-500">{{ $episode->admission->patient->hospital_number }} · {{ $episode->admission->admission_number }}</span>
                        </span>
                        <span class="flex items-center gap-3">
                            <span class="rounded-full bg-pink-50 px-3 py-1 text-xs font-semibold capitalize text-pink-800">{{ str_replace('_', ' ', $episode->status) }}</span>
                            <span class="text-xs text-slate-500">{{ $episode->labor_started_at->format('d M Y, g:i A') }}</span>
                        </span>
                    </a>
                @empty
                    <p class="p-8 text-center text-sm text-slate-500">No maternity episodes yet. Start one from an active admission.</p>
                @endforelse
            </div>
            <div class="border-t px-4 py-3">{{ $episodes->links() }}</div>
        </section>
    </div>
</x-app-layout>
