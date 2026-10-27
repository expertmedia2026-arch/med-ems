@extends('layouts.global-admin', ['title' => 'Overview'])

@section('content')
<div class="space-y-8">
    <div class="flex flex-col justify-between gap-4 sm:flex-row sm:items-end">
        <div><p class="text-xs font-bold uppercase tracking-[.16em] text-[#c81020]">MedEMS platform</p><h1 class="mt-2 text-2xl font-bold tracking-tight">Platform overview</h1><p class="mt-1 text-sm text-slate-600">A high-level view of your hospital network.</p></div>
        <a href="{{ route('global-admin.hospitals.create') }}" class="inline-flex items-center justify-center rounded-md bg-[#e51d2a] px-4 py-2.5 text-sm font-bold text-white hover:bg-[#c81020]">+ Add new hospital</a>
    </div>
    <section class="grid gap-4 sm:grid-cols-3">
        @foreach ([['Hospitals', $hospitalCount], ['Staff accounts', $staffCount], ['Patients', $patientCount]] as [$label, $value])
            <article class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm"><div class="text-sm font-semibold text-slate-500">{{ $label }}</div><div class="mt-3 text-3xl font-extrabold tracking-tight">{{ number_format($value) }}</div><div class="mt-2 h-1 w-12 rounded bg-[#e51d2a]"></div></article>
        @endforeach
    </section>
    @php
        $growthMax = max(1, ...$growthChart['hospitals']);
        $plotLeft = 48;
        $plotTop = 14;
        $plotWidth = 700;
        $plotHeight = 190;
        $growthPoints = [];
        $growthPoints = collect($growthChart['hospitals'])->map(function ($value, $index) use ($growthChart, $growthMax, $plotLeft, $plotTop, $plotWidth, $plotHeight) {
            $x = $plotLeft + ($index / max(1, count($growthChart['labels']) - 1)) * $plotWidth;
            $y = $plotTop + (1 - ($value / $growthMax)) * $plotHeight;
            return round($x, 1).','.round($y, 1);
        })->join(' ');
        $growthArea = 'M '.$plotLeft.','.($plotTop + $plotHeight).' '.$growthPoints.' L '.($plotLeft + $plotWidth).','.($plotTop + $plotHeight).' Z';
        $latestGrowthIndex = count($growthChart['hospitals']) - 1;
        $latestHospitalCount = $growthChart['hospitals'][$latestGrowthIndex] ?? 0;
        $latestMonthLabel = $growthChart['labels'][$latestGrowthIndex] ?? '';
        $hospitalGrowthThisMonth = $latestGrowthIndex > 0
            ? $latestHospitalCount - $growthChart['hospitals'][$latestGrowthIndex - 1]
            : 0;
        $maxHospitalSize = max(1, (int) ($hospitalSizeChart->max(fn ($hospital) => max((int) $hospital->patient_count, (int) $hospital->staff_count)) ?? 0));
    @endphp
    <section class="grid gap-5 xl:grid-cols-2">
        <article class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6">
            <div class="flex flex-wrap items-start justify-between gap-4">
                <div><h2 class="font-bold">Hospital growth</h2><p class="mt-1 text-xs text-slate-500">Cumulative hospitals onboarded · last 12 months</p></div>
                <div class="text-right"><div class="text-3xl font-extrabold tracking-tight">{{ number_format($hospitalCount) }}</div><div class="mt-1 text-xs font-semibold {{ $hospitalGrowthThisMonth > 0 ? 'text-green-700' : 'text-slate-500' }}">{{ $hospitalGrowthThisMonth > 0 ? '+' : '' }}{{ $hospitalGrowthThisMonth }} this month</div></div>
            </div>
            <div class="mt-4 overflow-x-auto">
                <svg viewBox="0 0 780 220" class="h-52 min-w-[540px] w-full" role="img" aria-label="Minimal line chart showing hospital growth over the last 12 months">
                    <defs><linearGradient id="hospital-growth-fill" x1="0" x2="0" y1="0" y2="1"><stop offset="0%" stop-color="#e51d2a" stop-opacity=".16"/><stop offset="100%" stop-color="#e51d2a" stop-opacity="0"/></linearGradient></defs>
                    <line x1="{{ $plotLeft }}" y1="{{ $plotTop + $plotHeight }}" x2="{{ $plotLeft + $plotWidth }}" y2="{{ $plotTop + $plotHeight }}" stroke="#e9e9ec" />
                    <line x1="{{ $plotLeft }}" y1="{{ $plotTop + ($plotHeight * .5) }}" x2="{{ $plotLeft + $plotWidth }}" y2="{{ $plotTop + ($plotHeight * .5) }}" stroke="#f1f1f3" stroke-dasharray="3 5" />
                    <path d="{{ $growthArea }}" fill="url(#hospital-growth-fill)" />
                    <polyline points="{{ $growthPoints }}" fill="none" stroke="#e51d2a" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" />
                    @php($lastX = $plotLeft + ($latestGrowthIndex / max(1, count($growthChart['labels']) - 1)) * $plotWidth)
                    @php($lastY = $plotTop + (1 - ($latestHospitalCount / $growthMax)) * $plotHeight)
                    <circle cx="{{ $lastX }}" cy="{{ $lastY }}" r="5" fill="#fff" stroke="#e51d2a" stroke-width="2.5"><title>{{ $latestMonthLabel }}: {{ $latestHospitalCount }} hospitals</title></circle>
                    @foreach ([0, 3, 6, 9, 11] as $index)
                        @if (isset($growthChart['labels'][$index]))
                            @php($labelX = $plotLeft + ($index / max(1, count($growthChart['labels']) - 1)) * $plotWidth)
                            <text x="{{ $labelX }}" y="205" text-anchor="middle" fill="#8b8b92" font-size="10">{{ $growthChart['labels'][$index] }}</text>
                        @endif
                    @endforeach
                </svg>
            </div>
        </article>
        <article class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6">
            <div><h2 class="font-bold">Hospital size</h2><p class="mt-1 text-xs text-slate-500">Staff and patient totals for the largest hospitals.</p></div>
            <div class="mt-6 space-y-5" role="img" aria-label="Grouped bars comparing staff and patient counts by hospital">
                @forelse ($hospitalSizeChart as $hospital)
                    <div>
                        <div class="mb-2 flex justify-between gap-3 text-xs"><span class="truncate font-semibold text-slate-700">{{ $hospital->name }}</span><span class="shrink-0 text-slate-500">{{ number_format($hospital->staff_count) }} staff · {{ number_format($hospital->patient_count) }} patients</span></div>
                        <div class="space-y-1.5"><div class="h-1.5 rounded-full bg-slate-100"><div class="h-1.5 rounded-full bg-[#171719]" style="width: {{ round(($hospital->staff_count / $maxHospitalSize) * 100) }}%"></div></div><div class="h-1.5 rounded-full bg-slate-100"><div class="h-1.5 rounded-full bg-[#e51d2a]" style="width: {{ round(($hospital->patient_count / $maxHospitalSize) * 100) }}%"></div></div></div>
                    </div>
                @empty <p class="py-8 text-center text-sm text-slate-500">Add hospitals to see tenant sizes.</p> @endforelse
            </div>
            <div class="mt-5 flex gap-5 text-xs text-slate-500"><span class="flex items-center gap-2"><i class="h-2 w-2 rounded-full bg-[#171719]"></i>Staff</span><span class="flex items-center gap-2"><i class="h-2 w-2 rounded-full bg-[#e51d2a]"></i>Patients</span></div>
        </article>
    </section>
    <section class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
        <div class="flex items-center justify-between border-b border-slate-100 px-5 py-4"><div><h2 class="font-bold">Hospitals</h2><p class="mt-1 text-xs text-slate-500">Staff and patient totals are summaries only.</p></div><a href="{{ route('global-admin.hospitals.index') }}" class="text-sm font-bold text-[#c81020] hover:underline">View all</a></div>
        <div class="divide-y divide-slate-100">
            @forelse ($hospitals as $hospital)
                <a href="{{ route('global-admin.hospitals.edit', $hospital) }}" class="flex flex-col gap-3 px-5 py-4 hover:bg-slate-50 sm:flex-row sm:items-center sm:justify-between">
                    <div><div class="font-bold">{{ $hospital->name }}</div><div class="mt-1 text-xs text-slate-500">{{ $hospital->slug }}</div></div>
                    <div class="flex flex-wrap items-center gap-x-5 gap-y-2 text-sm"><span><strong>{{ number_format($hospital->staff_count) }}</strong> staff</span><span><strong>{{ number_format($hospital->patient_count) }}</strong> patients</span><span class="flex flex-wrap gap-1">@forelse ($hospital->enabled_modules as $module)<span class="rounded-full bg-red-50 px-2 py-1 text-xs font-semibold text-[#a70e1a]">{{ $modules[$module] ?? ucfirst($module) }}</span>@empty<span class="text-xs text-slate-500">No optional features</span>@endforelse</span></div>
                </a>
            @empty <div class="px-5 py-10 text-center text-sm text-slate-500">No hospitals onboarded yet.</div> @endforelse
        </div>
    </section>
</div>
@endsection
