<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">{{ $isManager ? 'Department staff roster' : 'My duty roster' }}</h2>
                <p class="mt-1 text-sm text-gray-500">{{ $isManager ? 'Assign weekly morning and night shifts to staff in your roster scope.' : 'See your assigned shifts and the colleagues working alongside you.' }}</p>
            </div>
            <span class="rounded-full bg-teal-50 px-3 py-1.5 text-sm font-medium text-teal-800">{{ $scheduledShifts }} {{ $isManager ? 'shifts scheduled' : 'shifts assigned this week' }}</span>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">
            @if (session('success'))
                <div class="rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800">{{ session('success') }}</div>
            @endif

            @if ($errors->any())
                <div class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">
                    <p class="font-semibold">Please review the roster details.</p>
                    <ul class="mt-1 list-disc pl-5">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
                </div>
            @endif

            <section class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm">
                <div class="flex flex-wrap items-end justify-between gap-4">
                    <div>
                        <p class="text-sm font-medium text-gray-500">Roster week</p>
                        <h3 class="mt-1 text-lg font-semibold text-gray-900">{{ $weekStart->format('d M') }} – {{ $weekEnd->format('d M Y') }}</h3>
                    </div>
                    <div class="flex flex-wrap items-end gap-3">
                        <a class="rounded-lg border border-gray-300 px-3 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50" href="{{ route('nursing.roster.index', ['week' => $weekStart->copy()->subWeek()->toDateString(), 'department' => $filters['department'], 'search' => $filters['search']]) }}">Previous week</a>
                        <form method="GET" action="{{ route('nursing.roster.index') }}" class="flex items-end gap-2">
                            @if ($isManager)
                                <input type="hidden" name="department" value="{{ $filters['department'] }}">
                                <input type="hidden" name="search" value="{{ $filters['search'] }}">
                            @endif
                            <label class="text-sm text-gray-600">Choose a date
                                <input type="date" name="week" value="{{ $weekStart->toDateString() }}" class="mt-1 block rounded-lg border-gray-300 text-sm shadow-sm focus:border-teal-600 focus:ring-teal-600">
                            </label>
                            <button class="rounded-lg bg-teal-700 px-4 py-2.5 text-sm font-semibold text-white hover:bg-teal-800">View week</button>
                        </form>
                        <a class="rounded-lg border border-gray-300 px-3 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50" href="{{ route('nursing.roster.index', ['week' => $weekStart->copy()->addWeek()->toDateString(), 'department' => $filters['department'], 'search' => $filters['search']]) }}">Next week</a>
                    </div>
                </div>
                <div class="mt-5 grid gap-3 text-sm sm:grid-cols-2">
                    <div class="rounded-lg bg-teal-50 px-4 py-3 text-teal-900"><span class="font-semibold">Morning shift:</span> 07:00–19:00 · 12 hours</div>
                    <div class="rounded-lg bg-slate-100 px-4 py-3 text-slate-800"><span class="font-semibold">Night shift:</span> 19:00–07:00 · 12 hours</div>
                </div>
                <p class="mt-3 text-xs text-gray-500">One shift may be assigned per staff member each day. Night shift is recorded against the date it begins.</p>
            </section>

            @unless ($isManager)
                <section class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm">
                    <h3 class="font-semibold text-gray-900">My assigned shifts</h3>
                    <div class="mt-3 flex flex-wrap gap-2">
                        @forelse ($myDuties->sortBy('duty_date') as $duty)
                            <span class="rounded-full bg-teal-50 px-3 py-1.5 text-sm font-medium text-teal-800">{{ $duty->duty_date->format('D, d M') }} · {{ ucfirst($duty->shift) }}</span>
                        @empty
                            <p class="text-sm text-gray-500">No shifts have been assigned to you for this week.</p>
                        @endforelse
                    </div>
                </section>
            @endunless

            <section class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm">
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <div>
                        <h3 class="font-semibold text-gray-900">Shift coverage</h3>
                        <p class="mt-1 text-sm text-gray-500">{{ $isManager ? 'Scheduled staff coverage in your roster scope.' : 'Your shift assignments and the colleagues in your department on the same shift.' }}</p>
                    </div>
                    <span class="text-sm text-gray-500">{{ $isManager ? $staff->total().' active staff'.($filters['department'] ? ' in selected department' : '') : ($viewer->department?->name ?? 'No department assigned') }}</span>
                </div>
                <div class="mt-4 overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 text-sm">
                        <thead class="bg-gray-50 text-left text-xs uppercase tracking-wide text-gray-500"><tr><th class="px-3 py-2">Day</th><th class="px-3 py-2">Date</th><th class="px-3 py-2">Morning · 07:00–19:00</th><th class="px-3 py-2">Night · 19:00–07:00</th></tr></thead>
                        <tbody class="divide-y divide-gray-100">
                            @foreach ($days as $day)
                                @php $dateKey = $day['date']->toDateString(); @endphp
                                <tr>
                                    <td class="px-3 py-2 font-medium text-gray-800">{{ $day['name'] }}</td>
                                    <td class="px-3 py-2 text-gray-500">{{ $day['date']->format('d M') }}</td>
                                    @foreach (['morning', 'night'] as $shiftName)
                                        @php
                                            $shiftCoverage = $coverage->get($dateKey.':'.$shiftName);
                                            $myShift = $myDuties->first(fn ($duty) => $duty->duty_date->toDateString() === $dateKey && $duty->shift === $shiftName);
                                            $team = $coworkers->get($dateKey.':'.$shiftName, collect());
                                        @endphp
                                        <td class="px-3 py-2">
                                            @if ($isManager)
                                                {{ $shiftCoverage->total ?? 0 }} {{ (int) ($shiftCoverage->total ?? 0) === 1 ? 'staff member' : 'staff members' }}
                                            @elseif ($myShift)
                                                <span class="rounded-full bg-teal-50 px-2.5 py-1 text-xs font-medium text-teal-800">Assigned</span>
                                                @if ($team->isNotEmpty())
                                                    <span class="mt-1 block text-xs text-gray-600">With: {{ $team->map(fn ($duty) => $duty->staffMember?->name)->filter()->unique()->join(', ') }}</span>
                                                @else
                                                    <span class="mt-1 block text-xs text-gray-400">No other department staff assigned</span>
                                                @endif
                                            @else
                                                <span class="text-gray-400">—</span>
                                            @endif
                                        </td>
                                    @endforeach
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </section>

            @if ($isManager)
                <form method="GET" action="{{ route('nursing.roster.index') }}" class="grid gap-3 rounded-xl border border-gray-200 bg-white p-4 shadow-sm sm:grid-cols-[1fr_1fr_auto_auto] sm:items-end">
                    <input type="hidden" name="week" value="{{ $weekStart->toDateString() }}">
                    @if ($departments->count() > 1)
                        <label class="text-sm font-medium text-gray-700">Department
                            <select name="department" class="mt-1 block w-full rounded-lg border-gray-300 text-sm shadow-sm focus:border-teal-600 focus:ring-teal-600">
                                <option value="">All departments</option>
                                @foreach ($departments as $department)
                                    <option value="{{ $department->id }}" @selected((string) $filters['department'] === (string) $department->id)>{{ $department->name }}</option>
                                @endforeach
                            </select>
                        </label>
                    @endif
                    <label class="text-sm font-medium text-gray-700">Find staff member
                        <input type="search" name="search" value="{{ $filters['search'] }}" placeholder="Search by name" class="mt-1 block w-full rounded-lg border-gray-300 text-sm shadow-sm focus:border-teal-600 focus:ring-teal-600">
                    </label>
                    <button class="rounded-lg bg-teal-700 px-4 py-2.5 text-sm font-semibold text-white hover:bg-teal-800">Apply filters</button>
                    <a href="{{ route('nursing.roster.index', ['week' => $weekStart->toDateString()]) }}" class="rounded-lg border border-gray-300 px-4 py-2.5 text-center text-sm font-semibold text-gray-700 hover:bg-gray-50">Clear</a>
                </form>
            @endif

            <div class="flex flex-wrap items-center justify-between gap-3">
                <p class="text-sm text-gray-500">Showing {{ $staff->firstItem() ?? 0 }}–{{ $staff->lastItem() ?? 0 }} of {{ $staff->total() }} staff members</p>
                {{ $staff->onEachSide(1)->links() }}
            </div>

            @forelse ($staff as $staffMember)
                @php
                    $memberDuties = $duties->get($staffMember->id, collect());
                    $useOldInput = (int) old('_staff_id') === $staffMember->id;
                    $oldMorning = $useOldInput ? array_map('intval', (array) old('morning_days', [])) : [];
                    $oldNight = $useOldInput ? array_map('intval', (array) old('night_days', [])) : [];
                @endphp
                <section class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm">
                    <div class="flex flex-wrap items-center justify-between gap-3 border-b border-gray-100 bg-gray-50 px-5 py-4">
                        <div>
                            <h3 class="font-semibold text-gray-900">{{ $staffMember->name }}</h3>
                            <p class="mt-0.5 text-sm text-gray-500">{{ $staffMember->department?->name ?? 'No department assigned' }} · {{ $staffMember->role->label() }}</p>
                        </div>
                        @if ($isManager)
                            <span class="text-xs font-medium uppercase tracking-wide text-gray-500">Edit weekly assignment</span>
                        @else
                            <span class="rounded-full bg-gray-100 px-3 py-1 text-xs font-medium text-gray-600">Read only</span>
                        @endif
                    </div>

                    @if ($isManager)
                        <form method="POST" action="{{ route('nursing.roster.update', ['staff' => $staffMember, 'week' => $weekStart->toDateString(), 'department' => $filters['department'], 'search' => $filters['search'], 'page' => $staff->currentPage()]) }}">
                            @csrf
                            @method('PUT')
                            <input type="hidden" name="week_start" value="{{ $weekStart->toDateString() }}">
                            <input type="hidden" name="_staff_id" value="{{ $staffMember->id }}">
                    @endif

                    <div class="overflow-x-auto">
                        <div class="min-w-[900px] grid grid-cols-7 divide-x divide-gray-100">
                            @foreach ($days as $day)
                                <div class="p-4">
                                    <div class="mb-3 text-center">
                                        <p class="text-sm font-semibold text-gray-800">{{ $day['name'] }}</p>
                                        <p class="text-xs text-gray-500">{{ $day['date']->format('d M') }}</p>
                                    </div>
                                    @foreach ([['value' => 'morning', 'label' => 'Morning'], ['value' => 'night', 'label' => 'Night']] as $shift)
                                        @php
                                            $checked = $useOldInput
                                                ? in_array($day['number'], $shift['value'] === 'morning' ? $oldMorning : $oldNight, true)
                                                : $memberDuties->contains(fn ($duty) => $duty->duty_date->toDateString() === $day['date']->toDateString() && $duty->shift === $shift['value']);
                                        @endphp
                                        <label class="mb-2 flex items-center gap-2 rounded-lg border {{ $checked ? 'border-teal-200 bg-teal-50 text-teal-900' : 'border-gray-200 text-gray-600' }} px-2.5 py-2 text-xs hover:bg-teal-50">
                                            <input type="checkbox" name="{{ $shift['value'] }}_days[]" value="{{ $day['number'] }}" @checked($checked) @disabled(! $isManager) class="rounded border-gray-300 text-teal-700 shadow-sm focus:ring-teal-600 disabled:opacity-70">
                                            <span>{{ $shift['label'] }}@if ($checked) <span class="font-semibold">· Assigned</span>@endif</span>
                                        </label>
                                    @endforeach
                                </div>
                            @endforeach
                        </div>
                    </div>
                    @if ($isManager)
                        <div class="flex justify-end border-t border-gray-100 px-5 py-3">
                            <button type="submit" class="rounded-lg bg-teal-700 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-teal-800 focus:outline-none focus:ring-2 focus:ring-teal-600 focus:ring-offset-2">Save {{ $staffMember->name }}’s roster</button>
                        </div>
                        </form>
                    @endif
                </section>
            @empty
                <div class="rounded-xl border border-dashed border-gray-300 bg-white px-6 py-12 text-center">
                    <h3 class="font-semibold text-gray-900">No staff to roster</h3>
                    <p class="mt-1 text-sm text-gray-500">Active staff in your roster scope will appear here.</p>
                </div>
            @endforelse
            <div class="flex justify-end">{{ $staff->onEachSide(1)->links() }}</div>
        </div>
    </div>
</x-app-layout>
