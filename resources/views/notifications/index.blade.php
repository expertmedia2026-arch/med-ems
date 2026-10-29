@if ($user->isGlobalAdmin())
    @extends('layouts.global-admin', ['title' => 'Notifications'])
    @section('content')
        @include('notifications._list')
    @endsection
@elseif ($user->hasRole('patient'))
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
