@if ($user->hasRole('patient'))
    <x-portal-layout>
        <x-slot name="header">Notifications</x-slot>
        @include('notifications._list')
    </x-portal-layout>
@else
    <x-app-layout>
        <x-slot name="header">Notifications</x-slot>
        @include('notifications._list')
    </x-app-layout>
@endif
