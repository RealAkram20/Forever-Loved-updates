@extends('layouts.app')

@section('content')
    <x-common.page-breadcrumb pageTitle="Navigation menus" />

    @if (session('success'))
        <script>document.addEventListener('DOMContentLoaded', () => window.$toast?.('success', @json(session('success'))));</script>
    @endif

    <div class="mb-6 space-y-2">
        <p class="text-sm text-gray-600 dark:text-gray-400">
            The header and footer navigation on your own site,
            <a href="{{ $reseller->publicBaseUrl() }}" target="_blank" rel="noopener noreferrer"
               class="font-medium text-brand-600 hover:underline dark:text-brand-400">{{ $reseller->publicDisplayAddress() }}</a>.
            Drag items to reorder. Pick a destination from the list, or leave it on
            “Custom URL only” and paste a path or full address.
        </p>
        <p class="text-sm text-gray-600 dark:text-gray-400">
            While a menu has no items, your site falls back to a simple default. Only your own
            pages appear in the list — nothing here links a visitor back to us.
        </p>
    </div>

    @include('partials.menus.editor', [
        'menus' => $menus,
        'menuRouteGroups' => $menuRouteGroups,
        'routes' => [
            'update' => 'reseller.menus.items.update',
            'destroy' => 'reseller.menus.items.destroy',
            'store' => 'reseller.menus.items.store',
            'reorder' => 'reseller.menus.reorder',
        ],
    ])
@endsection
