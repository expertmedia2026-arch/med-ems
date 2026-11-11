@extends('layouts.global-admin', ['title' => 'Hospitals'])

@section('content')
<div class="space-y-7">
    <div class="flex flex-col justify-between gap-4 sm:flex-row sm:items-end">
        <div><p class="text-xs font-bold uppercase tracking-[.16em] text-[#c81020]">Platform tenants</p><h1 class="mt-2 text-2xl font-bold tracking-tight">Workspaces</h1><p class="mt-1 text-sm text-slate-600">Hospital and standalone pharmacy workspaces.</p></div>
        <a href="{{ route('global-admin.hospitals.create') }}" class="inline-flex items-center justify-center rounded-md bg-[#e51d2a] px-4 py-2.5 text-sm font-bold text-white hover:bg-[#c81020]">+ Add workspace</a>
    </div>
    @if (session('success')) <div class="rounded-lg border border-green-200 bg-green-50 p-4 text-green-800">{{ session('success') }}</div> @endif
    @if (session('warning')) <div class="rounded-lg border border-amber-200 bg-amber-50 p-4 text-amber-800">{{ session('warning') }}</div> @endif
    <div class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
        <div class="overflow-x-auto">
            <table class="min-w-[980px] text-left text-sm">
                <thead class="bg-slate-50 text-xs uppercase tracking-wide text-slate-500"><tr><th class="px-5 py-3">Hospital</th><th class="px-5 py-3">Admin email</th><th class="px-5 py-3">Staff</th><th class="px-5 py-3">Patients</th><th class="px-5 py-3">Features running</th><th class="px-5 py-3">Status</th><th class="px-5 py-3"></th></tr></thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse ($hospitals as $hospital)
                        <tr>
                            <td class="px-5 py-4"><div class="font-bold">{{ $hospital->name }}</div><div class="mt-1 flex items-center gap-2 text-xs text-slate-500"><span>{{ $hospital->slug }}</span><span class="rounded-full bg-teal-50 px-2 py-0.5 font-semibold text-teal-800">{{ $hospital->isStandalonePharmacy() ? 'Standalone Pharmacy' : 'Hospital' }}</span></div></td>
                            <td class="max-w-64 px-5 py-4"><div class="flex flex-col gap-1">
                                @forelse ($hospital->admin_emails as $email)
                                    <a href="mailto:{{ $email }}" class="break-all text-xs font-medium text-[#a70e1a] hover:underline">{{ $email }}</a>
                                @empty <span class="text-xs text-slate-400">No active admin email</span> @endforelse
                            </div></td>
                            <td class="px-5 py-4 font-semibold">{{ number_format($hospital->staff_count) }}</td>
                            <td class="px-5 py-4 font-semibold">{{ number_format($hospital->patient_count) }}</td>
                            <td class="px-5 py-4"><div class="flex max-w-xl flex-wrap gap-1.5">
                                @forelse ($hospital->enabled_modules as $module)
                                    <span class="rounded-full bg-red-50 px-2.5 py-1 text-xs font-semibold text-[#a70e1a]">{{ $modules[$module] ?? ucfirst($module) }}</span>
                                @empty <span class="text-slate-500">No optional features enabled</span> @endforelse
                            </div></td>
                            <td class="px-5 py-4"><div class="flex flex-col items-start gap-2"><span class="inline-flex items-center gap-2 text-xs font-semibold {{ $hospital->is_active ? 'text-green-700' : 'text-slate-500' }}"><span class="h-2 w-2 rounded-full {{ $hospital->is_active ? 'bg-green-500' : 'bg-slate-400' }}"></span>{{ $hospital->is_active ? 'Active' : 'Inactive' }}</span><form method="POST" action="{{ route('global-admin.hospitals.toggle', $hospital) }}">@csrf @method('PATCH')<button class="text-xs font-bold {{ $hospital->is_active ? 'text-[#c81020]' : 'text-green-700' }}">{{ $hospital->is_active ? 'Deactivate' : 'Activate' }}</button></form></div></td>
                            <td class="px-5 py-4 text-right"><a class="font-bold text-[#c81020] hover:underline" href="{{ route('global-admin.hospitals.edit', $hospital) }}">Configure</a></td>
                        </tr>
                    @empty <tr><td colspan="7" class="px-5 py-12 text-center text-slate-500">No hospitals yet. Add your first hospital to get started.</td></tr> @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
