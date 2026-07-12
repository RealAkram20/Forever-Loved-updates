@extends('layouts.fullscreen-layout')

@section('content')
<div class="relative z-1 min-h-screen flex flex-col bg-[var(--color-bg-page)]">
    <x-home-header />

    <main class="flex-1">
        @yield('page')
    </main>

    <x-visitor-footer />
</div>
@endsection
