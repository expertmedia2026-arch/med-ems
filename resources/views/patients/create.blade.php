<x-app-layout>
    <x-slot name="header">Register Patient</x-slot>

    <form method="POST" action="{{ route('patients.store') }}" enctype="multipart/form-data" class="max-w-3xl rounded-xl border bg-white p-6 shadow-sm">
        @csrf
        @include('patients._form', ['patient' => null])

        <div class="mt-6 flex items-center gap-3">
            <button class="rounded-lg bg-teal-700 px-5 py-2.5 text-sm font-medium text-white hover:bg-teal-800">Register patient</button>
            <a href="{{ route('patients.index') }}" class="text-sm text-slate-500 hover:underline">Cancel</a>
        </div>
    </form>
</x-app-layout>
