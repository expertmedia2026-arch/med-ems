@extends('layouts.global-admin', ['title' => 'Customize hospital'])

@section('content')
<div class="w-full space-y-7">
    <div class="flex flex-col justify-between gap-4 sm:flex-row sm:items-end">
        <div>
            <a href="{{ route('global-admin.hospitals.index') }}" class="text-sm font-semibold text-[#c81020] hover:underline">← All hospitals</a>
            <h1 class="mt-3 text-2xl font-semibold">Customize {{ $hospital->name }}</h1>
            <p class="mt-1 text-sm text-slate-600">Choose the platform modules and admission starting workflow for this hospital.</p>
        </div>
        <span class="inline-flex w-fit items-center rounded-full bg-slate-100 px-3 py-1.5 text-xs font-semibold text-slate-600">{{ $hospital->is_active ? 'Active hospital' : 'Inactive hospital' }}</span>
    </div>
    @if (session('success')) <div class="rounded-lg bg-green-50 p-4 text-green-800">{{ session('success') }}</div> @endif
    @if ($errors->any())
        <div class="rounded-lg bg-red-50 p-4 text-red-800"><ul class="list-disc pl-5">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
    @endif
    @if (auth()->user()->isPlatformSuperAdmin())
        <section class="space-y-4 rounded-xl border border-slate-200 bg-white p-4 shadow-sm sm:p-6">
            <div>
                <h2 class="text-lg font-semibold">Hospital administrators</h2>
                @if (count($hospital->admin_emails) > 0)
                    <p class="mt-1 text-sm text-slate-600">Active administrator accounts: {{ implode(', ', $hospital->admin_emails) }}</p>
                @else
                    <p class="mt-1 text-sm text-amber-800">This hospital has no active administrator account yet.</p>
                @endif
            </div>
            <form method="POST" action="{{ route('global-admin.hospitals.admins.store', $hospital) }}" class="grid gap-4 sm:grid-cols-3">
                @csrf
                <label class="block text-sm font-semibold">Administrator name<input name="name" value="{{ old('name') }}" required maxlength="255" class="mt-2 block w-full rounded-md border-slate-300 font-normal"></label>
                <label class="block text-sm font-semibold">Administrator email<input type="email" name="email" value="{{ old('email') }}" required maxlength="255" class="mt-2 block w-full rounded-md border-slate-300 font-normal"></label>
                <div class="flex items-end"><button class="rounded-md bg-[#e51d2a] px-4 py-2 font-semibold text-white hover:bg-[#c81020]">Add administrator</button></div>
            </form>
            <p class="text-xs text-slate-500">A password setup link will be sent to the new administrator.</p>
        </section>
    @endif
    <form method="POST" action="{{ route('global-admin.hospitals.update', $hospital) }}" class="space-y-7 rounded-xl border border-slate-200 bg-white p-4 shadow-sm sm:p-6 lg:p-8">
        @csrf
        @method('PUT')
        {{-- Keep module availability tied to each hospital; Maternity requires Inpatient. --}}
        <fieldset>
            <legend class="text-sm font-semibold">Enabled modules</legend>
            <p class="mt-1 text-sm text-slate-600">Disabled modules are hidden from this hospital’s users. External Lab and Lab Inventory require Laboratory; A&amp;E Pharmacy requires A&amp;E and Pharmacy; Maternity requires Inpatient; Online Payments requires Patient Portal.</p>
            <div class="mt-3 grid gap-3 sm:grid-cols-2 lg:grid-cols-4 xl:grid-cols-5">
                @foreach ($modules as $key => $label)
                    <label class="flex items-center gap-2 rounded-lg border border-slate-200 p-3 text-sm">
                        <input type="checkbox" name="modules[]" value="{{ $key }}" @checked(in_array($key, old('modules', $enabledModules), true)) class="rounded border-slate-300 text-[#e51d2a] focus:ring-[#e51d2a]">{{ $label }}
                    </label>
                @endforeach
            </div>
        </fieldset>
        <fieldset>
            <legend class="text-sm font-semibold">Admission workflow starting template</legend>
            <div class="mt-3 grid gap-3 sm:grid-cols-2">
                <label class="flex items-start gap-3 rounded-lg border border-slate-200 p-4 text-sm"><input type="radio" name="admission_template" value="current" @checked(old('admission_template', $admissionTemplate) === 'current') class="mt-1 text-[#e51d2a] focus:ring-[#e51d2a]"><span><strong>Current workflow</strong><br><span class="text-slate-600">Includes all admission charts currently available.</span></span></label>
                <label class="flex items-start gap-3 rounded-lg border border-slate-200 p-4 text-sm"><input type="radio" name="admission_template" value="basic" @checked(old('admission_template', $admissionTemplate) === 'basic') class="mt-1 text-[#e51d2a] focus:ring-[#e51d2a]"><span><strong>Basic workflow</strong><br><span class="text-slate-600">Starts with admission details, vitals, and care notes.</span></span></label>
            </div>
        </fieldset>
        <div class="flex justify-end"><button class="rounded-md bg-[#e51d2a] px-4 py-2 font-semibold text-white hover:bg-[#c81020]">Save customization</button></div>
    </form>
</div>
@endsection
