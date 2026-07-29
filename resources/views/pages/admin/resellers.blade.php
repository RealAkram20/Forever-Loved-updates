@extends('layouts.app')

@section('content')
    @php
        $createHasErrors = $errors->hasAny(['name', 'slug', 'owner_name', 'owner_email']);
    @endphp

    <div x-data="{ create: {{ $createHasErrors ? 'true' : 'false' }} }">
        {{-- Page header: title, context, and the one primary action. --}}
        <div class="mb-6 flex flex-wrap items-end justify-between gap-4">
            <div>
                <h2 class="text-xl font-semibold text-gray-800 dark:text-white/90">Resellers</h2>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                    Businesses selling memorial hosting under their own brand on
                    <span class="font-medium text-gray-700 dark:text-gray-300">{{ config('reseller.domain') }}</span>.
                </p>
            </div>
            <button type="button" @click="create = true" class="btn btn-primary btn-md">
                <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M12 5v14M5 12h14"/></svg>
                New Reseller
            </button>
        </div>

        @if (session('success'))
            <div class="mb-4 rounded-lg bg-green-50 dark:bg-green-900/20 px-4 py-3 text-sm text-green-700 dark:text-green-400">{{ session('success') }}</div>
        @endif
        @if (session('error'))
            <div class="mb-4 rounded-lg bg-red-50 dark:bg-red-900/20 px-4 py-3 text-sm text-red-700 dark:text-red-400">{{ session('error') }}</div>
        @endif

        {{-- Program at a glance. Four numbers, no decoration — this is a reference strip,
             not a dashboard, so it stays quiet enough to scan past. --}}
        <div class="mb-6 grid grid-cols-2 gap-4 lg:grid-cols-5">
            @foreach ([
                ['label' => 'Resellers', 'value' => $stats['total']],
                ['label' => 'Active', 'value' => $stats['active']],
                ['label' => 'Suspended', 'value' => $stats['suspended']],
                ['label' => 'Payment overdue', 'value' => $stats['overdue'], 'alert' => $stats['overdue'] > 0],
                ['label' => 'Memorials hosted', 'value' => $stats['memorials']],
            ] as $stat)
                <div class="rounded-2xl border border-gray-200 dark:border-gray-800 bg-white dark:bg-white/[0.03] px-5 py-4">
                    <p class="text-xs font-medium uppercase tracking-wide text-gray-400 dark:text-gray-500">{{ $stat['label'] }}</p>
                    <p class="mt-1.5 text-2xl font-semibold tabular-nums {{ !empty($stat['alert']) ? 'text-red-600 dark:text-red-400' : 'text-gray-800 dark:text-white/90' }}">{{ number_format($stat['value']) }}</p>
                </div>
            @endforeach
        </div>

        <div class="rounded-2xl border border-gray-200 dark:border-gray-800 bg-white dark:bg-white/[0.03]">
            {{-- Search + status filter --}}
            <div class="flex flex-wrap items-center justify-between gap-3 px-6 py-4">
                <form action="{{ route('settings.resellers') }}" method="GET" class="relative">
                    @if (request('status')) <input type="hidden" name="status" value="{{ request('status') }}"> @endif
                    <svg class="pointer-events-none absolute left-3.5 top-1/2 h-4 w-4 -translate-y-1/2 text-gray-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/></svg>
                    <input type="search" name="q" value="{{ request('q') }}" placeholder="Search name, subdomain or domain"
                        class="h-10 w-full rounded-lg border border-gray-300 dark:border-gray-700 bg-transparent pl-10 pr-4 text-sm text-gray-800 dark:text-white/90 placeholder:text-gray-400 focus:border-brand-300 focus:ring-3 focus:ring-brand-500/10 focus:outline-hidden sm:w-80" />
                </form>

                <div class="flex flex-wrap items-center gap-2">
                    @foreach ([['', 'All'], ['active', 'Active'], ['suspended', 'Suspended'], ['overdue', 'Overdue']] as [$value, $label])
                        <a href="{{ route('settings.resellers', array_filter(['status' => $value, 'q' => request('q')])) }}"
                            class="rounded-full px-3 py-1 text-xs font-medium transition-colors duration-150 {{ request('status', '') === $value ? 'bg-brand-500 text-white' : 'bg-gray-100 text-gray-700 hover:bg-gray-200 dark:bg-gray-800 dark:text-gray-300 dark:hover:bg-gray-700' }}">
                            {{ $label }}
                        </a>
                    @endforeach
                </div>
            </div>

            @if ($resellers->isEmpty())
                <div class="border-t border-gray-100 dark:border-gray-800 px-6 py-16 text-center">
                    <svg class="mx-auto h-10 w-10 text-gray-300 dark:text-gray-600" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M3.25 8.75h17.5M4.75 8.75V19a1.5 1.5 0 0 0 1.5 1.5h11.5a1.5 1.5 0 0 0 1.5-1.5V8.75M3.25 8.75l1.6-4.05A1.5 1.5 0 0 1 6.25 3.75h11.5a1.5 1.5 0 0 1 1.4.95l1.6 4.05"/></svg>
                    @if (request()->hasAny(['q', 'status']))
                        <p class="mt-3 text-sm text-gray-500 dark:text-gray-400">No resellers match that filter.</p>
                        <a href="{{ route('settings.resellers') }}" class="mt-2 inline-block text-sm text-brand-500 hover:underline">Clear filters</a>
                    @else
                        <p class="mt-3 text-sm font-medium text-gray-700 dark:text-gray-300">No resellers yet</p>
                        <p class="mx-auto mt-1 max-w-sm text-sm text-gray-500 dark:text-gray-400">Add a funeral home or agency and they'll get their own branded subdomain, staff login, and client-facing plans.</p>
                        <button type="button" @click="create = true" class="btn btn-primary btn-md mt-5">Create the first reseller</button>
                    @endif
                </div>
            @else
                <div class="overflow-x-auto border-t border-gray-100 dark:border-gray-800">
                    <table class="w-full min-w-[52rem] text-sm">
                        <thead>
                            <tr class="border-b border-gray-100 dark:border-gray-800">
                                <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wide text-gray-400 dark:text-gray-500">Reseller</th>
                                <th class="px-3 py-3 text-left text-xs font-medium uppercase tracking-wide text-gray-400 dark:text-gray-500">Tier</th>
                                <th class="px-3 py-3 text-right text-xs font-medium uppercase tracking-wide text-gray-400 dark:text-gray-500">Memorials</th>
                                <th class="px-3 py-3 text-right text-xs font-medium uppercase tracking-wide text-gray-400 dark:text-gray-500">Staff</th>
                                <th class="px-3 py-3 text-right text-xs font-medium uppercase tracking-wide text-gray-400 dark:text-gray-500">Plans</th>
                                <th class="px-3 py-3 text-left text-xs font-medium uppercase tracking-wide text-gray-400 dark:text-gray-500">Billing</th>
                                <th class="px-3 py-3 text-left text-xs font-medium uppercase tracking-wide text-gray-400 dark:text-gray-500">Status</th>
                                <th class="px-6 py-3 text-right text-xs font-medium uppercase tracking-wide text-gray-400 dark:text-gray-500"><span class="sr-only">Actions</span></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                            @foreach ($resellers as $reseller)
                                <tr class="transition-colors duration-150 hover:bg-gray-50 dark:hover:bg-white/[0.02]">
                                    <td class="px-6 py-4">
                                        <a href="{{ route('settings.resellers.show', $reseller) }}" class="font-medium text-gray-800 dark:text-white/90 hover:text-brand-500 dark:hover:text-brand-400 transition-colors duration-150">
                                            {{ $reseller->name }}
                                        </a>
                                        <div class="mt-0.5 flex flex-wrap items-center gap-x-2 gap-y-1 text-xs text-gray-500 dark:text-gray-400">
                                            <span class="font-mono">{{ $reseller->slug }}.{{ config('reseller.domain') }}</span>
                                            @if ($reseller->custom_domain)
                                                <span class="text-gray-300 dark:text-gray-600">&middot;</span>
                                                <span class="font-mono">{{ $reseller->custom_domain }}</span>
                                                <x-admin.domain-badge :status="$reseller->custom_domain_status" />
                                            @endif
                                        </div>
                                    </td>
                                    <td class="px-3 py-4 text-gray-700 dark:text-gray-300">
                                        {{ $reseller->tier?->name ?? '—' }}
                                    </td>
                                    <td class="px-3 py-4 text-right tabular-nums text-gray-700 dark:text-gray-300">{{ $reseller->memorials_count }}</td>
                                    <td class="px-3 py-4 text-right tabular-nums text-gray-700 dark:text-gray-300">{{ $reseller->staff_count }}</td>
                                    <td class="px-3 py-4 text-right tabular-nums text-gray-700 dark:text-gray-300">{{ $reseller->plans_count }}</td>
                                    <td class="px-3 py-4">
                                        <x-admin.billing-badge :reseller="$reseller" />
                                    </td>
                                    <td class="px-3 py-4">
                                        <x-admin.status-badge :status="$reseller->status" />
                                    </td>
                                    <td class="px-6 py-4 text-right">
                                        <x-admin.reseller-actions :reseller="$reseller" />
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                @if ($resellers->hasPages())
                    <div class="border-t border-gray-100 dark:border-gray-800 px-6 py-4">{{ $resellers->links() }}</div>
                @endif
            @endif
        </div>

        {{-- Create reseller. A modal rather than a third card stacked below the roster:
             creating is occasional, so it shouldn't cost permanent vertical space. --}}
        <div x-show="create" x-cloak @keydown.escape.window="create = false" class="fixed inset-0 z-99999 flex items-start justify-center overflow-y-auto p-4 sm:p-6">
            <div x-show="create" x-transition:enter="transition-opacity duration-200 ease-out" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100"
                x-transition:leave="transition-opacity duration-150 ease-out" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0"
                @click="create = false" class="fixed inset-0 bg-gray-900/50 backdrop-blur-sm"></div>

            <div x-show="create"
                x-transition:enter="transition duration-200 ease-[cubic-bezier(0.23,1,0.32,1)]"
                x-transition:enter-start="opacity-0 scale-[0.97]" x-transition:enter-end="opacity-100 scale-100"
                x-transition:leave="transition duration-150 ease-out"
                x-transition:leave-start="opacity-100 scale-100" x-transition:leave-end="opacity-0 scale-[0.98]"
                class="relative my-auto w-full max-w-2xl rounded-2xl border border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-900 shadow-xl">

                <div class="flex items-start justify-between gap-4 px-6 py-5">
                    <div>
                        <h3 class="text-base font-medium text-gray-800 dark:text-white/90">New reseller</h3>
                        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Creates the business and an owner account that can sign in immediately.</p>
                    </div>
                    <button type="button" @click="create = false" class="-mr-2 -mt-1 rounded-lg p-2 text-gray-400 transition-colors duration-150 hover:bg-gray-100 hover:text-gray-600 dark:hover:bg-gray-800 dark:hover:text-gray-300">
                        <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M6 18 18 6M6 6l12 12"/></svg>
                    </button>
                </div>

                <form action="{{ route('settings.resellers.store') }}" method="POST"
                    class="grid grid-cols-1 gap-5 border-t border-gray-100 dark:border-gray-800 p-6 sm:grid-cols-2"
                    x-data="{
                        name: @js(old('name', '')),
                        slug: @js(old('slug', '')),
                        slugTouched: {{ old('slug') ? 'true' : 'false' }},
                        suggestSlug() {
                            if (this.slugTouched) return;
                            this.slug = this.name.toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/^-+|-+$/g, '').slice(0, 63);
                        }
                    }">
                    @csrf
                    <div>
                        <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">Business name</label>
                        <input type="text" name="name" x-model="name" @input="suggestSlug()" required placeholder="Acme Funeral Home"
                            class="h-11 w-full rounded-lg border border-gray-300 dark:border-gray-700 bg-transparent px-4 py-2.5 text-sm text-gray-800 dark:text-white/90 placeholder:text-gray-400 focus:border-brand-300 focus:ring-3 focus:ring-brand-500/10 focus:outline-hidden" />
                        @error('name') <p class="mt-1 text-sm text-red-500">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">Subdomain</label>
                        <div class="flex items-center">
                            <input type="text" name="slug" x-model="slug" @input="slugTouched = true" required placeholder="acme"
                                class="h-11 w-full rounded-l-lg border border-gray-300 dark:border-gray-700 bg-transparent px-4 py-2.5 font-mono text-sm text-gray-800 dark:text-white/90 placeholder:text-gray-400 focus:border-brand-300 focus:ring-3 focus:ring-brand-500/10 focus:outline-hidden" />
                            <span class="flex h-11 items-center whitespace-nowrap rounded-r-lg border border-l-0 border-gray-300 dark:border-gray-700 bg-gray-50 dark:bg-gray-800 px-3 font-mono text-sm text-gray-500 dark:text-gray-400">.{{ config('reseller.domain') }}</span>
                        </div>
                        @error('slug') <p class="mt-1 text-sm text-red-500">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">Owner name</label>
                        <input type="text" name="owner_name" value="{{ old('owner_name') }}" required
                            class="h-11 w-full rounded-lg border border-gray-300 dark:border-gray-700 bg-transparent px-4 py-2.5 text-sm text-gray-800 dark:text-white/90 focus:border-brand-300 focus:ring-3 focus:ring-brand-500/10 focus:outline-hidden" />
                        @error('owner_name') <p class="mt-1 text-sm text-red-500">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">Owner email</label>
                        <input type="email" name="owner_email" value="{{ old('owner_email') }}" required
                            class="h-11 w-full rounded-lg border border-gray-300 dark:border-gray-700 bg-transparent px-4 py-2.5 text-sm text-gray-800 dark:text-white/90 focus:border-brand-300 focus:ring-3 focus:ring-brand-500/10 focus:outline-hidden" />
                        @error('owner_email') <p class="mt-1 text-sm text-red-500">{{ $message }}</p> @enderror
                    </div>
                    <div class="sm:col-span-2">
                        <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">Tier</label>
                        <select name="reseller_tier_id"
                            class="h-11 w-full rounded-lg border border-gray-300 dark:border-gray-700 bg-transparent px-4 py-2.5 text-sm text-gray-800 dark:text-white/90 focus:border-brand-300 focus:ring-3 focus:ring-brand-500/10 focus:outline-hidden">
                            <option value="">{{ $defaultTierId ? 'Use program default' : 'No tier yet' }}</option>
                            @foreach ($tiers as $tier)
                                <option value="{{ $tier->id }}" {{ old('reseller_tier_id') == $tier->id ? 'selected' : '' }}>
                                    {{ $tier->name }} — {{ \App\Helpers\PriceHelper::format($tier->annual_price) }}/yr
                                </option>
                            @endforeach
                        </select>
                        @if ($tiers->isEmpty())
                            <p class="mt-1.5 text-xs text-gray-500 dark:text-gray-400">
                                No tiers exist yet. <a href="{{ route('settings.reseller-pricing') }}" class="text-brand-500 hover:underline">Set up pricing</a> to assign one.
                            </p>
                        @endif
                    </div>
                    <div class="flex justify-end gap-3 sm:col-span-2">
                        <button type="button" @click="create = false" class="btn btn-secondary btn-md">Cancel</button>
                        <button type="submit" class="btn btn-primary btn-md">Create reseller</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endsection
