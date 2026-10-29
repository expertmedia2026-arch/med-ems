@extends('layouts.global-admin', ['title' => 'Configuration'])

@section('content')
<div class="space-y-7">
    <div><p class="text-xs font-bold uppercase tracking-[.16em] text-[#c81020]">Platform setup</p><h1 class="mt-2 text-2xl font-bold tracking-tight">Configuration</h1><p class="mt-1 max-w-3xl text-sm text-slate-600">Manage the MedEMS options available when hospitals are onboarded. Choose each hospital’s modules and admission starting workflow from its hospital configuration. CSV import and export stay available as standard tools.</p></div>
    <section class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6">
        <div class="flex flex-col justify-between gap-3 sm:flex-row sm:items-center"><div><h2 class="text-lg font-bold">Platform feature catalog</h2><p class="mt-1 text-sm text-slate-600">These are the features currently available to enable for each hospital.</p></div><a href="{{ route('global-admin.hospitals.create') }}" class="text-sm font-bold text-[#c81020] hover:underline">Onboard a hospital →</a></div>
        <div class="mt-5 grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
            @foreach ($modules as $key => $label)
                <article class="rounded-lg border border-slate-200 p-4"><div class="flex items-center justify-between gap-3"><h3 class="font-bold">{{ $label }}</h3><span class="rounded-full bg-green-50 px-2.5 py-1 text-xs font-bold text-green-700">Available</span></div><p class="mt-2 text-xs leading-5 text-slate-600">{{ match ($key) { 'pharmacy' => 'Medication dispensing and pharmacy operations.', 'mortuary' => 'Mortuary case and body management.', 'emergency' => 'Accident and emergency department workflows.', 'inpatient' => 'Admission and inpatient care workflows.', 'maternity' => 'Labor episode, delivery, postpartum documentation, and linked newborn records; requires Inpatient.', 'laboratory' => 'In-house laboratory requests, processing, and results.', 'external_lab' => 'External referrals and walk-in lab clients; requires Laboratory.', 'staff_roster' => 'Staff duty assignment and weekly roster management.', 'appointments' => 'Staff and patient appointment booking and management.', 'patient_id_card' => 'Print patient identification cards after registration.', 'patient_portal' => 'Patient-facing access to visits, lab reports, invoices, and portal tools.', 'central_vitals' => 'Allows OPD nurses to work as a central vitals team across departments.', 'medication_administration' => 'Nursing medication administration records and related workflows.', 'emergency_pharmacy' => 'Emergency medication inventory and recording supplies used; requires A&E and Pharmacy.', 'lab_inventory' => 'Laboratory supply inventory; requires Laboratory.', 'online_payments' => 'Online invoice payments and patient payment confirmation; requires Patient Portal.', 'finance_reports' => 'Finance overview and downloadable finance reports.', default => 'Hospital feature.' } }}</p></article>
            @endforeach
        </div>
    </section>
    <section class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6">
        <h2 class="text-lg font-bold">Admission workflow templates</h2><p class="mt-1 text-sm text-slate-600">Select a starting template during onboarding. Hospital admins can continue adjusting their own workflow in hospital settings.</p>
        <div class="mt-5 grid gap-4 sm:grid-cols-2">
            <article class="rounded-lg border-l-4 border-[#e51d2a] bg-slate-50 p-4"><h3 class="font-bold">Basic</h3><p class="mt-2 text-sm text-slate-600">Admission details, vitals, and care notes.</p></article>
            <article class="rounded-lg border-l-4 border-[#171719] bg-slate-50 p-4"><h3 class="font-bold">Current</h3><p class="mt-2 text-sm text-slate-600">Includes the admission charts currently available in MedEMS.</p></article>
        </div>
    </section>
</div>
@endsection
