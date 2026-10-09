<div class="mb-6 flex gap-2 border-b">
    @foreach (['pharmacy.index' => 'Dispensing queue', 'pharmacy.inventory' => 'Inventory'] as $route => $label)
        <a href="{{ route($route) }}"
           class="-mb-px border-b-2 px-4 py-2 text-sm font-medium {{ request()->routeIs($route) ? 'border-teal-700 text-teal-800' : 'border-transparent text-slate-500 hover:text-slate-700' }}">
            {{ $label }}
        </a>
    @endforeach
</div>