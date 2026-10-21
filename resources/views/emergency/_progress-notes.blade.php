@php
    $noteUser = auth()->user();
    $activeErVisit = $visit->is_emergency && in_array($visit->status, [
        \App\Enums\VisitStatus::WaitingTriage,
        \App\Enums\VisitStatus::WaitingDoctor,
        \App\Enums\VisitStatus::InConsultation,
    ], true);
    $canAddErNote = $activeErVisit && $noteUser->canAccessEmergency() && (
        ($noteUser->hasRole('nurse')) || ($noteUser->hasRole('doctor') && $visit->isOpenFor($noteUser))
    );
    $noteLabel = $noteUser->hasRole('nurse') ? 'Nursing progress note' : 'Medical progress note';
    $noteInput = 'mt-1 block w-full rounded-lg border-slate-300 bg-white text-sm shadow-sm focus:border-teal-600 focus:ring-teal-600';
@endphp

@if ($visit->is_emergency)
    <section class="overflow-hidden rounded-xl border border-teal-100 bg-white shadow-sm">
        <div class="border-b border-teal-100 px-5 py-4 sm:px-7">
            <h2 class="font-semibold text-slate-900">ER progress notes</h2>
            <p class="mt-1 text-sm text-slate-500">Time-stamped medical and nursing updates stay with this emergency visit.</p>
        </div>
        @if ($canAddErNote)
            <form method="POST" action="{{ route('emergency.notes.store', $visit) }}" class="border-b bg-slate-50 p-5 sm:px-7">
                @csrf
                <label class="block text-sm font-medium text-slate-700">{{ $noteLabel }} <span class="text-red-600">*</span>
                    <textarea name="note" rows="3" required minlength="2" maxlength="5000" class="{{ $noteInput }}" placeholder="Record the clinical update, intervention, response, or handover">{{ old('note') }}</textarea>
                </label>
                @error('note')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                <div class="mt-3 flex justify-end"><button class="rounded-lg bg-teal-700 px-4 py-2 text-sm font-semibold text-white hover:bg-teal-800">Add progress note</button></div>
            </form>
        @endif
        <ol class="divide-y divide-slate-100">
            @forelse ($visit->emergencyProgressNotes as $note)
                <li class="flex gap-3 p-5 sm:px-7">
                    <span class="mt-1 h-2.5 w-2.5 shrink-0 rounded-full {{ $note->note_type === 'nursing' ? 'bg-slate-400' : 'bg-teal-600' }}"></span>
                    <div class="min-w-0 flex-1">
                        <div class="flex flex-wrap items-center justify-between gap-2">
                            <p class="text-sm font-semibold text-slate-900">{{ $note->note_type === 'nursing' ? 'Nursing note' : 'Medical note' }} · {{ $note->author?->name ?? 'Staff' }}</p>
                            <time class="text-xs text-slate-500">{{ $note->created_at->format('d M Y, g:i A') }}</time>
                        </div>
                        <p class="mt-2 whitespace-pre-line text-sm text-slate-700">{{ $note->note }}</p>
                    </div>
                </li>
            @empty
                <li class="px-5 py-8 text-center text-sm text-slate-500">No ER progress notes have been recorded yet.</li>
            @endforelse
        </ol>
    </section>
@endif
