@extends('layouts.fullscreen-layout', ['hideFullscreenThemeToggle' => true])

@section('content')
<div class="min-h-screen bg-white dark:bg-gray-900">
    <x-home-header />

    <main class="mx-auto max-w-7xl px-4 py-8 sm:px-6 lg:px-8">
        <x-page-layout.renderer :widgets="$widgets" :context="$layoutContext ?? []" />
    </main>
</div>
@endsection
