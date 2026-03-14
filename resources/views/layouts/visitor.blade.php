@extends('layouts.fullscreen-layout')

@section('content')
<div class="relative z-1 min-h-screen flex flex-col bg-white dark:bg-gray-900">
    <x-home-header />

    <main class="flex-1">
        @yield('page')
    </main>

    <x-visitor-footer />
</div>
@endsection
