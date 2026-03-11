@extends('layouts.app')

@section('content')
    <x-common.page-breadcrumb pageTitle="Dashboard" />

    {{-- ═══════════════════════════════════════════════════════════════
         ADMIN / SUPER-ADMIN SECTION
         ═══════════════════════════════════════════════════════════════ --}}
    @if ($isAdmin)
        {{-- Period Filter --}}
        <div class="mb-6 flex flex-col sm:flex-row items-start sm:items-center justify-between gap-3">
            <h2 class="text-lg font-semibold text-gray-800 dark:text-white/90">Platform Overview</h2>
            <form method="GET" action="{{ route('dashboard') }}" class="flex items-center gap-2" id="adminFilterForm">
                @if (request('memorial_id'))
                    <input type="hidden" name="memorial_id" value="{{ request('memorial_id') }}" />
                @endif
                <select name="period" onchange="document.getElementById('adminFilterForm').submit()"
                    class="h-10 rounded-lg border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 px-3 pr-8 text-sm text-gray-700 dark:text-gray-300 focus:border-brand-300 focus:ring-3 focus:ring-brand-500/10 focus:outline-hidden">
                    @foreach ([
                        'all'        => 'All Time',
                        'today'      => 'Today',
                        'this_week'  => 'This Week',
                        'this_month' => 'This Month',
                        'this_year'  => 'This Year',
                    ] as $value => $label)
                        <option value="{{ $value }}" {{ ($period ?? 'all') === $value ? 'selected' : '' }}>{{ $label }}</option>
                    @endforeach
                </select>
            </form>
        </div>

        {{-- Admin Metric Cards --}}
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4 mb-6">
            {{-- Registered Users --}}
            <div class="rounded-2xl border border-gray-200 dark:border-gray-800 bg-white dark:bg-white/[0.03] p-5">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm text-gray-500 dark:text-gray-400">Registered Users</p>
                        <h3 class="mt-1 text-2xl font-bold text-gray-800 dark:text-white/90">{{ number_format($registeredUsers) }}</h3>
                    </div>
                    <div class="flex h-12 w-12 items-center justify-center rounded-xl bg-purple-50 dark:bg-purple-500/10 text-purple-500">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"/></svg>
                    </div>
                </div>
                <p class="mt-3 text-sm text-gray-500 dark:text-gray-400">
                    {{ ($period ?? 'all') === 'all' ? 'Total platform members' : 'New in selected period' }}
                </p>
            </div>

            {{-- Active Subscriptions --}}
            <div class="rounded-2xl border border-gray-200 dark:border-gray-800 bg-white dark:bg-white/[0.03] p-5">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm text-gray-500 dark:text-gray-400">Active Subscriptions</p>
                        <h3 class="mt-1 text-2xl font-bold text-gray-800 dark:text-white/90">{{ number_format($activeSubscriptions) }}</h3>
                    </div>
                    <div class="flex h-12 w-12 items-center justify-center rounded-xl bg-green-50 dark:bg-green-500/10 text-green-500">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    </div>
                </div>
                <p class="mt-3 text-sm text-gray-500 dark:text-gray-400">Currently active plans</p>
            </div>

            {{-- Total Memorials --}}
            <div class="rounded-2xl border border-gray-200 dark:border-gray-800 bg-white dark:bg-white/[0.03] p-5">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm text-gray-500 dark:text-gray-400">Total Memorials</p>
                        <h3 class="mt-1 text-2xl font-bold text-gray-800 dark:text-white/90">{{ number_format($totalMemorials) }}</h3>
                    </div>
                    <div class="flex h-12 w-12 items-center justify-center rounded-xl bg-brand-50 dark:bg-brand-500/10 text-brand-500">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"/></svg>
                    </div>
                </div>
                <p class="mt-3 text-sm text-gray-500 dark:text-gray-400">
                    <span class="text-green-600 dark:text-green-400 font-medium">{{ $activeMemorials }}</span> active
                </p>
            </div>

            {{-- Total Sales --}}
            <div class="rounded-2xl border border-gray-200 dark:border-gray-800 bg-white dark:bg-white/[0.03] p-5">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm text-gray-500 dark:text-gray-400">Total Sales</p>
                        <h3 class="mt-1 text-2xl font-bold text-gray-800 dark:text-white/90">{{ $currency }} {{ number_format($totalSalesAmount, 2) }}</h3>
                    </div>
                    <div class="flex h-12 w-12 items-center justify-center rounded-xl bg-amber-50 dark:bg-amber-500/10 text-amber-500">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    </div>
                </div>
                <p class="mt-3 text-sm text-gray-500 dark:text-gray-400">
                    <span class="font-medium text-gray-700 dark:text-gray-300">{{ $totalSalesCount }}</span> paid subscriptions
                </p>
            </div>
        </div>

        {{-- Admin: Growth Chart + Top Memorials + Recent Users --}}
        <div class="grid grid-cols-1 gap-6 xl:grid-cols-12 mb-6">
            {{-- Monthly Growth --}}
            <div class="xl:col-span-7">
                <div class="rounded-2xl border border-gray-200 dark:border-gray-800 bg-white dark:bg-white/[0.03] p-5 lg:p-6">
                    <h3 class="text-base font-medium text-gray-800 dark:text-white/90 mb-4">Monthly Growth ({{ now()->year }})</h3>
                    <div class="h-[260px]" x-data="growthChart()" x-init="init()">
                        <canvas x-ref="canvas" class="w-full h-full"></canvas>
                    </div>
                </div>
            </div>

            {{-- Top Memorials by Views --}}
            <div class="xl:col-span-5">
                <div class="rounded-2xl border border-gray-200 dark:border-gray-800 bg-white dark:bg-white/[0.03]">
                    <div class="px-5 py-4 sm:px-6">
                        <h3 class="text-base font-medium text-gray-800 dark:text-white/90">Top Memorials</h3>
                        <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">By total views</p>
                    </div>
                    <div class="border-t border-gray-100 dark:border-gray-800 divide-y divide-gray-100 dark:divide-gray-800">
                        @forelse ($topMemorials as $m)
                            <div class="flex items-center justify-between px-5 py-3 sm:px-6">
                                <div class="flex items-center gap-3 min-w-0">
                                    <div class="h-9 w-9 shrink-0 rounded-full bg-gray-100 dark:bg-gray-800 overflow-hidden flex items-center justify-center text-xs font-bold text-gray-500 dark:text-gray-400">
                                        @if ($m->profile_photo_url)
                                            <img src="{{ $m->profile_photo_url }}" class="h-full w-full object-cover" />
                                        @else
                                            {{ strtoupper(substr($m->full_name, 0, 1)) }}
                                        @endif
                                    </div>
                                    <div class="min-w-0">
                                        <p class="truncate text-sm font-medium text-gray-800 dark:text-white/90">{{ $m->full_name }}</p>
                                        <p class="truncate text-xs text-gray-500 dark:text-gray-400">{{ $m->birth_death_years }}</p>
                                    </div>
                                </div>
                                <div class="shrink-0 ml-3 text-right">
                                    <p class="text-sm font-medium text-gray-800 dark:text-white/90">{{ number_format($m->views_count) }}</p>
                                    <p class="text-xs text-gray-500 dark:text-gray-400">{{ $m->shares_count }} shares</p>
                                </div>
                            </div>
                        @empty
                            <div class="px-6 py-8 text-center text-sm text-gray-500 dark:text-gray-400">No memorial data yet.</div>
                        @endforelse
                    </div>
                </div>
            </div>
        </div>

        {{-- Admin: Recent Memorials + Recent Users --}}
        <div class="grid grid-cols-1 gap-6 xl:grid-cols-12 mb-6">
            {{-- Recent Memorials --}}
            <div class="xl:col-span-7">
                <div class="rounded-2xl border border-gray-200 dark:border-gray-800 bg-white dark:bg-white/[0.03]">
                    <div class="flex items-center justify-between px-5 py-4 sm:px-6">
                        <h3 class="text-base font-medium text-gray-800 dark:text-white/90">Recent Memorials</h3>
                        <a href="{{ route('memorials.index') }}" class="text-sm font-medium text-brand-500 hover:text-brand-600 transition">View all</a>
                    </div>
                    <div class="border-t border-gray-100 dark:border-gray-800">
                        @if ($recentMemorials->isEmpty())
                            <div class="px-6 py-10 text-center">
                                <p class="text-sm text-gray-500 dark:text-gray-400">No memorials yet.</p>
                            </div>
                        @else
                            <div class="overflow-x-auto">
                                <table class="w-full text-sm">
                                    <thead>
                                        <tr class="border-b border-gray-100 dark:border-gray-800">
                                            <th class="px-5 py-3 text-left font-medium text-gray-500 dark:text-gray-400 sm:px-6">Memorial</th>
                                            <th class="px-5 py-3 text-left font-medium text-gray-500 dark:text-gray-400 sm:px-6 hidden sm:table-cell">Status</th>
                                            <th class="px-5 py-3 text-left font-medium text-gray-500 dark:text-gray-400 sm:px-6 hidden md:table-cell">Owner</th>
                                            <th class="px-5 py-3 text-right font-medium text-gray-500 dark:text-gray-400 sm:px-6">Created</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                                        @foreach ($recentMemorials as $memorial)
                                            <tr class="hover:bg-gray-50 dark:hover:bg-white/[0.02] transition-colors">
                                                <td class="px-5 py-3 sm:px-6">
                                                    <a href="{{ route('memorials.show', $memorial) }}" class="flex items-center gap-3 group">
                                                        <div class="h-9 w-9 shrink-0 overflow-hidden rounded-full bg-gray-100 dark:bg-gray-800">
                                                            @if ($memorial->profile_photo_url)
                                                                <img src="{{ $memorial->profile_photo_url }}" class="h-full w-full object-cover" />
                                                            @else
                                                                <div class="flex h-full w-full items-center justify-center text-xs font-bold text-gray-400 dark:text-gray-500">
                                                                    {{ strtoupper(substr($memorial->full_name, 0, 1)) }}
                                                                </div>
                                                            @endif
                                                        </div>
                                                        <p class="truncate font-medium text-gray-800 dark:text-white/90 group-hover:text-brand-500 transition-colors">{{ $memorial->full_name }}</p>
                                                    </a>
                                                </td>
                                                <td class="px-5 py-3 sm:px-6 hidden sm:table-cell">
                                                    <span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium
                                                        {{ ($memorial->status ?? 'active') === 'active' ? 'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-400' : '' }}
                                                        {{ ($memorial->status ?? '') === 'suspended' ? 'bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-400' : '' }}
                                                        {{ ($memorial->status ?? '') === 'deactivated' ? 'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-300' : '' }}">
                                                        {{ ucfirst($memorial->status ?? 'active') }}
                                                    </span>
                                                </td>
                                                <td class="px-5 py-3 sm:px-6 text-gray-500 dark:text-gray-400 hidden md:table-cell">
                                                    {{ $memorial->owner?->name ?? '—' }}
                                                </td>
                                                <td class="px-5 py-3 sm:px-6 text-right text-gray-500 dark:text-gray-400 text-xs">
                                                    {{ $memorial->created_at->format('M d, Y') }}
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        @endif
                    </div>
                </div>
            </div>

            {{-- Recent Users --}}
            <div class="xl:col-span-5">
                <div class="rounded-2xl border border-gray-200 dark:border-gray-800 bg-white dark:bg-white/[0.03]">
                    <div class="flex items-center justify-between px-5 py-4 sm:px-6">
                        <h3 class="text-base font-medium text-gray-800 dark:text-white/90">Recent Users</h3>
                        <a href="{{ route('users.index') }}" class="text-sm font-medium text-brand-500 hover:text-brand-600 transition">Manage</a>
                    </div>
                    <div class="border-t border-gray-100 dark:border-gray-800 divide-y divide-gray-100 dark:divide-gray-800">
                        @forelse ($recentUsers as $u)
                            <div class="flex items-center justify-between px-5 py-3 sm:px-6">
                                <div class="flex items-center gap-3 min-w-0">
                                    <div class="h-9 w-9 shrink-0 rounded-full overflow-hidden bg-gray-100 dark:bg-gray-800 flex items-center justify-center text-xs font-bold text-gray-500 dark:text-gray-400">
                                        @if ($u->profile_photo_url)
                                            <img src="{{ $u->profile_photo_url }}" class="h-full w-full object-cover" />
                                        @else
                                            {{ strtoupper(substr($u->name, 0, 1)) }}
                                        @endif
                                    </div>
                                    <div class="min-w-0">
                                        <p class="truncate text-sm font-medium text-gray-800 dark:text-white/90">{{ $u->name }}</p>
                                        <p class="truncate text-xs text-gray-500 dark:text-gray-400">{{ $u->email }}</p>
                                    </div>
                                </div>
                                <span class="shrink-0 ml-3 inline-flex items-center rounded-full bg-gray-100 dark:bg-gray-800 px-2.5 py-0.5 text-xs font-medium text-gray-600 dark:text-gray-400">
                                    {{ $u->memorials_count }} memorial{{ $u->memorials_count !== 1 ? 's' : '' }}
                                </span>
                            </div>
                        @empty
                            <div class="px-6 py-8 text-center text-sm text-gray-500 dark:text-gray-400">No users yet.</div>
                        @endforelse
                    </div>
                </div>

                {{-- Quick Actions --}}
                <div class="mt-6 rounded-2xl border border-gray-200 dark:border-gray-800 bg-white dark:bg-white/[0.03] p-5">
                    <h3 class="text-base font-medium text-gray-800 dark:text-white/90 mb-4">Quick Actions</h3>
                    <div class="grid grid-cols-2 gap-3">
                        <a href="{{ route('memorials.create') }}" class="flex items-center gap-2.5 rounded-xl border border-gray-200 dark:border-gray-700 p-3 text-sm font-medium text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-white/[0.03] transition">
                            <svg class="w-5 h-5 text-brand-500 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                            New Memorial
                        </a>
                        <a href="{{ route('settings.general') }}" class="flex items-center gap-2.5 rounded-xl border border-gray-200 dark:border-gray-700 p-3 text-sm font-medium text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-white/[0.03] transition">
                            <svg class="w-5 h-5 text-gray-500 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.066 2.573c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.573 1.066c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.066-2.573c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                            Settings
                        </a>
                        <a href="{{ route('users.index') }}" class="flex items-center gap-2.5 rounded-xl border border-gray-200 dark:border-gray-700 p-3 text-sm font-medium text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-white/[0.03] transition">
                            <svg class="w-5 h-5 text-purple-500 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"/></svg>
                            Users
                        </a>
                        <a href="{{ route('settings.plans') }}" class="flex items-center gap-2.5 rounded-xl border border-gray-200 dark:border-gray-700 p-3 text-sm font-medium text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-white/[0.03] transition">
                            <svg class="w-5 h-5 text-amber-500 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"/></svg>
                            Plans
                        </a>
                    </div>
                </div>
            </div>
        </div>

        @if ($isAdmin && isset($userStats) && $userStats)
            <hr class="border-gray-200 dark:border-gray-800 my-8" />
        @endif
    @endif

    {{-- ═══════════════════════════════════════════════════════════════
         USER SECTION (all authenticated users, including admins for their own memorials)
         ═══════════════════════════════════════════════════════════════ --}}
    @if (isset($userStats) && $userStats)
        {{-- Memorial Filter --}}
        <div class="mb-6 flex flex-col sm:flex-row items-start sm:items-center justify-between gap-3">
            <h2 class="text-lg font-semibold text-gray-800 dark:text-white/90">
                {{ $isAdmin ? 'My Memorials' : 'My Dashboard' }}
            </h2>
            @if ($userMemorials->count() > 1)
                <form method="GET" action="{{ route('dashboard') }}" class="flex items-center gap-2" id="memorialFilterForm">
                    @if ($isAdmin && isset($period))
                        <input type="hidden" name="period" value="{{ $period }}" />
                    @endif
                    <select name="memorial_id" onchange="document.getElementById('memorialFilterForm').submit()"
                        class="h-10 rounded-lg border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 px-3 pr-8 text-sm text-gray-700 dark:text-gray-300 focus:border-brand-300 focus:ring-3 focus:ring-brand-500/10 focus:outline-hidden">
                        <option value="all" {{ ($selectedMemorialId ?? 'all') === 'all' ? 'selected' : '' }}>All Memorials</option>
                        @foreach ($userMemorials as $mem)
                            <option value="{{ $mem->id }}" {{ ($selectedMemorialId ?? '') == $mem->id ? 'selected' : '' }}>{{ $mem->full_name }}</option>
                        @endforeach
                    </select>
                </form>
            @endif
        </div>

        {{-- User Metric Cards --}}
        <div class="grid grid-cols-2 gap-4 sm:grid-cols-3 xl:grid-cols-5 mb-6">
            <div class="rounded-2xl border border-gray-200 dark:border-gray-800 bg-white dark:bg-white/[0.03] p-4 sm:p-5">
                <div class="flex items-center justify-between mb-2">
                    <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-brand-50 dark:bg-brand-500/10 text-brand-500">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"/></svg>
                    </div>
                </div>
                <h3 class="text-2xl font-bold text-gray-800 dark:text-white/90">{{ $userStats['totalMemorials'] }}</h3>
                <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">Total Memorials</p>
            </div>

            <div class="rounded-2xl border border-gray-200 dark:border-gray-800 bg-white dark:bg-white/[0.03] p-4 sm:p-5">
                <div class="flex items-center justify-between mb-2">
                    <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-blue-50 dark:bg-blue-500/10 text-blue-500">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                    </div>
                </div>
                <h3 class="text-2xl font-bold text-gray-800 dark:text-white/90">{{ number_format($userStats['totalVisits']) }}</h3>
                <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">Total Visits</p>
            </div>

            <div class="rounded-2xl border border-gray-200 dark:border-gray-800 bg-white dark:bg-white/[0.03] p-4 sm:p-5">
                <div class="flex items-center justify-between mb-2">
                    <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-green-50 dark:bg-green-500/10 text-green-500">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M8.684 13.342C8.886 12.938 9 12.482 9 12c0-.482-.114-.938-.316-1.342m0 2.684a3 3 0 110-2.684m0 2.684l6.632 3.316m-6.632-6l6.632-3.316m0 0a3 3 0 105.367-2.684 3 3 0 00-5.367 2.684zm0 9.316a3 3 0 105.368 2.684 3 3 0 00-5.368-2.684z"/></svg>
                    </div>
                </div>
                <h3 class="text-2xl font-bold text-gray-800 dark:text-white/90">{{ number_format($userStats['totalShares']) }}</h3>
                <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">Total Shares</p>
            </div>

            <div class="rounded-2xl border border-gray-200 dark:border-gray-800 bg-white dark:bg-white/[0.03] p-4 sm:p-5">
                <div class="flex items-center justify-between mb-2">
                    <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-amber-50 dark:bg-amber-500/10 text-amber-500">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z"/></svg>
                    </div>
                </div>
                <h3 class="text-2xl font-bold text-gray-800 dark:text-white/90">{{ number_format($userStats['totalTributes']) }}</h3>
                <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">Total Tributes</p>
            </div>

            <div class="rounded-2xl border border-gray-200 dark:border-gray-800 bg-white dark:bg-white/[0.03] p-4 sm:p-5">
                <div class="flex items-center justify-between mb-2">
                    <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-purple-50 dark:bg-purple-500/10 text-purple-500">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"/></svg>
                    </div>
                </div>
                <h3 class="text-2xl font-bold text-gray-800 dark:text-white/90">{{ number_format($userStats['totalChapters']) }}</h3>
                <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">Life Chapters</p>
            </div>
        </div>

        {{-- User: Visits Breakdown + Weekly Chart --}}
        <div class="grid grid-cols-1 gap-6 xl:grid-cols-12 mb-6">
            {{-- Visit Breakdown --}}
            <div class="xl:col-span-4">
                <div class="rounded-2xl border border-gray-200 dark:border-gray-800 bg-white dark:bg-white/[0.03] p-5 lg:p-6 h-full">
                    <h3 class="text-base font-medium text-gray-800 dark:text-white/90 mb-5">Visit Breakdown</h3>
                    <div class="space-y-4">
                        <div class="flex items-center justify-between">
                            <span class="text-sm text-gray-500 dark:text-gray-400">Today</span>
                            <span class="text-sm font-semibold text-gray-800 dark:text-white/90">{{ number_format($userStats['visitsToday']) }}</span>
                        </div>
                        <div class="flex items-center justify-between">
                            <span class="text-sm text-gray-500 dark:text-gray-400">Last 7 days</span>
                            <span class="text-sm font-semibold text-gray-800 dark:text-white/90">{{ number_format($userStats['visitsThisWeek']) }}</span>
                        </div>
                        <div class="flex items-center justify-between">
                            <span class="text-sm text-gray-500 dark:text-gray-400">Last 30 days</span>
                            <span class="text-sm font-semibold text-gray-800 dark:text-white/90">{{ number_format($userStats['visitsThisMonth']) }}</span>
                        </div>
                        <div class="flex items-center justify-between border-t border-gray-100 dark:border-gray-800 pt-4">
                            <span class="text-sm font-medium text-gray-700 dark:text-gray-300">All time</span>
                            <span class="text-sm font-bold text-brand-600 dark:text-brand-400">{{ number_format($userStats['totalVisits']) }}</span>
                        </div>
                    </div>

                    @if (!empty($userStats['sharesByType']))
                        <h4 class="text-sm font-medium text-gray-700 dark:text-gray-300 mt-6 mb-3">Shares by Platform</h4>
                        <div class="space-y-2">
                            @foreach ($userStats['sharesByType'] as $type => $count)
                                <div class="flex items-center justify-between">
                                    <span class="text-sm text-gray-500 dark:text-gray-400 capitalize">{{ $type }}</span>
                                    <span class="text-sm font-medium text-gray-800 dark:text-white/90">{{ $count }}</span>
                                </div>
                            @endforeach
                        </div>
                    @endif
                </div>
            </div>

            {{-- Weekly Visits Chart --}}
            <div class="xl:col-span-8">
                <div class="rounded-2xl border border-gray-200 dark:border-gray-800 bg-white dark:bg-white/[0.03] p-5 lg:p-6 h-full">
                    <h3 class="text-base font-medium text-gray-800 dark:text-white/90 mb-4">Visits (Last 7 Days)</h3>
                    <div class="h-[240px]" x-data="visitsChart()" x-init="init()">
                        <canvas x-ref="canvas" class="w-full h-full"></canvas>
                    </div>
                </div>
            </div>
        </div>

        {{-- User: Tribute Breakdown + Recent Tributes --}}
        <div class="grid grid-cols-1 gap-6 xl:grid-cols-12 mb-6">
            {{-- Tribute Breakdown --}}
            <div class="xl:col-span-4">
                <div class="rounded-2xl border border-gray-200 dark:border-gray-800 bg-white dark:bg-white/[0.03] p-5 lg:p-6 h-full">
                    <h3 class="text-base font-medium text-gray-800 dark:text-white/90 mb-5">Tributes Received</h3>
                    @if (!empty($userStats['tributesByType']))
                        <div class="space-y-3">
                            @php
                                $tributeIcons = [
                                    'flower' => ['icon' => '🌸', 'label' => 'Flowers', 'color' => 'bg-pink-100 dark:bg-pink-900/30'],
                                    'candle' => ['icon' => '🕯️', 'label' => 'Candles', 'color' => 'bg-amber-100 dark:bg-amber-900/30'],
                                    'note'   => ['icon' => '📝', 'label' => 'Notes', 'color' => 'bg-blue-100 dark:bg-blue-900/30'],
                                    'image'  => ['icon' => '📷', 'label' => 'Images', 'color' => 'bg-purple-100 dark:bg-purple-900/30'],
                                ];
                            @endphp
                            @foreach ($userStats['tributesByType'] as $type => $count)
                                @php $meta = $tributeIcons[$type] ?? ['icon' => '💝', 'label' => ucfirst($type), 'color' => 'bg-gray-100 dark:bg-gray-800']; @endphp
                                <div class="flex items-center gap-3">
                                    <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl {{ $meta['color'] }} text-lg">
                                        {{ $meta['icon'] }}
                                    </div>
                                    <div class="flex-1 min-w-0">
                                        <div class="flex items-center justify-between">
                                            <span class="text-sm text-gray-700 dark:text-gray-300">{{ $meta['label'] }}</span>
                                            <span class="text-sm font-semibold text-gray-800 dark:text-white/90">{{ $count }}</span>
                                        </div>
                                        @php $pct = $userStats['totalTributes'] > 0 ? round(($count / $userStats['totalTributes']) * 100) : 0; @endphp
                                        <div class="mt-1 h-1.5 w-full rounded-full bg-gray-200 dark:bg-gray-700">
                                            <div class="h-full rounded-full bg-brand-500" style="width: {{ $pct }}%"></div>
                                        </div>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @else
                        <p class="text-sm text-gray-500 dark:text-gray-400">No tributes received yet.</p>
                    @endif
                </div>
            </div>

            {{-- Recent Tributes --}}
            <div class="xl:col-span-8">
                <div class="rounded-2xl border border-gray-200 dark:border-gray-800 bg-white dark:bg-white/[0.03]">
                    <div class="px-5 py-4 sm:px-6">
                        <h3 class="text-base font-medium text-gray-800 dark:text-white/90">Recent Tributes</h3>
                    </div>
                    <div class="border-t border-gray-100 dark:border-gray-800 divide-y divide-gray-100 dark:divide-gray-800">
                        @forelse ($userStats['recentTributes'] as $tribute)
                            <div class="flex items-start gap-3 px-5 py-3 sm:px-6">
                                <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-gray-100 dark:bg-gray-800 text-xs font-bold text-gray-500 dark:text-gray-400">
                                    @if ($tribute->user)
                                        {{ strtoupper(substr($tribute->user->name, 0, 1)) }}
                                    @else
                                        {{ strtoupper(substr($tribute->guest_name ?? '?', 0, 1)) }}
                                    @endif
                                </div>
                                <div class="min-w-0 flex-1">
                                    <div class="flex items-center gap-2">
                                        <p class="text-sm font-medium text-gray-800 dark:text-white/90 truncate">
                                            {{ $tribute->user?->name ?? $tribute->guest_name ?? 'Anonymous' }}
                                        </p>
                                        <span class="shrink-0 text-xs text-gray-400 dark:text-gray-500">{{ $tribute->created_at->diffForHumans() }}</span>
                                    </div>
                                    <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">
                                        Left a <span class="font-medium capitalize">{{ $tribute->type }}</span>
                                        on <span class="font-medium">{{ $tribute->memorial?->full_name }}</span>
                                    </p>
                                    @if ($tribute->message)
                                        <p class="text-sm text-gray-600 dark:text-gray-300 mt-1 line-clamp-2">{{ $tribute->message }}</p>
                                    @endif
                                </div>
                            </div>
                        @empty
                            <div class="px-6 py-8 text-center text-sm text-gray-500 dark:text-gray-400">No tributes yet.</div>
                        @endforelse
                    </div>
                </div>
            </div>
        </div>

        {{-- Create Memorial CTA for non-admin users --}}
        @if (!$isAdmin)
            <a href="{{ route('memorials.create') }}"
                class="block rounded-2xl border border-dashed border-brand-300 dark:border-brand-600 bg-brand-50/50 dark:bg-brand-500/5 p-5 flex items-center gap-4 hover:bg-brand-50 dark:hover:bg-brand-500/10 transition group">
                <div class="flex h-12 w-12 items-center justify-center rounded-xl bg-brand-500 text-white group-hover:scale-105 transition-transform">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                </div>
                <div>
                    <p class="font-medium text-gray-800 dark:text-white/90">Create a New Memorial</p>
                    <p class="text-sm text-gray-500 dark:text-gray-400">Honor another loved one</p>
                </div>
            </a>
        @endif

    @elseif (!$isAdmin)
        {{-- No memorials yet --}}
        <div class="rounded-2xl border border-gray-200 dark:border-gray-800 bg-white dark:bg-white/[0.03] p-8 text-center">
            <div class="mx-auto flex h-16 w-16 items-center justify-center rounded-full bg-brand-50 dark:bg-brand-500/10 text-brand-500 mb-4">
                <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"/></svg>
            </div>
            <h3 class="text-lg font-semibold text-gray-800 dark:text-white/90 mb-2">Welcome to Forever Loved</h3>
            <p class="text-sm text-gray-500 dark:text-gray-400 mb-6 max-w-md mx-auto">You haven't created any memorials yet. Create your first memorial to start honoring a loved one.</p>
            <a href="{{ route('memorials.create') }}"
                class="inline-flex items-center gap-2 rounded-lg bg-brand-500 px-6 py-3 text-sm font-medium text-white hover:bg-brand-600 transition">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                Create Your First Memorial
            </a>
        </div>
    @endif
