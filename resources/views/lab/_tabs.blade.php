@if (auth()->user()->hasRole('admin', 'lab'))
    <div class="mb-6 flex gap-2 border-b">
        @foreach (['lab.index' => 'Requests', 'lab.tests.index' => 'Department services'] as $route => $label)
            <a href="{{ route($route) }}"
               class="-mb-px border-b-2 px-4 py-2 text-sm font-medium {{ request()->routeIs($route) || ($route === 'lab.tests.index' && request()->routeIs('lab.tests.*')) ? 'border-teal-700 text-teal-800' : 'border-transparent text-slate-500 hover:text-slate-700' }}">
                {{ $label }}
            </a>
        @endforeach
        @if (auth()->user()->hospital?->hasModule('lab_inventory'))
            <a href="{{ route('lab.inventory.index') }}" class="-mb-px border-b-2 px-4 py-2 text-sm font-medium {{ request()->routeIs('lab.inventory.*') ? 'border-teal-700 text-teal-800' : 'border-transparent text-slate-500 hover:text-slate-700' }}">Department inventory</a>
        @endif
    </div>
@endif
