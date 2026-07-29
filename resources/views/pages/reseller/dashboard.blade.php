@extends('layouts.app')

@section('content')
    <x-common.page-breadcrumb pageTitle="Dashboard" />

    @if (session('success'))
        <div class="mb-4 rounded-lg bg-green-50 dark:bg-green-900/20 px-4 py-3 text-sm text-green-700 dark:text-green-400">{{ session('success') }}</div>
    @endif

    {{-- Header: who you are + your subdomain, front and center --}}
    <div class="mb-6 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <h2 class="text-lg font-semibold text-gray-800 dark:text-white/90">{{ $reseller->name }}</h2>
            <p class="text-sm text-gray-500 dark:text-gray-400">{{ $reseller->tier?->name ? $reseller->tier->name.' plan' : 'No tier assigned yet' }}</p>
        </div>
        <div class="flex items-center gap-2 rounded-lg border border-gray-200 dark:border-gray-700 bg-white dark:bg-white/[0.03] px-3 py-2"
            x-data="{ copied: false }">
            <svg class="h-4 w-4 shrink-0 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M13.828 10.172a4 4 0 010 5.656l-3 3a4 4 0 01-5.656-5.656l1.5-1.5m5.656-5.656l1.5-1.5a4 4 0 015.656 5.656l-3 3a4 4 0 01-5.656 0"/></svg>
            <code class="text-sm text-gray-700 dark:text-gray-300">{{ $reseller->slug }}.{{ config('reseller.domain') }}</code>
            <button type="button" title="Copy"
                @click="navigator.clipboard.writeText('https://{{ $reseller->slug }}.{{ config('reseller.domain') }}'); copied = true; setTimeout(() => copied = false, 1500)"
                class="ml-1 rounded p-1 text-gray-400 hover:bg-gray-100 hover:text-gray-600 dark:hover:bg-gray-800 dark:hover:text-gray-300 transition-colors">
                <svg x-show="!copied" class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"/></svg>
                <svg x-show="copied" x-cloak class="h-4 w-4 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
            </button>
        </div>
    </div>

    {{-- Stat tiles — matches the platform's own dashboard convention (icon badge + number + label) --}}
    <div class="mb-6 grid grid-cols-1 gap-4 sm:grid-cols-3">
        <div class="rounded-2xl border border-gray-200 dark:border-gray-800 bg-white dark:bg-white/[0.03] p-4 sm:p-5">
            <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-brand-50 dark:bg-brand-500/10 text-brand-500 mb-2">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"/></svg>
            </div>
            <h3 class="text-2xl font-bold text-gray-800 dark:text-white/90">{{ $memorialCount }}</h3>
            <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">Client memorials</p>
        </div>
        <div class="rounded-2xl border border-gray-200 dark:border-gray-800 bg-white dark:bg-white/[0.03] p-4 sm:p-5">
            <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-blue-50 dark:bg-blue-500/10 text-blue-500 mb-2">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M17 20h5v-2a4 4 0 00-3-3.87M9 20H4v-2a4 4 0 013-3.87m6-1.13a4 4 0 100-8 4 4 0 000 8zm6 1.13A4 4 0 0018 12"/></svg>
            </div>
            <h3 class="text-2xl font-bold text-gray-800 dark:text-white/90">{{ $clientCount }}</h3>
            <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">Clients</p>
        </div>
        <div class="rounded-2xl border border-gray-200 dark:border-gray-800 bg-white dark:bg-white/[0.03] p-4 sm:p-5">
            <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-amber-50 dark:bg-amber-500/10 text-amber-500 mb-2">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 7h6m-6 4h6m-6 4h4M5 21V5a2 2 0 012-2h10a2 2 0 012 2v16l-4-2-3 2-3-2-4 2z"/></svg>
            </div>
            <h3 class="text-2xl font-bold text-gray-800 dark:text-white/90">{{ $planCount }}</h3>
            <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">Client plans</p>
        </div>
    </div>

    @php
        $steps = [
            ['done' => $planCount > 0, 'label' => 'Create a client plan', 'desc' => 'Set the pricing you offer your own clients.', 'route' => route('reseller.plans')],
            ['done' => (bool) $reseller->logo_path, 'label' => 'Add your branding', 'desc' => 'Upload a logo so your subdomain looks like your business.', 'route' => route('reseller.branding')],
            ['done' => $clientCount > 0, 'label' => 'Add a client', 'desc' => 'Onboard the first family you\'re building a memorial for.', 'route' => route('reseller.clients')],
            ['done' => $memorialCount > 0, 'label' => 'Create their memorial', 'desc' => 'Build it yourself, or invite the client to finish it.', 'route' => route('reseller.memorials.create')],
        ];
        $remaining = collect($steps)->reject(fn ($s) => $s['done']);
    @endphp

    @if ($remaining->isNotEmpty())
        <x-common.component-card title="Get set up" desc="{{ $remaining->count() }} of {{ count($steps) }} steps left">
            <ul class="divide-y divide-gray-100 dark:divide-gray-800">
                @foreach ($steps as $step)
                    <li class="flex items-center justify-between gap-4 py-3 first:pt-0 last:pb-0">
                        <div class="flex items-center gap-3 min-w-0">
                            <span class="flex h-6 w-6 shrink-0 items-center justify-center rounded-full {{ $step['done'] ? 'bg-green-100 text-green-600 dark:bg-green-900/30 dark:text-green-400' : 'border border-gray-300 dark:border-gray-600' }}">
                                @if ($step['done'])
                                    <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"/></svg>
                                @endif
                            </span>
                            <div class="min-w-0">
                                <p class="text-sm font-medium {{ $step['done'] ? 'text-gray-400 dark:text-gray-500 line-through' : 'text-gray-800 dark:text-white/90' }}">{{ $step['label'] }}</p>
                                @if (! $step['done'])
                                    <p class="text-xs text-gray-500 dark:text-gray-400">{{ $step['desc'] }}</p>
                                @endif
                            </div>
                        </div>
                        @if (! $step['done'])
                            <a href="{{ $step['route'] }}" class="btn btn-secondary btn-sm shrink-0">Start</a>
                        @endif
                    </li>
                @endforeach
            </ul>
        </x-common.component-card>
    @endif

    {{-- Quick links --}}
    <div class="mt-6 grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
        @foreach ([
            ['route' => 'reseller.memorials', 'label' => 'Memorials', 'desc' => 'View and manage client memorials', 'color' => 'brand', 'icon' => 'M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253'],
            ['route' => 'reseller.clients', 'label' => 'Clients', 'desc' => 'Manage the families you serve', 'color' => 'blue', 'icon' => 'M17 20h5v-2a4 4 0 00-3-3.87M9 20H4v-2a4 4 0 013-3.87m6-1.13a4 4 0 100-8 4 4 0 000 8zm6 1.13A4 4 0 0018 12'],
            ['route' => 'reseller.plans', 'label' => 'Plans', 'desc' => 'Pricing you offer your clients', 'color' => 'amber', 'icon' => 'M9 7h6m-6 4h6m-6 4h4M5 21V5a2 2 0 012-2h10a2 2 0 012 2v16l-4-2-3 2-3-2-4 2z'],
            ['route' => 'reseller.settings', 'label' => 'Settings', 'desc' => 'Business name, branding, payments', 'color' => 'purple', 'icon' => 'M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z'],
        ] as $link)
            <a href="{{ route($link['route']) }}" class="group rounded-2xl border border-gray-200 dark:border-gray-800 bg-white dark:bg-white/[0.03] p-4 sm:p-5 transition-colors hover:border-brand-300 dark:hover:border-brand-500/50">
                <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-{{ $link['color'] }}-50 dark:bg-{{ $link['color'] }}-500/10 text-{{ $link['color'] }}-500 mb-3">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="{{ $link['icon'] }}"/></svg>
                </div>
                <p class="text-sm font-semibold text-gray-800 dark:text-white/90 group-hover:text-brand-600 dark:group-hover:text-brand-400">{{ $link['label'] }}</p>
                <p class="mt-0.5 text-xs text-gray-500 dark:text-gray-400">{{ $link['desc'] }}</p>
            </a>
        @endforeach
    </div>
@endsection
