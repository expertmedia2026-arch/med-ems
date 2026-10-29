<x-app-layout>
    <x-slot name="header">Maternity episode</x-slot>

    @php
        // Keep submitted newborn rows visible after validation redirects, with one live-birth row by default.
        $input = 'mt-1 block w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-pink-600 focus:ring-pink-600';
        $label = 'block text-sm font-medium text-slate-700';
        $newbornFormData = old('newborns');
        $newbornFormData = is_array($newbornFormData)
            ? array_values(array_filter($newbornFormData, 'is_array'))
            : [];
        $newbornFormData = $newbornFormData ?: [['outcome' => 'live_birth', 'first_name' => '', 'last_name' => '', 'gender' => '']];
    @endphp

    <div class="space-y-6">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <a href="{{ route('admissions.show', $episode->admission) }}" class="text-sm font-semibold text-pink-800 hover:underline">← Maternal admission, medicines &amp; IV fluids</a>
            <span class="rounded-full bg-pink-50 px-3 py-1.5 text-sm font-semibold capitalize text-pink-800">{{ str_replace('_', ' ', $episode->status) }}</span>
        </div>
        @if (session('success'))<div class="rounded-lg border border-green-200 bg-green-50 p-4 text-sm text-green-800">{{ session('success') }}</div>@endif
        @if ($errors->any())
            <div class="rounded-lg border border-red-200 bg-red-50 p-4 text-sm text-red-800">
                <p class="font-semibold">Please check the information below.</p>
                <ul class="mt-1 list-disc pl-5">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
            </div>
        @endif

        {{-- The summary keeps labor, delivery, and material-supply facts together for clinical review. --}}
        <section class="rounded-xl border bg-white p-5 shadow-sm">
            <h2 class="text-xl font-semibold text-slate-900">{{ $episode->admission->patient->full_name }}</h2>
            <p class="mt-1 text-sm text-slate-500">{{ $episode->admission->patient->hospital_number }} · {{ $episode->admission->admission_number }} · {{ $episode->admission->department->name }}</p>
            <dl class="mt-4 grid gap-4 border-t pt-4 text-sm sm:grid-cols-2">
                <div><dt class="text-slate-500">Labor started</dt><dd class="mt-1 font-medium">{{ $episode->labor_started_at->format('d M Y, g:i A') }}</dd></div>
                <div><dt class="text-slate-500">Delivery</dt><dd class="mt-1 font-medium">{{ $episode->delivery_at?->format('d M Y, g:i A') ?? 'Not recorded' }}</dd></div>
                <div><dt class="text-slate-500">Delivery mode</dt><dd class="mt-1 font-medium">{{ $episode->delivery_mode ? str($episode->delivery_mode)->replace('_', ' ')->title() : 'Not recorded' }}</dd></div>
                <div><dt class="text-slate-500">Patient brought delivery materials</dt><dd class="mt-1 font-medium">{{ is_null($episode->patient_brought_delivery_materials) ? 'Not recorded' : ($episode->patient_brought_delivery_materials ? 'Yes' : 'No') }}</dd></div>
                @if (! is_null($episode->hospital_supplied_delivery_pack))
                    <div><dt class="text-slate-500">Hospital supplied delivery pack</dt><dd class="mt-1 font-medium">{{ $episode->hospital_supplied_delivery_pack ? 'Yes' : 'No' }}</dd></div>
                @endif
            </dl>
            @if ($episode->labor_assessment)
                <div class="mt-4 border-t pt-4"><h3 class="text-sm font-semibold text-slate-700">Initial labor assessment</h3><p class="mt-1 whitespace-pre-line text-sm text-slate-600">{{ $episode->labor_assessment }}</p></div>
            @endif
            @if ($episode->delivery_notes)
                <div class="mt-4 border-t pt-4"><h3 class="text-sm font-semibold text-slate-700">Delivery notes</h3><p class="mt-1 whitespace-pre-line text-sm text-slate-600">{{ $episode->delivery_notes }}</p></div>
            @endif
            @if ($episode->delivery_material_notes)
                <div class="mt-4 border-t pt-4"><h3 class="text-sm font-semibold text-slate-700">Delivery materials notes</h3><p class="mt-1 whitespace-pre-line text-sm text-slate-600">{{ $episode->delivery_material_notes }}</p></div>
            @endif
        </section>

        @if ($episode->status === 'in_labor' && $episode->admission->status->value === 'active')
            {{-- Medication and IV care stay on the linked inpatient admission rather than duplicating charting here. --}}
            <section class="rounded-xl border border-blue-200 bg-blue-50 p-5">
                <h2 class="font-semibold text-blue-950">Medicines, injections and IV fluids</h2>
                <p class="mt-1 text-sm text-blue-900">Keep these on the existing inpatient record: prescribe and dispense medicines through Pharmacy, then record doses and fluid intake in nursing and admission care charts.</p>
                <a href="{{ route('admissions.show', $episode->admission) }}" class="mt-3 inline-flex rounded-lg border border-blue-700 px-3 py-2 text-sm font-semibold text-blue-800 hover:bg-white">Open maternal inpatient care</a>
            </section>
            {{-- Delivery prices are informational here; the service applies configured charges on save. --}}
            <section class="rounded-xl border bg-white p-5 shadow-sm">
                <h2 class="text-lg font-semibold text-slate-900">Record delivery</h2>
                <p class="mt-1 text-sm text-slate-600">Enter the birth details. Each live-born baby receives a separate patient record linked to this episode and the mother.</p>
                <div class="mt-4 rounded-lg border border-slate-200 bg-slate-50 p-4 text-sm text-slate-700">
                    <p class="font-semibold">Delivery fees configured by this hospital</p>
                    <dl class="mt-2 grid gap-2 sm:grid-cols-2">
                        @foreach ([
                            'Vaginal' => 'vaginal',
                            'Assisted vaginal' => 'assisted_vaginal',
                            'Cesarean' => 'cesarean',
                            'Other' => 'other',
                        ] as $modeLabel => $mode)
                            <div class="flex justify-between gap-3"><dt>{{ $modeLabel }}</dt><dd class="font-medium">₦{{ number_format($deliveryPricing[$mode], 2) }}</dd></div>
                        @endforeach
                        <div class="flex justify-between gap-3 border-t pt-2 sm:col-span-2"><dt>Hospital-supplied delivery pack (if used)</dt><dd class="font-medium">₦{{ number_format($deliveryPricing['delivery_pack'], 2) }}</dd></div>
                    </dl>
                </div>
                <form method="POST" action="{{ route('maternity.episodes.delivery', $episode) }}" class="mt-5 space-y-5" x-data="{ newborns: @js($newbornFormData) }">
                    @csrf
                    <div class="grid gap-4 sm:grid-cols-2">
                        <label class="{{ $label }}">Delivery date and time<input type="datetime-local" name="delivery_at" value="{{ old('delivery_at', now()->format('Y-m-d\TH:i')) }}" required class="{{ $input }}">@error('delivery_at')<span class="text-red-600">{{ $message }}</span>@enderror</label>
                        <label class="{{ $label }}">Delivery mode<select name="delivery_mode" required class="{{ $input }}"><option value="">Select mode</option><option value="vaginal" @selected(old('delivery_mode') === 'vaginal')>Vaginal</option><option value="assisted_vaginal" @selected(old('delivery_mode') === 'assisted_vaginal')>Assisted vaginal</option><option value="cesarean" @selected(old('delivery_mode') === 'cesarean')>Cesarean</option><option value="other" @selected(old('delivery_mode') === 'other')>Other</option></select>@error('delivery_mode')<span class="text-red-600">{{ $message }}</span>@enderror</label>
                        <label class="{{ $label }} sm:col-span-2">Delivery notes<textarea name="delivery_notes" rows="3" maxlength="5000" class="{{ $input }}">{{ old('delivery_notes') }}</textarea></label>
                        <fieldset class="sm:col-span-2">
                            <legend class="{{ $label }}">Did the hospital supply a delivery pack?</legend>
                            <p class="mt-1 text-xs text-slate-500">Only mark Yes if the hospital actually supplied it; the configured pack fee is added to Billing.</p>
                            <div class="mt-2 flex flex-wrap gap-4 text-sm text-slate-700">
                                <label class="inline-flex items-center gap-2"><input type="radio" name="hospital_supplied_delivery_pack" value="1" required @checked(old('hospital_supplied_delivery_pack') === '1') class="border-slate-300 text-pink-700">Yes</label>
                                <label class="inline-flex items-center gap-2"><input type="radio" name="hospital_supplied_delivery_pack" value="0" required @checked(old('hospital_supplied_delivery_pack') === '0') class="border-slate-300 text-pink-700">No</label>
                            </div>
                            @error('hospital_supplied_delivery_pack')<span class="text-sm text-red-600">{{ $message }}</span>@enderror
                        </fieldset>
                        <label class="{{ $label }} sm:col-span-2">Delivery materials notes <span class="font-normal text-slate-500">(optional)</span><textarea name="delivery_material_notes" rows="2" maxlength="2000" class="{{ $input }}">{{ old('delivery_material_notes', $episode->delivery_material_notes) }}</textarea>@error('delivery_material_notes')<span class="text-red-600">{{ $message }}</span>@enderror</label>
                    </div>
                    <fieldset class="space-y-3">
                        <legend class="text-sm font-semibold text-slate-900">Babies recorded for this delivery</legend>
                        <template x-for="(newborn, index) in newborns" :key="index">
                            <div class="space-y-3 rounded-lg border border-slate-200 p-4">
                                <div class="flex items-center justify-between gap-3"><h3 class="text-sm font-semibold text-slate-800">Baby <span x-text="index + 1"></span></h3><button type="button" x-show="newborns.length > 1" @click="newborns.splice(index, 1)" class="text-xs font-semibold text-red-700">Remove</button></div>
                                <label class="{{ $label }}">Birth outcome<select :name="`newborns[${index}][outcome]`" x-model="newborn.outcome" required class="{{ $input }}"><option value="live_birth">Live birth</option><option value="stillbirth">Stillbirth</option></select></label>
                                <div x-show="newborn.outcome === 'live_birth'" class="grid gap-3 sm:grid-cols-3">
                                    <label class="{{ $label }}">First name<input :name="`newborns[${index}][first_name]`" x-model="newborn.first_name" x-bind:required="newborn.outcome === 'live_birth'" maxlength="100" class="{{ $input }}"></label>
                                    <label class="{{ $label }}">Last name<input :name="`newborns[${index}][last_name]`" x-model="newborn.last_name" x-bind:required="newborn.outcome === 'live_birth'" maxlength="100" class="{{ $input }}"></label>
                                    <label class="{{ $label }}">Sex<select :name="`newborns[${index}][gender]`" x-model="newborn.gender" x-bind:required="newborn.outcome === 'live_birth'" class="{{ $input }}"><option value="">Select</option><option value="female">Female</option><option value="male">Male</option></select></label>
                                </div>
                                <p x-show="newborn.outcome === 'stillbirth'" class="text-xs text-slate-500">A birth record will be saved; no patient account will be created.</p>
                            </div>
                        </template>
                        <button type="button" @click="newborns.push({ outcome: 'live_birth', first_name: '', last_name: '', gender: '' })" class="rounded-lg border border-pink-700 px-3 py-2 text-sm font-semibold text-pink-800 hover:bg-pink-50">Add another baby</button>
                    </fieldset>
                    <button class="rounded-lg bg-pink-700 px-4 py-2.5 text-sm font-semibold text-white hover:bg-pink-800">Save delivery and newborn records</button>
                </form>
            </section>
        @endif

        @if ($episode->newborns->isNotEmpty())
            <section class="rounded-xl border bg-white p-5 shadow-sm">
                <h2 class="text-lg font-semibold text-slate-900">Newborn records</h2>
                <div class="mt-4 divide-y">
                    @foreach ($episode->newborns as $newborn)
                        <div class="flex flex-wrap items-center justify-between gap-3 py-3 first:pt-0 last:pb-0">
                            <div><p class="font-medium text-slate-900">Baby {{ $newborn->birth_order }} · {{ trim(($newborn->first_name ?? '').' '.($newborn->last_name ?? '')) ?: 'Name not recorded' }}</p><p class="mt-1 text-xs text-slate-500">{{ str($newborn->outcome)->replace('_', ' ')->title() }} · {{ $newborn->date_of_birth->format('d M Y') }}{{ $newborn->gender ? ' · '.ucfirst($newborn->gender) : '' }}</p></div>
                            @if ($newborn->patient)
                                <a href="{{ route('patients.show', $newborn->patient) }}" class="text-sm font-semibold text-pink-800 hover:underline">Open patient record · {{ $newborn->patient->hospital_number }}</a>
                            @else
                                <span class="text-xs text-slate-500">No patient record for this outcome</span>
                            @endif
                        </div>
                    @endforeach
                </div>
            </section>
        @endif

        @if ($episode->status === 'postpartum' && $episode->admission->status->value === 'active')
            {{-- Postpartum documentation is editable only while the maternal admission remains active. --}}
            <section class="rounded-xl border bg-white p-5 shadow-sm">
                <h2 class="text-lg font-semibold text-slate-900">Maternal postpartum notes</h2>
                <form method="POST" action="{{ route('maternity.episodes.postpartum', $episode) }}" class="mt-4 space-y-3">
                    @csrf
                    @method('PUT')
                    <label for="postpartum_notes" class="{{ $label }}">Postpartum notes</label>
                    <textarea id="postpartum_notes" name="postpartum_notes" rows="5" required maxlength="5000" class="{{ $input }}">{{ old('postpartum_notes', $episode->postpartum_notes) }}</textarea>
                    @error('postpartum_notes')<p class="text-sm text-red-600">{{ $message }}</p>@enderror
                    <button class="rounded-lg bg-pink-700 px-4 py-2.5 text-sm font-semibold text-white hover:bg-pink-800">Save postpartum notes</button>
                </form>
            </section>
        @elseif ($episode->postpartum_notes)
            <section class="rounded-xl border bg-white p-5 shadow-sm"><h2 class="font-semibold text-slate-900">Maternal postpartum notes</h2><p class="mt-2 whitespace-pre-line text-sm text-slate-600">{{ $episode->postpartum_notes }}</p></section>
        @endif
    </div>
</x-app-layout>
