@extends('layouts.app')

@section('content')
    <x-common.page-breadcrumb pageTitle="Subscription Plans" />

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
        {{-- Existing Plans --}}
        <x-common.component-card title="Plans" desc="Manage subscription plans available to users.">
            @if ($plans->isEmpty())
                <p class="text-sm text-gray-500 dark:text-gray-400">No plans created yet.</p>
            @else
                <div class="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-3">
                    @foreach ($plans as $plan)
                        <div class="rounded-xl border border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800/50 p-5"
                            x-data="{ editing: false }">
                            {{-- View Mode --}}
                            <div x-show="!editing">
                                <div class="flex items-start justify-between mb-3">
                                    <div>
                                        <h4 class="text-lg font-semibold text-gray-800 dark:text-white/90">{{ $plan->name }}</h4>
                                        <p class="text-xs text-gray-500 dark:text-gray-400">{{ $plan->slug }}</p>
                                    </div>
                                    <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium {{ $plan->is_active ? 'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-400' : 'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-300' }}">
                                        {{ $plan->is_active ? 'Active' : 'Inactive' }}
                                    </span>
                                </div>

                                <div class="mb-4">
                                    <span class="text-2xl font-bold text-gray-800 dark:text-white/90">${{ number_format($plan->price, 2) }}</span>
                                    <span class="text-sm text-gray-500 dark:text-gray-400">/ {{ $plan->interval }}</span>
                                </div>

                                <p class="mb-4 text-sm text-gray-600 dark:text-gray-400">{{ $plan->description ?? 'No description' }}</p>

                                <div class="space-y-1.5 text-sm text-gray-600 dark:text-gray-400 mb-4">
                                    <div class="flex items-center gap-2">
                                        <svg class="w-4 h-4 text-green-500" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/></svg>
                                        {{ $plan->memorial_limit }} memorial{{ $plan->memorial_limit > 1 ? 's' : '' }}
                                    </div>
                                    <div class="flex items-center gap-2">
                                        <svg class="w-4 h-4 text-green-500" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/></svg>
                                        {{ $plan->storage_limit_mb }} MB storage
                                    </div>
                                </div>

                                <div class="flex gap-2">
                                    <button @click="editing = true"
                                        class="flex-1 h-9 rounded-lg bg-gray-100 dark:bg-gray-700 text-sm font-medium text-gray-700 dark:text-gray-300 hover:bg-gray-200 dark:hover:bg-gray-600 transition">
                                        Edit
                                    </button>
                                    @if (!$plan->subscriptions()->exists())
                                        <form action="{{ route('settings.plans.destroy', $plan) }}" method="POST"
                                            onsubmit="return confirm('Delete this plan?')">
                                            @csrf @method('DELETE')
                                            <button type="submit"
                                                class="h-9 rounded-lg bg-red-50 dark:bg-red-900/20 px-4 text-sm font-medium text-red-600 dark:text-red-400 hover:bg-red-100 dark:hover:bg-red-900/40 transition">
                                                Delete
                                            </button>
                                        </form>
                                    @endif
                                </div>
                            </div>

                            {{-- Edit Mode --}}
                            <form x-show="editing" x-cloak action="{{ route('settings.plans.update', $plan) }}" method="POST" class="space-y-4">
                                @csrf @method('PUT')
                                <div>
                                    <label class="mb-1 block text-xs font-medium text-gray-600 dark:text-gray-400">Name</label>
                                    <input type="text" name="name" value="{{ $plan->name }}"
                                        class="h-9 w-full rounded-lg border border-gray-300 dark:border-gray-700 bg-transparent px-3 text-sm text-gray-800 dark:text-white/90 focus:border-brand-300 focus:outline-hidden" />
                                </div>
                                <div>
                                    <label class="mb-1 block text-xs font-medium text-gray-600 dark:text-gray-400">Description</label>
                                    <textarea name="description" rows="2"
                                        class="w-full rounded-lg border border-gray-300 dark:border-gray-700 bg-transparent px-3 py-2 text-sm text-gray-800 dark:text-white/90 focus:border-brand-300 focus:outline-hidden">{{ $plan->description }}</textarea>
                                </div>
                                <div class="grid grid-cols-2 gap-3">
                                    <div>
                                        <label class="mb-1 block text-xs font-medium text-gray-600 dark:text-gray-400">Price</label>
                                        <input type="number" name="price" value="{{ $plan->price }}" step="0.01" min="0"
                                            class="h-9 w-full rounded-lg border border-gray-300 dark:border-gray-700 bg-transparent px-3 text-sm text-gray-800 dark:text-white/90 focus:border-brand-300 focus:outline-hidden" />
                                    </div>
                                    <div>
                                        <label class="mb-1 block text-xs font-medium text-gray-600 dark:text-gray-400">Interval</label>
                                        <select name="interval"
                                            class="h-9 w-full rounded-lg border border-gray-300 dark:border-gray-700 bg-transparent px-3 text-sm text-gray-800 dark:text-white/90 focus:border-brand-300 focus:outline-hidden">
                                            @foreach (['monthly', 'yearly', 'lifetime'] as $interval)
                                                <option value="{{ $interval }}" {{ $plan->interval === $interval ? 'selected' : '' }}>{{ ucfirst($interval) }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div>
                                        <label class="mb-1 block text-xs font-medium text-gray-600 dark:text-gray-400">Memorial Limit</label>
                                        <input type="number" name="memorial_limit" value="{{ $plan->memorial_limit }}" min="1"
                                            class="h-9 w-full rounded-lg border border-gray-300 dark:border-gray-700 bg-transparent px-3 text-sm text-gray-800 dark:text-white/90 focus:border-brand-300 focus:outline-hidden" />
                                    </div>
                                    <div>
                                        <label class="mb-1 block text-xs font-medium text-gray-600 dark:text-gray-400">Storage (MB)</label>
                                        <input type="number" name="storage_limit_mb" value="{{ $plan->storage_limit_mb }}" min="10"
                                            class="h-9 w-full rounded-lg border border-gray-300 dark:border-gray-700 bg-transparent px-3 text-sm text-gray-800 dark:text-white/90 focus:border-brand-300 focus:outline-hidden" />
                                    </div>
                                    <div>
                                        <label class="mb-1 block text-xs font-medium text-gray-600 dark:text-gray-400">Sort Order</label>
                                        <input type="number" name="sort_order" value="{{ $plan->sort_order }}" min="0"
                                            class="h-9 w-full rounded-lg border border-gray-300 dark:border-gray-700 bg-transparent px-3 text-sm text-gray-800 dark:text-white/90 focus:border-brand-300 focus:outline-hidden" />
                                    </div>
                                    <div class="flex items-end pb-1">
                                        <label class="flex items-center gap-2 text-sm text-gray-700 dark:text-gray-300">
                                            <input type="hidden" name="is_active" value="0">
                                            <input type="checkbox" name="is_active" value="1" {{ $plan->is_active ? 'checked' : '' }}
                                                class="rounded border-gray-300 dark:border-gray-700 text-brand-500 focus:ring-brand-500" />
                                            Active
                                        </label>
                                    </div>
                                </div>
                                <div class="flex gap-2">
                                    <button type="submit"
                                        class="flex-1 h-9 rounded-lg bg-brand-500 text-sm font-medium text-white hover:bg-brand-600 transition">
                                        Save
                                    </button>
                                    <button type="button" @click="editing = false"
                                        class="h-9 rounded-lg bg-gray-100 dark:bg-gray-700 px-4 text-sm font-medium text-gray-700 dark:text-gray-300 hover:bg-gray-200 dark:hover:bg-gray-600 transition">
                                        Cancel
                                    </button>
                                </div>
                            </form>
                        </div>
                    @endforeach
                </div>
            @endif
        </x-common.component-card>

        {{-- Create New Plan --}}
        <x-common.component-card title="Create New Plan" desc="Add a new subscription plan.">
            <form action="{{ route('settings.plans.store') }}" method="POST">
                @csrf
                <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
                    <div>
                        <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">Plan Name</label>
                        <input type="text" name="name" value="{{ old('name') }}" placeholder="e.g. Pro, Business"
                            class="h-11 w-full rounded-lg border border-gray-300 dark:border-gray-700 bg-transparent px-4 py-2.5 text-sm text-gray-800 dark:text-white/90 placeholder:text-gray-400 focus:border-brand-300 focus:ring-3 focus:ring-brand-500/10 focus:outline-hidden" />
                        @error('name') <p class="mt-1 text-sm text-red-500">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">Slug</label>
                        <input type="text" name="slug" value="{{ old('slug') }}" placeholder="e.g. pro, business"
                            class="h-11 w-full rounded-lg border border-gray-300 dark:border-gray-700 bg-transparent px-4 py-2.5 text-sm text-gray-800 dark:text-white/90 placeholder:text-gray-400 focus:border-brand-300 focus:ring-3 focus:ring-brand-500/10 focus:outline-hidden" />
                        @error('slug') <p class="mt-1 text-sm text-red-500">{{ $message }}</p> @enderror
                    </div>
                    <div class="lg:col-span-2">
                        <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">Description</label>
                        <textarea name="description" rows="2" placeholder="Brief description of the plan"
                            class="w-full rounded-lg border border-gray-300 dark:border-gray-700 bg-transparent px-4 py-2.5 text-sm text-gray-800 dark:text-white/90 placeholder:text-gray-400 focus:border-brand-300 focus:ring-3 focus:ring-brand-500/10 focus:outline-hidden">{{ old('description') }}</textarea>
                    </div>
                    <div>
                        <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">Price</label>
                        <input type="number" name="price" value="{{ old('price', '0') }}" step="0.01" min="0"
                            class="h-11 w-full rounded-lg border border-gray-300 dark:border-gray-700 bg-transparent px-4 py-2.5 text-sm text-gray-800 dark:text-white/90 focus:border-brand-300 focus:ring-3 focus:ring-brand-500/10 focus:outline-hidden" />
                        @error('price') <p class="mt-1 text-sm text-red-500">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">Billing Interval</label>
                        <select name="interval"
                            class="h-11 w-full rounded-lg border border-gray-300 dark:border-gray-700 bg-transparent px-4 py-2.5 text-sm text-gray-800 dark:text-white/90 focus:border-brand-300 focus:ring-3 focus:ring-brand-500/10 focus:outline-hidden">
                            <option value="monthly" {{ old('interval') === 'monthly' ? 'selected' : '' }}>Monthly</option>
                            <option value="yearly" {{ old('interval') === 'yearly' ? 'selected' : '' }}>Yearly</option>
                            <option value="lifetime" {{ old('interval') === 'lifetime' ? 'selected' : '' }}>Lifetime</option>
                        </select>
                    </div>
                    <div>
                        <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">Memorial Limit</label>
                        <input type="number" name="memorial_limit" value="{{ old('memorial_limit', '1') }}" min="1"
                            class="h-11 w-full rounded-lg border border-gray-300 dark:border-gray-700 bg-transparent px-4 py-2.5 text-sm text-gray-800 dark:text-white/90 focus:border-brand-300 focus:ring-3 focus:ring-brand-500/10 focus:outline-hidden" />
                    </div>
                    <div>
                        <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">Storage Limit (MB)</label>
                        <input type="number" name="storage_limit_mb" value="{{ old('storage_limit_mb', '100') }}" min="10"
                            class="h-11 w-full rounded-lg border border-gray-300 dark:border-gray-700 bg-transparent px-4 py-2.5 text-sm text-gray-800 dark:text-white/90 focus:border-brand-300 focus:ring-3 focus:ring-brand-500/10 focus:outline-hidden" />
                    </div>
                    <div>
                        <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">Sort Order</label>
                        <input type="number" name="sort_order" value="{{ old('sort_order', '0') }}" min="0"
                            class="h-11 w-full rounded-lg border border-gray-300 dark:border-gray-700 bg-transparent px-4 py-2.5 text-sm text-gray-800 dark:text-white/90 focus:border-brand-300 focus:ring-3 focus:ring-brand-500/10 focus:outline-hidden" />
                    </div>
                    <div class="flex items-end">
                        <label class="flex items-center gap-2 text-sm text-gray-700 dark:text-gray-300">
                            <input type="hidden" name="is_active" value="0">
                            <input type="checkbox" name="is_active" value="1" checked
                                class="rounded border-gray-300 dark:border-gray-700 text-brand-500 focus:ring-brand-500" />
                            Active
                        </label>
                    </div>
                </div>
                <div class="mt-6 flex justify-end">
                    <button type="submit"
                        class="inline-flex items-center gap-2 rounded-lg bg-brand-500 px-5 py-2.5 text-sm font-medium text-white shadow-theme-xs hover:bg-brand-600 transition">
                        Create Plan
                    </button>
                </div>
            </form>
        </x-common.component-card>
    </div>
@endsection
