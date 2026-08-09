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
            @if ($resellers->isNotEmpty())
                {{-- Defaults to the platform's own plans; resellers manage theirs themselves,
                     so those are viewable here but not editable. --}}
                <div class="mb-4 flex justify-end">
                    <x-admin.reseller-filter :resellers="$resellers" default="direct" />
                </div>
            @endif

            @if ($plans->isEmpty())
                <p class="text-sm text-gray-500 dark:text-gray-400">
                    {{ request('reseller') && request('reseller') !== 'direct' ? 'This reseller has no plans yet.' : 'No plans created yet.' }}
                </p>
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
                                        @if ($plan->reseller)
                                            <div class="mt-1.5"><x-admin.owner-tag :reseller="$plan->reseller" /></div>
                                        @endif
                                    </div>
                                    <div class="flex flex-wrap items-center justify-end gap-1.5">
                                        @if ($plan->is_popular)
                                            <span class="inline-flex items-center rounded-full bg-brand-100 px-2.5 py-0.5 text-xs font-medium text-brand-800 dark:bg-brand-900/30 dark:text-brand-400">
                                                Most popular
                                            </span>
                                        @endif
                                        <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium {{ $plan->is_active ? 'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-400' : 'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-300' }}">
                                            {{ $plan->is_active ? 'Active' : 'Inactive' }}
                                        </span>
                                    </div>
                                </div>

                                <div class="mb-4">
                                    <span class="text-2xl font-bold text-gray-800 dark:text-white/90">{{ $currency ?? 'USD' }} {{ \App\Helpers\PriceHelper::format($plan->price) }}</span>
                                    <span class="text-sm text-gray-500 dark:text-gray-400">/ {{ $plan->interval }}</span>
                                </div>

                                <p class="mb-4 text-sm text-gray-600 dark:text-gray-400">{{ $plan->description ?? 'No description' }}</p>

                                @include("pages.settings.partials.plan-entitlement-summary", ["plan" => $plan])

                                <div class="flex gap-2">
                                    <button @click="editing = true"
                                        class="btn btn-secondary btn-sm flex-1">
                                        Edit
                                    </button>
                                    @if (!$plan->subscriptions()->exists())
                                        <form action="{{ route('settings.plans.destroy', $plan) }}" method="POST"
                                            onsubmit="return confirm('Delete this plan?')">
                                            @csrf @method('DELETE')
                                            <button type="submit"
                                                class="btn btn-danger-soft btn-sm">
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
                                        <label class="mb-1 block text-xs font-medium text-gray-600 dark:text-gray-400">Sort Order</label>
                                        <input type="number" name="sort_order" value="{{ $plan->sort_order }}" min="0"
                                            class="h-9 w-full rounded-lg border border-gray-300 dark:border-gray-700 bg-transparent px-3 text-sm text-gray-800 dark:text-white/90 focus:border-brand-300 focus:outline-hidden" />
                                    </div>
                                    <div class="flex flex-col justify-end gap-1.5 pb-1">
                                        <label class="flex items-center gap-2 text-sm text-gray-700 dark:text-gray-300">
                                            <input type="hidden" name="is_active" value="0">
                                            <input type="checkbox" name="is_active" value="1" {{ $plan->is_active ? 'checked' : '' }}
                                                class="rounded border-gray-300 dark:border-gray-700 text-brand-500 focus:ring-brand-500" />
                                            Active
                                        </label>
                                        <label class="flex items-center gap-2 text-sm text-gray-700 dark:text-gray-300">
                                            <input type="hidden" name="is_popular" value="0">
                                            <input type="checkbox" name="is_popular" value="1" {{ $plan->is_popular ? 'checked' : '' }}
                                                class="rounded border-gray-300 dark:border-gray-700 text-brand-500 focus:ring-brand-500" />
                                            Most popular
                                        </label>
                                    </div>
                                </div>
                                <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Most popular badges this plan on the pricing page and preselects it at signup. Only one plan can hold it — setting it here clears it elsewhere.</p>
                                @include("pages.settings.partials.plan-entitlement-fields", ["plan" => $plan, "idPrefix" => "plan-edit-".$plan->id])
                                <div class="flex gap-2 mt-4">
                                    <button type="submit"
                                        class="btn btn-primary btn-sm flex-1">
                                        Save
                                    </button>
                                    <button type="button" @click="editing = false"
                                        class="btn btn-secondary btn-sm">
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
                        <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">Sort Order</label>
                        <input type="number" name="sort_order" value="{{ old('sort_order', '0') }}" min="0"
                            class="h-11 w-full rounded-lg border border-gray-300 dark:border-gray-700 bg-transparent px-4 py-2.5 text-sm text-gray-800 dark:text-white/90 focus:border-brand-300 focus:ring-3 focus:ring-brand-500/10 focus:outline-hidden" />
                    </div>
                    <div class="flex flex-col justify-end gap-1.5">
                        <label class="flex items-center gap-2 text-sm text-gray-700 dark:text-gray-300">
                            <input type="hidden" name="is_active" value="0">
                            <input type="checkbox" name="is_active" value="1" checked
                                class="rounded border-gray-300 dark:border-gray-700 text-brand-500 focus:ring-brand-500" />
                            Active
                        </label>
                        <label class="flex items-center gap-2 text-sm text-gray-700 dark:text-gray-300">
                            <input type="hidden" name="is_popular" value="0">
                            <input type="checkbox" name="is_popular" value="1" {{ old('is_popular') ? 'checked' : '' }}
                                class="rounded border-gray-300 dark:border-gray-700 text-brand-500 focus:ring-brand-500" />
                            Most popular
                        </label>
                    </div>
                </div>
                @include("pages.settings.partials.plan-entitlement-fields", ["plan" => null, "idPrefix" => "plan-create"])
                <div class="mt-6 flex justify-end">
                    <button type="submit"
                        class="btn btn-primary btn-md">
                        Create Plan
                    </button>
                </div>
            </form>
        </x-common.component-card>
    </div>
@endsection