@endsection

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js@4/dist/chart.umd.min.js"></script>
<script>
@if ($isAdmin && isset($monthlyGrowth))
function growthChart() {
    return {
        init() {
            const data = @json($monthlyGrowth);
            const isDark = document.documentElement.classList.contains('dark');
            const gridColor = isDark ? 'rgba(255,255,255,0.06)' : 'rgba(0,0,0,0.06)';
            const textColor = isDark ? 'rgba(255,255,255,0.5)' : 'rgba(0,0,0,0.4)';

            new Chart(this.$refs.canvas, {
                type: 'bar',
                data: {
                    labels: data.map(d => d.label),
                    datasets: [
                        {
                            label: 'Users',
                            data: data.map(d => d.users),
                            backgroundColor: isDark ? 'rgba(124,58,237,0.6)' : 'rgba(124,58,237,0.7)',
                            borderRadius: 6,
                            barPercentage: 0.6,
                        },
                        {
                            label: 'Memorials',
                            data: data.map(d => d.memorials),
                            backgroundColor: isDark ? 'rgba(70,95,255,0.6)' : 'rgba(70,95,255,0.7)',
                            borderRadius: 6,
                            barPercentage: 0.6,
                        }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: { legend: { position: 'top', labels: { color: textColor, boxWidth: 12, padding: 16, font: { size: 12 } } } },
                    scales: {
                        x: { grid: { display: false }, ticks: { color: textColor, font: { size: 11 } } },
                        y: { beginAtZero: true, grid: { color: gridColor }, ticks: { color: textColor, font: { size: 11 }, stepSize: 1 } }
                    }
                }
            });
        }
    };
}
@endif

@if (isset($userStats) && $userStats)
function visitsChart() {
    return {
        init() {
            const data = @json($userStats['weeklyVisits']);
            const isDark = document.documentElement.classList.contains('dark');
            const gridColor = isDark ? 'rgba(255,255,255,0.06)' : 'rgba(0,0,0,0.06)';
            const textColor = isDark ? 'rgba(255,255,255,0.5)' : 'rgba(0,0,0,0.4)';

            new Chart(this.$refs.canvas, {
                type: 'line',
                data: {
                    labels: data.map(d => d.label),
                    datasets: [{
                        label: 'Unique Visitors',
                        data: data.map(d => d.count),
                        borderColor: '#465fff',
                        backgroundColor: isDark ? 'rgba(70,95,255,0.15)' : 'rgba(70,95,255,0.1)',
                        fill: true,
                        tension: 0.4,
                        pointRadius: 4,
                        pointBackgroundColor: '#465fff',
                        pointBorderColor: isDark ? '#1f2937' : '#ffffff',
                        pointBorderWidth: 2,
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { display: false },
                        tooltip: { callbacks: { title: (items) => data[items[0].dataIndex]?.date || '' } }
                    },
                    scales: {
                        x: { grid: { display: false }, ticks: { color: textColor, font: { size: 11 } } },
                        y: { beginAtZero: true, grid: { color: gridColor }, ticks: { color: textColor, font: { size: 11 }, stepSize: 1 } }
                    }
                }
            });
        }
    };
}
@endif
</script>
@endpush
