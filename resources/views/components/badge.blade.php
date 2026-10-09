@props(['color' => 'slate'])

@php
    $map = [
        'slate' => 'bg-slate-100 text-slate-700',
        'green' => 'bg-green-100 text-green-700',
        'amber' => 'bg-amber-100 text-amber-800',
        'red'   => 'bg-red-100 text-red-700',
        'blue'  => 'bg-blue-100 text-blue-700',
    ];
@endphp

<span {{ $attributes->class(['inline-flex rounded-full px-2.5 py-0.5 text-xs font-medium', $map[$color] ?? $map['slate']]) }}>
    {{ $slot }}
</span>