{{--
    The A-Plus shell.

    hideFullscreenThemeToggle: this template is a committed light design — a navy hero over a
    white page, with two brand colours that have no dark counterpart worth switching to. A
    floating sun/moon button in the corner of a funeral home's front page reads as unserious,
    and the same call was made on Dignified for the same reason.
--}}
@extends('layouts.fullscreen-layout', ['hideFullscreenThemeToggle' => true])

@push('head')
@include('ap-theme-style')
@endpush

@section('content')
<div class="flex min-h-screen flex-col bg-[var(--ap-paper)] text-[var(--ap-ink)]">
    <x-home-header />

    <main class="flex-1">
        @yield('page')
    </main>

    <x-visitor-footer />
</div>
@endsection
