@extends('layouts.fullscreen-layout', ['hideFullscreenThemeToggle' => true])

@section('content')
<div class="min-h-screen bg-white dark:bg-gray-900">
    <x-home-header />

    <main class="mx-auto max-w-7xl px-4 py-8 sm:px-6 lg:px-8">
        <h1 class="text-2xl font-semibold text-gray-900 dark:text-white/90 mb-6">Find Memorial</h1>

        @include('pages.memorial-directory.partials.directory-app')
    </main>
</div>
@endsection
