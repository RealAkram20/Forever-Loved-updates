@extends('layouts.app')

@section('content')
    <x-common.page-breadcrumb pageTitle="Subscriptions" />

    @if (session('success'))
        <div class="mb-4 rounded-lg bg-green-50 dark:bg-green-900/20 px-4 py-3 text-sm text-green-700 dark:text-green-400">
            {{ session('success') }}
        </div>
    @endif

    <x-common.component-card title="User Subscriptions" desc="View and manage all user subscriptions.">
        @if ($subscriptions->isEmpty())
            <div class="py-12 text-center">
                <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"/>
                </svg>
                <p class="mt-3 text-sm text-gray-500 dark:text-gray-400">No subscriptions found.</p>
                <p class="text-sm text-gray-400 dark:text-gray-500">Subscriptions will appear here once users upgrade their plans.</p>
            </div>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-gray-200 dark:border-gray-700">
                            <th class="pb-3 text-left font-medium text-gray-500 dark:text-gray-400">User</th>
                            <th class="pb-3 text-left font-medium text-gray-500 dark:text-gray-400">Plan</th>
                            <th class="pb-3 text-left font-medium text-gray-500 dark:text-gray-400">Status</th>
                            <th class="pb-3 text-left font-medium text-gray-500 dark:text-gray-400">Started</th>
                            <th class="pb-3 text-left font-medium text-gray-500 dark:text-gray-400">Expires</th>
                            <th class="pb-3 text-left font-medium text-gray-500 dark:text-gray-400">Gateway</th>
                            <th class="pb-3 text-left font-medium text-gray-500 dark:text-gray-400">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                        @foreach ($subscriptions as $sub)
                            <tr>
                                <td class="py-3">
                                    <div class="text-gray-800 dark:text-white/90">{{ $sub->user->name ?? 'Deleted User' }}</div>
                                    <div class="text-xs text-gray-500">{{ $sub->user->email ?? '' }}</div>
                                </td>
                                <td class="py-3 text-gray-700 dark:text-gray-300">{{ $sub->plan->name ?? 'N/A' }}</td>
                                <td class="py-3">
                                    <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium
                                        {{ $sub->status === 'active' ? 'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-400' : '' }}
                                        {{ $sub->status === 'cancelled' ? 'bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-400' : '' }}
                                        {{ $sub->status === 'expired' ? 'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-300' : '' }}
                                        {{ $sub->status === 'paused' ? 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900/30 dark:text-yellow-400' : '' }}">
                                        {{ ucfirst($sub->status) }}
                                    </span>
                                </td>
                                <td class="py-3 text-gray-500 dark:text-gray-400">{{ $sub->starts_at?->format('M d, Y') ?? '-' }}</td>
                                <td class="py-3 text-gray-500 dark:text-gray-400">{{ $sub->ends_at?->format('M d, Y') ?? 'Never' }}</td>
                                <td class="py-3 text-gray-500 dark:text-gray-400">{{ $sub->payment_gateway ?? '-' }}</td>
                                <td class="py-3">
                                    <form action="{{ route('settings.subscriptions.update', $sub) }}" method="POST" class="flex items-center gap-2">
                                        @csrf @method('PUT')
                                        <select name="status"
                                            class="h-8 rounded-md border border-gray-300 dark:border-gray-700 bg-transparent px-2 py-1 text-xs text-gray-800 dark:text-white/90 focus:border-brand-300 focus:outline-hidden">
                                            @foreach (['active', 'cancelled', 'expired', 'paused'] as $status)
                                                <option value="{{ $status }}" {{ $sub->status === $status ? 'selected' : '' }}>{{ ucfirst($status) }}</option>
                                            @endforeach
                                        </select>
                                        <button type="submit"
                                            class="h-8 rounded-md bg-gray-100 dark:bg-gray-800 px-2.5 text-xs font-medium text-gray-700 dark:text-gray-300 hover:bg-gray-200 dark:hover:bg-gray-700 transition">
                                            Save
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div class="mt-4">
                {{ $subscriptions->links() }}
            </div>
        @endif
    </x-common.component-card>
@endsection
