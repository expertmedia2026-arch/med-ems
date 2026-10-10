<x-app-layout>
    <x-slot name="header">My Appointment Availability</x-slot>

    <div class="max-w-3xl space-y-6">
        <form method="POST" action="{{ route('doctor.availability.store') }}" class="rounded-xl border bg-white p-6 shadow-sm">
            @csrf
            <h2 class="font-semibold">Add an available date</h2>
            <p class="mt-1 text-sm text-slate-500">Patients and reception can book you on dates saved here. Appointment times are selected during booking.</p>

            <div class="mt-4 flex flex-wrap items-end gap-3">
                <div class="flex-1">
                    <label for="date" class="block text-sm font-medium text-slate-700">Date</label>
                    <input id="date" name="date" type="date" required min="{{ today()->toDateString() }}" max="{{ $maxDate }}"
                           value="{{ old('date') }}" class="mt-1 block w-full rounded-lg border-slate-300 shadow-sm focus:border-teal-600 focus:ring-teal-600">
                    @error('date')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                </div>
                <button class="rounded-lg bg-teal-700 px-5 py-2.5 text-sm font-medium text-white hover:bg-teal-800">Save date</button>
            </div>
            <p class="mt-2 text-xs text-slate-500">You can add more than one date. Availability can be set up to {{ config('hms.booking.max_days_ahead') }} days ahead.</p>
        </form>

        <section class="overflow-hidden rounded-xl border bg-white shadow-sm">
            <h2 class="border-b px-5 py-4 font-semibold">Upcoming available dates</h2>
            <ul class="divide-y">
                @forelse ($availabilities as $availability)
                    <li class="flex items-center justify-between gap-4 px-5 py-4">
                        <div>
                            <p class="font-medium">{{ $availability->date->format('l, d F Y') }}</p>
                            <p class="text-xs text-slate-500">Available for appointment booking</p>
                        </div>
                        <form method="POST" action="{{ route('doctor.availability.destroy', $availability) }}" onsubmit="return confirm('Remove this available date?')">
                            @csrf
                            @method('DELETE')
                            <button class="text-sm text-red-600 hover:underline">Remove</button>
                        </form>
                    </li>
                @empty
                    <li class="px-5 py-8 text-center text-sm text-slate-500">You have not added any upcoming dates yet.</li>
                @endforelse
            </ul>
        </section>
    </div>
</x-app-layout>
