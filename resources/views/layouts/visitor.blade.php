@extends('layouts.fullscreen-layout')

@section('content')
<div class="relative z-1 min-h-screen flex flex-col bg-gradient-to-b from-gray-50 via-white to-gray-50 dark:from-gray-900 dark:via-gray-900 dark:to-gray-900">
    <x-home-header />

    <main class="flex-1">
        @yield('page')
    </main>

    <x-visitor-footer />
</div>
@endsection
