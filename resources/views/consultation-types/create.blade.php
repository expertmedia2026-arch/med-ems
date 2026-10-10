<x-app-layout>
    <x-slot name="header">Add Consultation Type</x-slot>

    <form method="POST" action="{{ route('consultation-types.store') }}" class="max-w-2xl rounded-xl border bg-white p-6 shadow-sm">
        @csrf
        @include('consultation-types._form', ['consultationType' => null])

        <div class="mt-6 flex items-center gap-3">
            <button class="rounded-lg bg-teal-700 px-5 py-2.5 text-sm font-medium text-white hover:bg-teal-800">Add consultation type</button>
            <a href="{{ route('consultation-types.index') }}" class="text-sm text-slate-500 hover:underline">Cancel</a>
        </div>
    </form>
</x-app-layout>
