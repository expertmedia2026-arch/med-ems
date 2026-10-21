<x-app-layout>
    <x-slot name="header">Edit {{ $patient->full_name }}</x-slot>

    <form method="POST" action="{{ route('patients.update', $patient) }}" enctype="multipart/form-data" class="max-w-3xl rounded-xl border bg-white p-6 shadow-sm">
        @csrf
        @method('PUT')
        @if ($patient->identity_status === 'unidentified')
            <div class="mb-5 rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900">
                This is a temporary ER identity. Enter verified patient details and save to update the record while keeping hospital number {{ $patient->hospital_number }}.
            </div>
        @endif
        @include('patients._form')

        <div class="mt-6 flex items-center gap-3">
            <button class="rounded-lg bg-teal-700 px-5 py-2.5 text-sm font-medium text-white hover:bg-teal-800">Save changes</button>
            <a href="{{ route('patients.show', $patient) }}" class="text-sm text-slate-500 hover:underline">Cancel</a>
        </div>
    </form>
</x-app-layout>
