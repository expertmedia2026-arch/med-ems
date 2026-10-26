<x-app-layout>
    <x-slot name="header">Add Department Service</x-slot>

    <form method="POST" action="{{ route('lab.tests.store') }}" class="max-w-3xl rounded-xl border bg-white p-6 shadow-sm">
        @csrf
        @include('lab.tests._form', ['test' => null])

        <div class="mt-6 flex items-center gap-3">
            <button class="rounded-lg bg-teal-700 px-5 py-2.5 text-sm font-medium text-white hover:bg-teal-800">Add service</button>
            <a href="{{ route('lab.tests.index') }}" class="text-sm text-slate-500 hover:underline">Cancel</a>
        </div>
    </form>
</x-app-layout>
