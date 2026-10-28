@extends('layouts.global-admin', ['title' => 'Overview'])

@section('content')
<div class="space-y-6 sm:space-y-8">
    <div class="flex flex-col gap-4 rounded-2xl bg-[#171719] p-5 text-white shadow-sm sm:flex-row sm:items-center sm:justify-between sm:p-7">
        <div>
            <p class="text-xs font-bold uppercase tracking-[.16em] text-white/55">MedEMS platform</p>
            <h1 class="mt-2 text-2xl font-bold tracking-tight sm:text-3xl">Platform overview</h1>
            <p class="mt-2 text-sm text-white/65">Your hospital network, all in one place.</p>
        </div>
        <a href="{{ route('global-admin.hospitals.create') }}" class="inline-flex min-h-11 items-center justify-center rounded-lg bg-[#e51d2a] px-4 py-2.5 text-sm font-bold text-white transition hover:bg-[#c81020] focus:outline-none focus:ring-2 focus:ring-white/70">+ Add new hospital</a>
    </div>

    <section aria-label="Platform totals" class="grid grid-cols-1 gap-3 sm:grid-cols-3 sm:gap-4">
        @foreach ([['Hospitals', $hospitalCount, 'Across your network'], ['Staff accounts', $staffCount, 'Across all hospitals'], ['Patients', $patientCount, 'Across all hospitals']] as [$label, $value, $caption])
            <article class="flex items-center justify-between gap-4 rounded-xl border border-slate-200 bg-white p-4 shadow-sm sm:block sm:p-5">
                <div><div class="text-sm font-semibold text-slate-500">{{ $label }}</div><div class="mt-1 text-xs text-slate-400 sm:mt-2">{{ $caption }}</div></div>
                <div class="text-2xl font-extrabold tracking-tight text-slate-900 sm:mt-3 sm:text-3xl">{{ number_format($value) }}</div>
            </article>
        @endforeach
    </section>

    <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
        <div class="flex flex-col gap-3 border-b border-slate-100 px-4 py-4 sm:flex-row sm:items-center sm:justify-between sm:px-6">
            <div><h2 class="text-lg font-bold tracking-tight">Hospitals</h2><p class="mt-1 text-sm text-slate-500">{{ number_format($hospitalCount) }} {{ \Illuminate\Support\Str::plural('hospital', $hospitalCount) }} in your platform workspace</p></div>
            <a href="{{ route('global-admin.hospitals.index') }}" class="inline-flex min-h-10 items-center justify-center rounded-lg border border-slate-200 px-3 py-2 text-sm font-semibold text-slate-700 transition hover:bg-slate-50">Manage hospitals <span class="ml-2" aria-hidden="true">→</span></a>
        </div>
        <div class="divide-y divide-slate-100">
            @forelse ($hospitals as $hospital)
                <article class="grid gap-4 px-4 py-4 transition hover:bg-slate-50/80 sm:px-6 sm:py-5 lg:grid-cols-[minmax(0,1.5fr)_repeat(2,minmax(7rem,.55fr))_minmax(0,1.4fr)_auto] lg:items-center lg:gap-6">
                    <a href="{{ route('global-admin.hospitals.edit', $hospital) }}" class="min-w-0">
                        <span class="block truncate font-bold text-slate-900">{{ $hospital->name }}</span>
                        <span class="mt-1 block truncate text-xs text-slate-500">{{ $hospital->slug }}</span>
                    </a>
                    <div class="grid grid-cols-2 gap-3 sm:max-w-sm lg:contents">
                        <div class="rounded-lg bg-slate-50 px-3 py-2 lg:bg-transparent lg:px-0"><span class="block text-[11px] font-semibold uppercase tracking-wide text-slate-400">Staff</span><span class="mt-1 block font-bold text-slate-800">{{ number_format($hospital->staff_count) }}</span></div>
                        <div class="rounded-lg bg-slate-50 px-3 py-2 lg:bg-transparent lg:px-0"><span class="block text-[11px] font-semibold uppercase tracking-wide text-slate-400">Patients</span><span class="mt-1 block font-bold text-slate-800">{{ number_format($hospital->patient_count) }}</span></div>
                    </div>
                    <div class="flex min-w-0 flex-wrap gap-1.5">
                        @forelse ($hospital->enabled_modules as $module)
                            <span class="max-w-full truncate rounded-full bg-red-50 px-2.5 py-1 text-xs font-semibold text-[#a70e1a]">{{ $modules[$module] ?? ucfirst($module) }}</span>
                        @empty
                            <span class="text-xs text-slate-500">No optional features</span>
                        @endforelse
                    </div>
                    <div class="flex items-center justify-between gap-3 border-t border-slate-100 pt-3 lg:border-0 lg:pt-0">
                        <span class="inline-flex items-center gap-2 rounded-full px-2.5 py-1 text-xs font-semibold {{ $hospital->is_active ? 'bg-green-50 text-green-700' : 'bg-slate-100 text-slate-600' }}"><span class="h-1.5 w-1.5 rounded-full {{ $hospital->is_active ? 'bg-green-500' : 'bg-slate-400' }}"></span>{{ $hospital->is_active ? 'Active' : 'Inactive' }}</span>
                        <a href="{{ route('global-admin.hospitals.edit', $hospital) }}" class="text-sm font-bold text-[#c81020] hover:underline">Configure <span aria-hidden="true">→</span></a>
                    </div>
                </article>
            @empty
                <div class="px-5 py-14 text-center">
                    <div class="mx-auto grid h-12 w-12 place-items-center rounded-full bg-red-50 text-xl text-[#c81020]" aria-hidden="true">+</div>
                    <h3 class="mt-4 font-bold text-slate-900">No hospitals yet</h3>
                    <p class="mt-1 text-sm text-slate-500">Add your first hospital to start building your network.</p>
                    <a href="{{ route('global-admin.hospitals.create') }}" class="mt-4 inline-flex min-h-10 items-center justify-center rounded-lg bg-[#e51d2a] px-4 py-2 text-sm font-bold text-white hover:bg-[#c81020]">Add new hospital</a>
                </div>
            @endforelse
        </div>
    </section>
</div>
@endsection
