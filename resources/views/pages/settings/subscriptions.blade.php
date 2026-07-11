@extends('layouts.app')

@section('content')
    <x-common.page-breadcrumb pageTitle="Subscriptions" />

    @if (session('success'))
        <div class="mb-4 rounded-lg bg-green-50 dark:bg-green-900/20 px-4 py-3 text-sm text-green-700 dark:text-green-400">
            {{ session('success') }}
        </div>
    @endif
    @if (session('error'))
        <div class="mb-4 rounded-lg bg-red-50 dark:bg-red-900/20 px-4 py-3 text-sm text-red-700 dark:text-red-400">
            {{ session('error') }}
        </div>
    @endif

    <div class="space-y-6">
        {{-- Status filter --}}
        <x-common.component-card title="User Subscriptions" desc="View and manage all user subscriptions. Create payment orders in Payment Orders to grant access.">
            <div class="mb-4 flex flex-wrap items-center gap-2">
                <span class="text-sm font-medium text-gray-600 dark:text-gray-400">Filter by status:</span>
                <a href="{{ route('settings.subscriptions') }}"
                    class="rounded-full px-3 py-1 text-xs font-medium transition {{ !request('status') ? 'bg-brand-500 text-white' : 'bg-gray-100 text-gray-700 dark:bg-gray-700 dark:text-gray-300 hover:bg-gray-200 dark:hover:bg-gray-600' }}">
                    All
                </a>
                @foreach (['active', 'pending', 'cancelled', 'expired', 'paused'] as $s)
                    <a href="{{ route('settings.subscriptions', ['status' => $s]) }}"
                        class="rounded-full px-3 py-1 text-xs font-medium transition {{ request('status') === $s ? 'bg-brand-500 text-white' : 'bg-gray-100 text-gray-700 dark:bg-gray-700 dark:text-gray-300 hover:bg-gray-200 dark:hover:bg-gray-600' }}">
                        {{ ucfirst($s) }}
                    </a>
                @endforeach
            </div>

            @if ($subscriptions->isEmpty())
                <div class="py-12 text-center">
                    <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"/>
                    </svg>
                    <p class="mt-3 text-sm text-gray-500 dark:text-gray-400">No subscriptions found.</p>
                    @if (request('status'))
                        <a href="{{ route('settings.subscriptions') }}" class="mt-2 inline-block text-sm text-brand-500 hover:underline">Clear filter</a>
                    @endif
                </div>
            @else
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="border-b border-gray-200 dark:border-gray-700">
                                <th class="pb-3 text-left font-medium text-gray-500 dark:text-gray-400">User</th>
                                <th class="pb-3 text-left font-medium text-gray-500 dark:text-gray-400">Memorial</th>
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
                                <tr x-data="{ editing: false }">
                                    {{-- View mode --}}
                                    <td class="py-3" x-show="!editing">
                                        <div class="text-gray-800 dark:text-white/90">{{ $sub->user->name ?? 'Deleted User' }}</div>
                                        <div class="text-xs text-gray-500">{{ $sub->user->email ?? '' }}</div>
                                    </td>
                                    <td class="py-3" x-show="!editing">
                                        @if ($sub->memorial)
                                            <a href="{{ route('memorials.show', $sub->memorial) }}" class="text-brand-500 hover:underline">{{ $sub->memorial->full_name }}</a>
                                        @else
                                            <span class="text-amber-600 dark:text-amber-400">-</span>
                                        @endif
                                    </td>
                                    <td class="py-3 text-gray-700 dark:text-gray-300" x-show="!editing">{{ $sub->plan->name ?? 'N/A' }}</td>
                                    <td class="py-3" x-show="!editing">
                                        <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium
                                        {{ $sub->status === 'active' ? 'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-400' : '' }}
                                        {{ $sub->status === 'pending' ? 'bg-amber-100 text-amber-800 dark:bg-amber-900/30 dark:text-amber-400' : '' }}
                                        {{ $sub->status === 'cancelled' ? 'bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-400' : '' }}
                                        {{ $sub->status === 'expired' ? 'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-300' : '' }}
                                        {{ $sub->status === 'paused' ? 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900/30 dark:text-yellow-400' : '' }}">
                                            {{ ucfirst($sub->status) }}
                                        </span>
                                    </td>
                                    <td class="py-3 text-gray-500 dark:text-gray-400" x-show="!editing">{{ $sub->starts_at?->format('M d, Y') ?? '-' }}</td>
                                    <td class="py-3 text-gray-500 dark:text-gray-400" x-show="!editing">{{ $sub->ends_at?->format('M d, Y') ?? 'Never' }}</td>
                                    <td class="py-3 text-gray-500 dark:text-gray-400" x-show="!editing">{{ $sub->payment_gateway ?? '-' }}</td>
                                    <td class="py-3" x-show="!editing">
                                        <button type="button" @click="editing = true"
                                            class="btn btn-secondary btn-sm">
                                            Edit
                                        </button>
                                    </td>
                                    {{-- Edit mode (spans full row) --}}
                                    <td colspan="8" x-show="editing" x-cloak class="bg-gray-50 dark:bg-gray-800/50 p-4">
                                        <form action="{{ route('settings.subscriptions.update', $sub) }}" method="POST" class="space-y-3">
                                            @csrf @method('PUT')
                                            <div class="grid grid-cols-2 gap-4 md:grid-cols-4">
                                                <div>
                                                    <label class="mb-1 block text-xs font-medium text-gray-600 dark:text-gray-400">User</label>
                                                    <select name="user_id" required
                                                        class="h-9 w-full rounded-lg border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-800 px-3 text-sm text-gray-800 dark:text-white/90 focus:border-brand-300 focus:outline-hidden">
                                                        @foreach ($users as $u)
                                                            <option value="{{ $u->id }}" {{ $sub->user_id == $u->id ? 'selected' : '' }}>{{ $u->name }} ({{ $u->email }})</option>
                                                        @endforeach
                                                    </select>
                                                </div>
                                                <div>
                                                    <label class="mb-1 block text-xs font-medium text-gray-600 dark:text-gray-400">Memorial</label>
                                                    <select name="memorial_id" required
                                                        class="h-9 w-full rounded-lg border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-800 px-3 text-sm text-gray-800 dark:text-white/90 focus:border-brand-300 focus:outline-hidden">
                                                        @foreach ($memorials ?? [] as $m)
                                                            <option value="{{ $m->id }}" {{ $sub->memorial_id == $m->id ? 'selected' : '' }} data-user="{{ $m->user_id }}">{{ $m->full_name }} ({{ $m->owner->name ?? '' }})</option>
                                                        @endforeach
                                                    </select>
                                                </div>
                                                <div>
                                                    <label class="mb-1 block text-xs font-medium text-gray-600 dark:text-gray-400">Plan</label>
                                                    <select name="subscription_plan_id" required
                                                        class="h-9 w-full rounded-lg border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-800 px-3 text-sm text-gray-800 dark:text-white/90 focus:border-brand-300 focus:outline-hidden">
                                                        @foreach ($plans as $p)
                                                            <option value="{{ $p->id }}" {{ $sub->subscription_plan_id == $p->id ? 'selected' : '' }}>{{ $p->name }}</option>
                                                        @endforeach
                                                    </select>
                                                </div>
                                                <div>
                                                    <label class="mb-1 block text-xs font-medium text-gray-600 dark:text-gray-400">Status</label>
                                                    <select name="status" required
                                                        class="h-9 w-full rounded-lg border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-800 px-3 text-sm text-gray-800 dark:text-white/90 focus:border-brand-300 focus:outline-hidden">
                                                        @foreach (['active', 'pending', 'cancelled', 'expired', 'paused'] as $status)
                                                            <option value="{{ $status }}" {{ $sub->status === $status ? 'selected' : '' }}>{{ ucfirst($status) }}</option>
                                                        @endforeach
                                                    </select>
                                                </div>
                                                <div>
                                                    <label class="mb-1 block text-xs font-medium text-gray-600 dark:text-gray-400">Started</label>
                                                    <input type="date" name="starts_at" value="{{ $sub->starts_at?->format('Y-m-d') }}" required
                                                        class="h-9 w-full rounded-lg border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-800 px-3 text-sm text-gray-800 dark:text-white/90 focus:border-brand-300 focus:outline-hidden" />
                                                </div>
                                                <div>
                                                    <label class="mb-1 block text-xs font-medium text-gray-600 dark:text-gray-400">Expires (optional)</label>
                                                    <input type="date" name="ends_at" value="{{ $sub->ends_at?->format('Y-m-d') }}"
                                                        class="h-9 w-full rounded-lg border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-800 px-3 text-sm text-gray-800 dark:text-white/90 focus:border-brand-300 focus:outline-hidden" />
                                                </div>
                                                <div>
                                                    <label class="mb-1 block text-xs font-medium text-gray-600 dark:text-gray-400">Gateway</label>
                                                    <input type="text" name="payment_gateway" value="{{ old('payment_gateway', $sub->payment_gateway) }}" placeholder="e.g. pesapal, manual"
                                                        class="h-9 w-full rounded-lg border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-800 px-3 text-sm text-gray-800 dark:text-white/90 focus:border-brand-300 focus:outline-hidden" />
                                                </div>
                                                <div>
                                                    <label class="mb-1 block text-xs font-medium text-gray-600 dark:text-gray-400">Reference</label>
                                                    <input type="text" name="payment_reference" value="{{ old('payment_reference', $sub->payment_reference) }}" placeholder="e.g. order ID"
                                                        class="h-9 w-full rounded-lg border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-800 px-3 text-sm text-gray-800 dark:text-white/90 focus:border-brand-300 focus:outline-hidden" />
                                                </div>
                                            </div>
                                            <div class="flex gap-2">
                                                <button type="submit"
                                                    class="btn btn-primary btn-sm">
                                                    Save
                                                </button>
                                                <button type="button" @click="editing = false"
                                                    class="btn btn-secondary btn-sm">
                                                    Cancel
                                                </button>
                                            </div>
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
    </div>
@endsection
