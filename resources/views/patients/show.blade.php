<x-app-layout>
    <x-slot name="header">{{ $patient->full_name }}</x-slot>

    @php
        $frontDesk = auth()->user()->hasRole('admin', 'receptionist');
        $doctor = auth()->user()->hasRole('doctor');
        $input = 'mt-1 block w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-teal-600 focus:ring-teal-600';
    @endphp

    <div class="grid gap-6 lg:grid-cols-3">
        {{-- Profile --}}
        <div class="rounded-xl border bg-white p-6 shadow-sm lg:col-span-2">
            <div class="flex items-start justify-between">
                <div>
                    <p class="font-mono text-sm text-teal-700">{{ $patient->hospital_number }}</p>
                    <h2 class="text-2xl font-semibold">{{ $patient->full_name }}</h2>
                    <p class="text-slate-500">
                        {{ ucfirst($patient->gender) }} · {{ $patient->dob_estimated ? '~' : '' }}{{ $patient->age }} yrs
                        @if ($patient->dob_estimated)
                            · <span class="text-amber-700">Date of birth not confirmed</span>
                        @else
                            · DOB {{ $patient->date_of_birth->format('d M Y') }}
                        @endif
                    </p>
                </div>
                @if ($frontDesk)
                    <a href="{{ route('patients.edit', $patient) }}" class="text-sm text-teal-700 hover:underline">Edit</a>
                @endif
            </div>

            <dl class="mt-6 grid gap-4 text-sm sm:grid-cols-2">
                @foreach ([
                    'Phone' => $patient->phone, 'Email' => $patient->email,
                    'Address' => $patient->address, 'Blood group' => $patient->blood_group,
                    'Genotype' => $patient->genotype, 'Allergies' => $patient->allergies,
                    'Next of kin' => $patient->next_of_kin_name, 'Next of kin phone' => $patient->next_of_kin_phone,
                ] as $label => $value)
                    <div>
                        <dt class="text-slate-500">{{ $label }}</dt>
                        <dd class="font-medium">{{ $value ?: '—' }}</dd>
                    </div>
                @endforeach
            </dl>
        </div>

        {{-- Actions --}}
        <div class="space-y-4">
            @if ($activeVisit)
                <div class="rounded-xl border bg-white p-5 shadow-sm">
                    <p class="text-sm text-slate-500">Current visit</p>
                    <div class="mt-2"><x-badge :color="$activeVisit->status->color()">{{ $activeVisit->status->label() }}</x-badge></div>
                    
                    @if ($activeVisit->status->value === 'waiting_triage' && ! $activeVisit->consultation_paid)
                        <p class="mt-3 rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 text-xs text-amber-900">
                            Consultation fee not paid yet. Send the patient to the cashier.
                        </p>
                    @endif


                    <p class="mt-3 text-sm">Checked in {{ $activeVisit->checked_in_at->diffForHumans() }}</p>
                    @if ($activeVisit->complaint)
                        <p class="mt-1 text-sm text-slate-600">“{{ $activeVisit->complaint }}”</p>
                    @endif
                </div>
            @elseif ($frontDesk)
                <form method="POST" action="{{ route('patients.check-in', $patient) }}" class="rounded-xl border bg-white p-5 shadow-sm">
                    @csrf
                    <p class="font-semibold">Walk-in check-in</p>

                    <label class="mt-3 block text-sm text-slate-600">Complaint</label>
                    <input name="complaint" type="text" placeholder="e.g. Fever, headache" class="{{ $input }}">

                    <label class="mt-3 block text-sm text-slate-600">Doctor (optional)</label>
                    <select name="doctor_id" class="{{ $input }}">
                        <option value="">Any available doctor</option>
                        @foreach ($doctors as $doctor)
                            <option value="{{ $doctor->id }}">{{ $doctor->name }}</option>
                        @endforeach
                    </select>

                    <button class="mt-4 w-full rounded-lg bg-teal-700 py-2.5 text-sm font-medium text-white hover:bg-teal-800">
                        Check in patient
                    </button>
                </form>
            @endif

            @if ($frontDesk)
                <a href="{{ route('appointments.create', ['patient' => $patient->hospital_number]) }}"
                   class="block rounded-xl border bg-white p-4 text-center text-sm font-medium text-teal-700 shadow-sm hover:bg-slate-50">
                    Book appointment
                </a>
                <a href="{{ route('lab.referrals.create', ['patient' => $patient->hospital_number]) }}"
                    class="block rounded-xl border bg-white p-4 text-center text-sm font-medium text-teal-700 shadow-sm hover:bg-slate-50">
                    Lab-only request (referral)
                </a>
            @endif


            {{-- Invitation link for local testing (shown right after sending) --}}
            @if (session('invite_link'))
                <div class="rounded-xl border border-blue-200 bg-blue-50 p-4 text-xs text-blue-900" x-data="{ copied: false }">
                    <p class="font-medium">Portal invitation link (valid 7 days, single use)</p>
                    <div class="mt-2 flex gap-2">
                        <input readonly value="{{ session('invite_link') }}" x-ref="link" class="flex-1 rounded-lg border-blue-200 bg-white text-xs">
                        <button type="button" @click="navigator.clipboard.writeText($refs.link.value); copied = true"
                                class="rounded-lg bg-blue-700 px-3 text-xs font-medium text-white hover:bg-blue-800">
                            <span x-text="copied ? 'Copied' : 'Copy'"></span>
                        </button>
                    </div>
                </div>
            @endif

            <div class="rounded-xl border bg-white p-5 text-sm shadow-sm">
                <p class="font-semibold">Patient portal</p>

                <p class="mt-2 text-xs text-slate-500">
                    Registration fee:
                    @if ($patient->registration_paid_at)
                        <span class="font-medium text-green-700">paid</span>
                    @else
                        <span class="font-medium text-amber-700">unpaid (at the cashier)</span>
                    @endif
                </p>

                @if ($portalUser)
                    <p class="mt-3">
                        <x-badge :color="$patient->registration_paid_at ? 'green' : 'amber'">
                            {{ $patient->registration_paid_at ? 'Account active' : 'Account locked' }}
                        </x-badge>
                    </p>
                    <p class="mt-1 text-xs text-slate-500">{{ $portalUser->email }}</p>
                    @unless ($patient->registration_paid_at)
                        <p class="mt-2 text-xs text-amber-800">The patient can log in once the cashier collects the registration fee.</p>
                    @endunless
                @elseif ($pendingInvite)
                    <p class="mt-3 text-xs text-slate-600">
                        Invitation sent to <strong>{{ $pendingInvite->email }}</strong> {{ $pendingInvite->created_at->diffForHumans() }}.
                        Waiting for the patient to set a password.
                    </p>
                    <form method="POST" action="{{ route('patients.portal-invite', $patient) }}" class="mt-3">
                        @csrf
                        <button class="text-xs text-teal-700 hover:underline">Resend invitation</button>
                    </form>
                @elseif (blank($patient->email))
                    <p class="mt-3 text-xs text-slate-500">No email on file. Add one under Edit to give this patient portal access.</p>
                @else
                    <form method="POST" action="{{ route('patients.portal-invite', $patient) }}" class="mt-3">
                        @csrf
                        <button class="w-full rounded-lg border border-teal-700 px-3 py-2 text-xs font-medium text-teal-700 hover:bg-teal-50">
                            Send portal invitation
                        </button>
                    </form>
                @endif
            </div>

        </div>
    </div>

    {{-- History --}}
    <div class="mt-8 grid gap-6 lg:grid-cols-2">
        <div class="rounded-xl border bg-white shadow-sm">
            <h3 class="border-b px-5 py-3 font-semibold">Visits</h3>
            <ul class="divide-y text-sm">
                @forelse ($patient->visits as $visit)
                    <li class="flex flex-wrap items-center justify-between gap-3 px-5 py-3">
                        <div>
                            <p class="font-medium">{{ $visit->checked_in_at->format('d M Y, g:i A') }}</p>
                            <p class="text-slate-500">{{ $visit->doctor?->name ?? 'No doctor assigned' }}{{ $visit->diagnosis ? ' · ' . $visit->diagnosis : '' }}</p>
                        </div>
                        <div class="flex items-center gap-3">
                            <x-badge :color="$visit->status->color()">{{ $visit->status->label() }}</x-badge>
                            @if ($doctor && $visit->status->value === 'completed')
                                <a href="{{ route('consultations.history', $visit) }}" class="rounded-lg border px-3 py-1.5 text-xs font-medium text-teal-700 hover:bg-teal-50">View</a>
                            @endif
                        </div>
                    </li>
                @empty
                    <li class="px-5 py-6 text-center text-slate-500">No visits yet.</li>
                @endforelse
            </ul>
        </div>

        <div class="rounded-xl border bg-white shadow-sm">
            <h3 class="border-b px-5 py-3 font-semibold">Appointments</h3>
            <ul class="divide-y text-sm">
                @forelse ($patient->appointments as $appt)
                    <li class="flex flex-wrap items-center justify-between gap-3 px-5 py-3">
                        <div>
                            <p class="font-medium">{{ $appt->scheduled_at->format('d M Y, g:i A') }}</p>
                            <p class="text-slate-500">{{ $appt->isLab() ? 'Lab test' : $appt->doctor?->name }}{{ $appt->reason ? ' · ' . $appt->reason : '' }}</p>
                        </div>
                        <div class="flex items-center gap-3">
                            <x-badge :color="$appt->status->color()">{{ $appt->status->label() }}</x-badge>
                            @if ($doctor)
                                <a href="{{ route('appointments.show', $appt) }}" class="rounded-lg border px-3 py-1.5 text-xs font-medium text-teal-700 hover:bg-teal-50">View</a>
                            @endif
                        </div>
                    </li>
                @empty
                    <li class="px-5 py-6 text-center text-slate-500">No appointments.</li>
                @endforelse
            </ul>
        </div>
    </div>
</x-app-layout>