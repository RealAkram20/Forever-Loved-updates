@extends('layouts.app')

@section('content')
    <x-common.page-breadcrumb pageTitle="Navigation menus" />

    @if (session('success'))
        <script>document.addEventListener('DOMContentLoaded', () => window.$toast?.('success', @json(session('success'))));</script>
    @endif

    <p class="mb-6 text-sm text-gray-600 dark:text-gray-400">
        Drag items to reorder. Choose a <strong class="font-medium text-gray-800 dark:text-white/90">site route</strong> or <strong class="font-medium text-gray-800 dark:text-white/90">CMS page</strong> from the list, or leave the route on “Custom URL only” and paste a path or full URL. At least one of route or URL should be set.
    </p>

    <p class="mb-6 text-sm text-gray-600 dark:text-gray-400">
        These are the platform's own menus. Resellers build the navigation for their
        white-labeled sites separately, under their own Website → Menus.
    </p>

    @include('partials.menus.editor', [
        'menus' => $menus,
        'menuRouteGroups' => $menuRouteGroups,
        'routes' => [
            'update' => 'settings.menus.items.update',
            'destroy' => 'settings.menus.items.destroy',
            'store' => 'settings.menus.items.store',
            'reorder' => 'settings.menus.reorder',
        ],
    ])
@endsection
