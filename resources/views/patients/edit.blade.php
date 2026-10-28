<x-app-layout>
    <x-slot name="header">Edit {{ $patient->full_name }}</x-slot>

    <form method="POST" action="{{ route('patients.update', $patient) }}" enctype="multipart/form-data" class="w-full min-w-0 space-y-5 rounded-2xl border border-slate-200 bg-slate-50/60 p-3 shadow-sm sm:space-y-6 sm:p-5 lg:p-7">
        @csrf
        @method('PUT')
        @if ($patient->identity_status === 'unidentified')
            <div class="mb-5 rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900">
                This is a temporary A&E identity. Enter verified patient details and save to update the record while keeping hospital number {{ $patient->hospital_number }}.
            </div>
        @endif
        @include('patients._form')

        <div class="flex flex-wrap items-center justify-end gap-3 border-t border-slate-200 pt-5">
            <a href="{{ route('patients.show', $patient) }}" class="inline-flex min-h-11 items-center justify-center rounded-lg border border-slate-300 px-4 py-2.5 text-sm font-medium text-slate-700 transition hover:bg-white">Cancel</a>
            <button class="inline-flex min-h-11 min-w-40 items-center justify-center rounded-lg bg-teal-700 px-5 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-teal-800 focus:outline-none focus:ring-2 focus:ring-teal-600 focus:ring-offset-2">Save changes</button>
        </div>
    </form>
</x-app-layout>
