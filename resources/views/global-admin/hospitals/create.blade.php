@extends('layouts.global-admin', ['title' => 'Add hospital'])

@section('content')
<div class="w-full space-y-6">
    <div>
        <a href="{{ route('global-admin.hospitals.index') }}" class="text-sm font-semibold text-[#c81020] hover:underline">← Hospitals</a>
        <h1 class="mt-3 text-2xl font-bold tracking-tight">Add new hospital</h1>
        <p class="mt-1 text-sm text-slate-600">Create a separate hospital workspace and set its initial modules and admission workflow.</p>
    </div>
    @if (session('success')) <div class="rounded-lg border border-green-200 bg-green-50 p-4 text-green-800">{{ session('success') }}</div> @endif
    @if (session('warning')) <div class="rounded-lg border border-amber-200 bg-amber-50 p-4 text-amber-800">{{ session('warning') }}</div> @endif
    @if ($errors->any())
        <div class="rounded-lg border border-red-200 bg-red-50 p-4 text-red-800"><ul class="list-disc pl-5">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
    @endif
    <form method="POST" action="{{ route('global-admin.hospitals.store') }}" class="space-y-7 rounded-xl border border-slate-200 bg-white p-4 shadow-sm sm:p-6 lg:p-8">
        @csrf
        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <label class="block text-sm font-semibold">Hospital name<input name="name" value="{{ old('name') }}" required maxlength="255" class="mt-2 block w-full rounded-md border-slate-300 font-normal"></label>
            <label class="block text-sm font-semibold">Hospital URL name (optional)<input name="slug" value="{{ old('slug') }}" maxlength="80" class="mt-2 block w-full rounded-md border-slate-300 font-normal" placeholder="generated-from-hospital-name"></label>
            <label class="block text-sm font-semibold">First admin name<input name="admin_name" value="{{ old('admin_name') }}" required maxlength="255" class="mt-2 block w-full rounded-md border-slate-300 font-normal"></label>
            <label class="block text-sm font-semibold">First admin email<input type="email" name="admin_email" value="{{ old('admin_email') }}" required maxlength="255" class="mt-2 block w-full rounded-md border-slate-300 font-normal"></label>
        </div>
        {{-- Maternity is enabled per hospital and depends on the Inpatient module. --}}
        <fieldset>
            <legend class="text-sm font-bold">Features for this hospital</legend>
            <p class="mt-1 text-sm text-slate-600">You can adjust these later. External Lab and Lab Inventory require Laboratory; A&amp;E Pharmacy requires A&amp;E and Pharmacy; Maternity requires Inpatient; Online Payments requires Patient Portal.</p>
            <div class="mt-3 grid gap-3 sm:grid-cols-2 lg:grid-cols-4 xl:grid-cols-5">
                @foreach ($modules as $key => $label)
                    <label class="flex items-center gap-2 rounded-lg border border-slate-200 p-3 text-sm">
                        <input type="checkbox" name="modules[]" value="{{ $key }}" @checked(in_array($key, old('modules', ['pharmacy', 'inpatient', 'laboratory']), true)) class="rounded border-slate-300 text-[#e51d2a] focus:ring-[#e51d2a]">{{ $label }}
                    </label>
                @endforeach
            </div>
        </fieldset>
        <fieldset>
            <legend class="text-sm font-bold">Admission workflow starting point</legend>
            <div class="mt-3 grid gap-3 sm:grid-cols-2">
                <label class="flex items-start gap-3 rounded-lg border border-slate-200 p-4 text-sm"><input type="radio" name="admission_template" value="current" @checked(old('admission_template', 'current') === 'current') class="mt-1 text-[#e51d2a] focus:ring-[#e51d2a]"><span><strong>Current workflow</strong><br><span class="text-slate-600">Includes all admission charts currently available.</span></span></label>
                <label class="flex items-start gap-3 rounded-lg border border-slate-200 p-4 text-sm"><input type="radio" name="admission_template" value="basic" @checked(old('admission_template') === 'basic') class="mt-1 text-[#e51d2a] focus:ring-[#e51d2a]"><span><strong>Basic workflow</strong><br><span class="text-slate-600">Admission details, vitals, and care notes.</span></span></label>
            </div>
        </fieldset>
        <div class="flex justify-end"><button class="rounded-md bg-[#e51d2a] px-5 py-2.5 text-sm font-bold text-white hover:bg-[#c81020]">Create hospital</button></div>
    </form>
</div>
@endsection
