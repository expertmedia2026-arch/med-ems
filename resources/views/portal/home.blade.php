<x-portal-layout>
    <x-slot name="header">Dashboard</x-slot>

    <div class="mb-6">
        <h2 class="text-2xl font-semibold">Welcome, {{ $patient->full_name }}</h2>
        <p class="text-slate-500">Patient · {{ $patient->hospital_number }} · {{ now()->format('l, d F Y') }}</p>
    </div>

    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
        <div class="rounded-xl border bg-white p-5 shadow-sm">
            <p class="text-sm text-slate-500">Outstanding balance</p>
            <p class="mt-2 text-3xl font-bold {{ $outstanding > 0 ? 'text-red-700' : 'text-green-700' }}">₦{{ number_format($outstanding, 2) }}</p>
            <p class="mt-1 text-xs text-slate-400">{{ $unpaidInvoices }} unpaid invoice(s)</p>
            @if ($outstanding > 0 && $onlinePaymentsEnabled)
                <a href="{{ route('portal.invoices.index') }}" class="mt-2 inline-block text-xs font-medium text-teal-700 hover:underline">Pay now</a>
            @endif
        </div>

        @if ($appointmentsEnabled)
        <div class="rounded-xl border bg-white p-5 shadow-sm">
            <p class="text-sm text-slate-500">Next appointment</p>
            @if ($nextAppointment)
                <p class="mt-2 text-xl font-bold text-teal-800">{{ $nextAppointment->isLab() ? $nextAppointment->scheduled_at->format('D, d M · g:i A') : $nextAppointment->scheduled_at->format('D, d M') }}</p>
                <p class="mt-1 text-xs text-slate-500">{{ $nextAppointment->doctor?->name ?? 'Laboratory visit' }}</p>
            @else
                <p class="mt-2 text-sm text-slate-500">Nothing booked.</p>
            @endif
        </div>
        @endif

        <div class="rounded-xl border bg-white p-5 shadow-sm">
            <p class="text-sm text-slate-500">Last visit</p>
            
            @if ($lastVisit)
                <p class="mt-2 text-xl font-bold text-teal-800">{{ $lastVisit->closed_at->format('d M Y') }}</p>
                <p class="mt-1 text-xs text-slate-500">{{ $lastVisit->doctor?->name ?? 'Laboratory visit' }}</p>
                <a href="{{ route('portal.visits.show', $lastVisit) }}" class="mt-2 inline-block text-xs text-teal-700 hover:underline">View details</a>
            @else
                <p class="mt-2 text-sm text-slate-500">No visits yet.</p>
            @endif
        </div>
    </div>

    <div class="mt-8 rounded-xl border bg-white shadow-sm">
        <h3 class="border-b px-5 py-3 font-semibold">My details</h3>

        <dl class="grid gap-4 p-5 text-sm sm:grid-cols-2">
            @foreach ([
                'Name'            => $patient->full_name,
                'Hospital number' => $patient->hospital_number,
                'Sex'             => ucfirst($patient->gender),
                'Age'             => ($patient->dob_estimated ? '~' : '') . $patient->age . ' yrs',
                'Date of birth'   => $patient->date_of_birth ? ($patient->dob_estimated ? '~' : '') . $patient->date_of_birth->format('d M Y') : null,
                'Blood group'     => $patient->blood_group,
                'Genotype'        => $patient->genotype,
                'Allergies'       => $patient->allergies,
                'Phone'           => $patient->phone,
                'Email'           => $patient->email,
                'Address'         => $patient->address,
            ] as $label => $value)
                <div>
                    <dt class="text-slate-500">{{ $label }}</dt>
                    <dd class="font-medium">{{ $value ?: '—' }}</dd>
                </div>
            @endforeach
        </dl>

        <p class="border-t px-5 py-3 text-xs text-slate-400">To correct any of these details, please speak to the front desk.</p>
    </div>
</x-portal-layout>
