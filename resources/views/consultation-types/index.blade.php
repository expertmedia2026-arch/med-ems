<x-app-layout>
    <x-slot name="header">Consultation Pricing</x-slot>

    <div class="mb-6"><x-csv-transfer-actions dataset="consultation-prices" label="consultation prices" /></div>

    <div class="mb-6 flex flex-wrap items-center justify-between gap-3">
        <p class="text-sm text-slate-600">Set the consultation types and prices offered by each department.</p>
        <a href="{{ route('consultation-types.create') }}" class="rounded-lg bg-teal-700 px-4 py-2 text-sm font-medium text-white hover:bg-teal-800">+ Add consultation type</a>
    </div>

    <div class="overflow-x-auto rounded-xl border bg-white shadow-sm">
        <table class="min-w-full divide-y divide-slate-200 text-sm">
            <thead class="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500">
                <tr>
                    <th class="px-4 py-3">Department</th>
                    <th class="px-4 py-3">Consultation type</th>
                    <th class="px-4 py-3">Price</th>
                    <th class="px-4 py-3">Status</th>
                    <th class="px-4 py-3"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($consultationTypes as $consultationType)
                    <tr>
                        <td class="px-4 py-3">{{ $consultationType->department->name }}</td>
                        <td class="px-4 py-3">
                            <span class="font-medium">{{ $consultationType->name }}</span>
                            @if ($consultationType->description)<span class="mt-1 block text-xs text-slate-500">{{ $consultationType->description }}</span>@endif
                        </td>
                        <td class="px-4 py-3 font-medium">₦{{ number_format((float) $consultationType->price, 2) }}</td>
                        <td class="px-4 py-3">
                            <x-badge :color="$consultationType->is_active ? 'green' : 'slate'">{{ $consultationType->is_active ? 'Active' : 'Inactive' }}</x-badge>
                        </td>
                        <td class="px-4 py-3 text-right"><a href="{{ route('consultation-types.edit', $consultationType) }}" class="text-teal-700 hover:underline">Edit</a></td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="px-4 py-10 text-center text-slate-500">No consultation types yet. Add one for a department to enable consultation check-in.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">{{ $consultationTypes->links() }}</div>
</x-app-layout>
