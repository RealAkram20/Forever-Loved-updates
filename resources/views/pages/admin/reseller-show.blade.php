@extends('layouts.app')

@section('content')
    {{-- Header --}}
    <div class="mb-6">
        <a href="{{ route('settings.resellers') }}" class="inline-flex items-center gap-1.5 text-sm text-gray-500 transition-colors duration-150 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-300">
            <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M14 6l-6 6 6 6"/></svg>
            All resellers
        </a>

        <div class="mt-3 flex flex-wrap items-start justify-between gap-4">
            <div>
                <div class="flex flex-wrap items-center gap-3">
                    <h2 class="text-xl font-semibold text-gray-800 dark:text-white/90">{{ $reseller->name }}</h2>
                    <x-admin.status-badge :status="$reseller->status" />
                </div>
                <p class="mt-1 font-mono text-sm text-gray-500 dark:text-gray-400">{{ $reseller->publicDisplayAddress() }}</p>
                @if ($reseller->usingFallbackAddress())
                    {{-- Admin needs to know the roster is showing a stand-in, not the live
                         address — this is the page they answer "what's my URL?" from. --}}
                    <p class="mt-1 text-xs text-amber-600 dark:text-amber-500">
                        Temporary — subdomain routing is unavailable in this environment. Live address will be
                        <code class="font-mono">{{ $reseller->publicHost() }}</code>.
                    </p>
                @endif
            </div>

            <div class="flex flex-wrap items-center gap-2">
                <form action="{{ route('settings.resellers.login-as', $reseller) }}" method="POST">
                    @csrf
                    <button type="submit" class="btn btn-secondary btn-md">Log in as owner</button>
                </form>
                @if ($reseller->isActive())
                    <form action="{{ route('settings.resellers.suspend', $reseller) }}" method="POST"
                        onsubmit="return confirm('Suspend {{ addslashes($reseller->name) }}? Their public memorials and subdomain keep working — only their own dashboard access is blocked.')">
                        @csrf
                        <button type="submit" class="btn btn-danger-soft btn-md">Suspend</button>
                    </form>
                @else
                    <form action="{{ route('settings.resellers.activate', $reseller) }}" method="POST">
                        @csrf
                        <button type="submit" class="btn btn-primary btn-md">Reactivate</button>
                    </form>
                @endif
            </div>
        </div>
    </div>

    @if (session('success'))
        <div class="mb-4 rounded-lg bg-green-50 dark:bg-green-900/20 px-4 py-3 text-sm text-green-700 dark:text-green-400">{{ session('success') }}</div>
    @endif
    @if (session('error'))
        <div class="mb-4 rounded-lg bg-red-50 dark:bg-red-900/20 px-4 py-3 text-sm text-red-700 dark:text-red-400">{{ session('error') }}</div>
    @endif

    @unless ($reseller->isActive())
        <div class="mb-6 flex items-start gap-3 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 dark:border-amber-500/25 dark:bg-amber-900/20">
            <svg class="mt-0.5 h-4 w-4 shrink-0 text-amber-600 dark:text-amber-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"><path d="M12 8.5v5M12 17h.01"/><circle cx="12" cy="12" r="9"/></svg>
            <p class="text-sm text-amber-800 dark:text-amber-300">
                This reseller is suspended. Their staff can't sign in, but every public memorial page and their subdomain keep serving as normal.
            </p>
        </div>
    @endunless

    @php $allowance = $reseller->memorialAllowance(); $remaining = $reseller->memorialsRemaining(); @endphp

    @if ($remaining === 0)
        <div class="mb-6 flex items-start gap-3 rounded-xl border border-red-200 bg-red-50 px-4 py-3 dark:border-red-500/25 dark:bg-red-900/20">
            <svg class="mt-0.5 h-4 w-4 shrink-0 text-red-600 dark:text-red-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"><circle cx="12" cy="12" r="9"/><path d="M12 7.75v5M12 16h.01"/></svg>
            <p class="text-sm text-red-800 dark:text-red-300">
                At their tier limit — {{ number_format($allowance) }} of {{ number_format($allowance) }} profiles used. They can't create new memorials until you raise the allowance or move them to a larger tier.
            </p>
        </div>
    @endif

    {{-- Counts --}}
    <div class="mb-6 grid grid-cols-2 gap-4 lg:grid-cols-5">
        @foreach ([
            ['label' => 'Memorials', 'value' => number_format($reseller->memorials_count).($allowance !== null ? ' / '.number_format($allowance) : '')],
            ['label' => 'Staff', 'value' => number_format($reseller->staff_count)],
            ['label' => 'Plans', 'value' => number_format($reseller->plans_count)],
            ['label' => 'Storage', 'value' => \App\Helpers\StorageHelper::formatBytes($reseller->storageUsedBytes()).($reseller->storageLimitBytes() !== null ? ' / '.\App\Helpers\StorageHelper::formatBytes($reseller->storageLimitBytes()) : '')],
            ['label' => 'Collected', 'value' => \App\Helpers\PriceHelper::format($revenue)],
        ] as $stat)
            <div class="rounded-2xl border border-gray-200 dark:border-gray-800 bg-white dark:bg-white/[0.03] px-5 py-4">
                <p class="text-xs font-medium uppercase tracking-wide text-gray-400 dark:text-gray-500">{{ $stat['label'] }}</p>
                <p class="mt-1.5 text-2xl font-semibold tabular-nums text-gray-800 dark:text-white/90">{{ $stat['value'] }}</p>
            </div>
        @endforeach
    </div>

    <div class="grid grid-cols-1 gap-6 xl:grid-cols-3">
        {{-- ─── Left column: what they've actually built ─────────────── --}}
        <div class="space-y-6 xl:col-span-2">
            <x-common.component-card title="Memorials" desc="The 10 most recent memorials hosted under this reseller.">
                @if ($memorials->isEmpty())
                    <p class="py-4 text-sm text-gray-500 dark:text-gray-400">No memorials yet.</p>
                @else
                    <ul class="-my-2 divide-y divide-gray-100 dark:divide-gray-800">
                        @foreach ($memorials as $memorial)
                            <li class="flex items-center justify-between gap-4 py-3">
                                <div class="min-w-0">
                                    <a href="{{ route('memorials.show', $memorial) }}" class="block truncate text-sm font-medium text-gray-800 transition-colors duration-150 hover:text-brand-500 dark:text-white/90 dark:hover:text-brand-400">
                                        {{ $memorial->full_name }}
                                    </a>
                                    <p class="mt-0.5 truncate text-xs text-gray-500 dark:text-gray-400">{{ $memorial->owner->name ?? 'No owner' }}</p>
                                </div>
                                <span class="shrink-0 text-xs text-gray-400 dark:text-gray-500">{{ $memorial->created_at?->format('M j, Y') }}</span>
                            </li>
                        @endforeach
                    </ul>
                    @if ($reseller->memorials_count > $memorials->count())
                        <p class="pt-4 text-xs text-gray-500 dark:text-gray-400">
                            Showing {{ $memorials->count() }} of {{ number_format($reseller->memorials_count) }}.
                        </p>
                    @endif
                @endif
            </x-common.component-card>

            <x-common.component-card title="Their plans" desc="What this reseller sells to their own clients. They control these; you only see them.">
                @if ($plans->isEmpty())
                    <p class="py-4 text-sm text-gray-500 dark:text-gray-400">They haven't published any plans yet.</p>
                @else
                    <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                        @foreach ($plans as $plan)
                            <div class="rounded-xl border border-gray-200 dark:border-gray-800 px-4 py-3">
                                <p class="text-sm font-medium text-gray-800 dark:text-white/90">{{ $plan->name }}</p>
                                <p class="mt-0.5 text-sm tabular-nums text-gray-500 dark:text-gray-400">{{ \App\Helpers\PriceHelper::format($plan->price) }}</p>
                            </div>
                        @endforeach
                    </div>
                @endif
            </x-common.component-card>

            <x-common.component-card title="Recent payments" desc="Orders placed against this reseller's plans.">
                @if ($orders->isEmpty())
                    <p class="py-4 text-sm text-gray-500 dark:text-gray-400">No payments recorded yet.</p>
                @else
                    <div class="overflow-x-auto">
                        <table class="w-full min-w-[28rem] text-sm">
                            <thead>
                                <tr class="border-b border-gray-100 dark:border-gray-800">
                                    <th class="pb-2 text-left text-xs font-medium uppercase tracking-wide text-gray-400 dark:text-gray-500">Date</th>
                                    <th class="pb-2 text-left text-xs font-medium uppercase tracking-wide text-gray-400 dark:text-gray-500">Client</th>
                                    <th class="pb-2 text-right text-xs font-medium uppercase tracking-wide text-gray-400 dark:text-gray-500">Amount</th>
                                    <th class="pb-2 text-right text-xs font-medium uppercase tracking-wide text-gray-400 dark:text-gray-500">Status</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                                @foreach ($orders as $order)
                                    <tr>
                                        <td class="py-2.5 text-gray-500 dark:text-gray-400">{{ $order->created_at?->format('M j, Y') }}</td>
                                        <td class="py-2.5 text-gray-800 dark:text-white/90">{{ $order->user->name ?? 'Deleted user' }}</td>
                                        <td class="py-2.5 text-right tabular-nums text-gray-800 dark:text-white/90">{{ \App\Helpers\PriceHelper::format($order->amount) }} {{ $order->currency }}</td>
                                        <td class="py-2.5 text-right">
                                            <span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium
                                                {{ $order->status === 'completed' ? 'bg-green-50 text-green-700 dark:bg-green-900/25 dark:text-green-400' : '' }}
                                                {{ $order->status === 'pending' ? 'bg-amber-50 text-amber-700 dark:bg-amber-900/25 dark:text-amber-400' : '' }}
                                                {{ in_array($order->status, ['failed', 'cancelled']) ? 'bg-red-50 text-red-700 dark:bg-red-900/25 dark:text-red-400' : '' }}">
                                                {{ ucfirst($order->status) }}
                                            </span>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </x-common.component-card>
        </div>

        {{-- ─── Right column: what you control ───────────────────────── --}}
        <div class="space-y-6">
            <x-common.component-card title="Account">
                <dl class="space-y-4 text-sm">
                    <div>
                        <dt class="text-xs font-medium uppercase tracking-wide text-gray-400 dark:text-gray-500">Owner</dt>
                        <dd class="mt-1 text-gray-800 dark:text-white/90">{{ $reseller->owner?->name ?? '—' }}</dd>
                        @if ($reseller->owner)
                            <dd class="text-xs text-gray-500 dark:text-gray-400">{{ $reseller->owner->email }}</dd>
                        @endif
                    </div>
                    <div>
                        <dt class="text-xs font-medium uppercase tracking-wide text-gray-400 dark:text-gray-500">Joined</dt>
                        <dd class="mt-1 text-gray-800 dark:text-white/90">{{ $reseller->created_at?->format('F j, Y') }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs font-medium uppercase tracking-wide text-gray-400 dark:text-gray-500">Own payment gateway</dt>
                        <dd class="mt-1 text-gray-800 dark:text-white/90">
                            {{ $reseller->pesapal_enabled ? 'Pesapal ('.($reseller->pesapal_environment ?: 'unset').')' : 'Platform default' }}
                        </dd>
                    </div>
                </dl>

                <form action="{{ route('settings.resellers.update', $reseller) }}" method="POST" class="border-t border-gray-100 dark:border-gray-800 pt-5">
                    @csrf @method('PUT')
                    <input type="hidden" name="name" value="{{ $reseller->name }}">
                    <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">Tier</label>
                    <div class="flex gap-2">
                        <select name="reseller_tier_id"
                            class="h-11 w-full rounded-lg border border-gray-300 dark:border-gray-700 bg-transparent px-3 text-sm text-gray-800 dark:text-white/90 focus:border-brand-300 focus:ring-3 focus:ring-brand-500/10 focus:outline-hidden">
                            <option value="">No tier</option>
                            @foreach ($tiers as $tier)
                                <option value="{{ $tier->id }}" {{ $reseller->reseller_tier_id === $tier->id ? 'selected' : '' }}>{{ $tier->name }}</option>
                            @endforeach
                        </select>
                        <button type="submit" class="btn btn-secondary btn-md">Save</button>
                    </div>
                    @if ($reseller->tier)
                        <p class="mt-2 text-xs text-gray-500 dark:text-gray-400">
                            {{ \App\Helpers\PriceHelper::format($reseller->tier->annual_price) }}/yr &middot;
                            {{ $reseller->tier->memorial_profile_allowance ?? 'Unlimited' }} profiles included
                        </p>
                    @endif
                </form>
            </x-common.component-card>

            {{-- Billing: what they owe the platform. Not to be confused with "Collected"
                 above, which is their clients paying them. --}}
            <x-common.component-card title="Billing">
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <p class="text-2xl font-semibold tabular-nums text-gray-800 dark:text-white/90">
                            {{ \App\Helpers\PriceHelper::format($reseller->amountDue()) }}
                        </p>
                        <p class="mt-0.5 text-xs text-gray-500 dark:text-gray-400">due at renewal</p>
                    </div>
                    <x-admin.billing-badge :reseller="$reseller" />
                </div>

                <dl class="space-y-2 border-t border-gray-100 dark:border-gray-800 pt-5 text-sm">
                    <div class="flex items-baseline justify-between gap-3">
                        <dt class="text-gray-500 dark:text-gray-400">{{ $reseller->tier?->name ?? 'No tier' }} annual</dt>
                        <dd class="tabular-nums text-gray-800 dark:text-white/90">{{ \App\Helpers\PriceHelper::format($reseller->tier?->annual_price ?? 0) }}</dd>
                    </div>
                    @if ($reseller->overageProfiles() > 0)
                        <div class="flex items-baseline justify-between gap-3">
                            <dt class="text-gray-500 dark:text-gray-400">
                                {{ $reseller->overageProfiles() }} over allowance
                                <span class="text-xs">&times; {{ \App\Helpers\PriceHelper::format($reseller->tier->price_per_additional_profile) }}</span>
                            </dt>
                            <dd class="tabular-nums text-gray-800 dark:text-white/90">{{ \App\Helpers\PriceHelper::format($reseller->overageAmount()) }}</dd>
                        </div>
                    @endif
                    @if ($reseller->billing_period_end)
                        <div class="flex items-baseline justify-between gap-3 border-t border-gray-100 dark:border-gray-800 pt-2">
                            <dt class="text-gray-500 dark:text-gray-400">Renews</dt>
                            <dd class="text-gray-800 dark:text-white/90">{{ $reseller->billing_period_end->format('M j, Y') }}</dd>
                        </div>
                    @endif
                </dl>

                <div x-data="{ open: false }" class="border-t border-gray-100 dark:border-gray-800 pt-5">
                    <button type="button" @click="open = !open" class="btn btn-secondary btn-sm">Record a payment</button>

                    {{-- submitting guard: a second click would create a second payment and
                         roll the renewal date forward another whole year. --}}
                    <form x-show="open" x-cloak x-data="{ submitting: false }" @submit="submitting = true"
                        action="{{ route('settings.resellers.payments.store', $reseller) }}" method="POST" class="mt-4 space-y-3">
                        @csrf
                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label class="mb-1 block text-xs font-medium text-gray-600 dark:text-gray-400">Amount ({{ $currency }})</label>
                                <input type="number" name="amount" step="0.01" min="0" value="{{ old('amount', $reseller->amountDue()) }}" required
                                    class="h-9 w-full rounded-lg border border-gray-300 dark:border-gray-700 bg-transparent px-3 text-sm tabular-nums text-gray-800 dark:text-white/90 focus:border-brand-300 focus:outline-hidden" />
                            </div>
                            <div>
                                <label class="mb-1 block text-xs font-medium text-gray-600 dark:text-gray-400">Paid on</label>
                                <input type="date" name="paid_at" value="{{ old('paid_at', now()->toDateString()) }}" required
                                    class="h-9 w-full rounded-lg border border-gray-300 dark:border-gray-700 bg-transparent px-3 text-sm text-gray-800 dark:text-white/90 focus:border-brand-300 focus:outline-hidden" />
                            </div>
                        </div>
                        <div>
                            <label class="mb-1 block text-xs font-medium text-gray-600 dark:text-gray-400">Method</label>
                            <select name="method" class="h-9 w-full rounded-lg border border-gray-300 dark:border-gray-700 bg-transparent px-3 text-sm text-gray-800 dark:text-white/90 focus:border-brand-300 focus:outline-hidden">
                                @foreach (\App\Models\ResellerPayment::methods() as $value => $label)
                                    <option value="{{ $value }}" @selected(old('method') === $value)>{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="mb-1 block text-xs font-medium text-gray-600 dark:text-gray-400">Reference <span class="font-normal text-gray-400">(optional)</span></label>
                            <input type="text" name="reference" value="{{ old('reference') }}" placeholder="Transfer ref, invoice no."
                                class="h-9 w-full rounded-lg border border-gray-300 dark:border-gray-700 bg-transparent px-3 text-sm text-gray-800 dark:text-white/90 placeholder:text-gray-400 focus:border-brand-300 focus:outline-hidden" />
                        </div>
                        <p class="text-xs text-gray-500 dark:text-gray-400">
                            @if ($reseller->billing_period_end?->isFuture())
                                Extends their period to {{ $reseller->billing_period_end->copy()->addYear()->format('M j, Y') }} — renewals stay on the same anniversary.
                            @else
                                Starts a one-year period from today.
                            @endif
                        </p>
                        <div class="flex gap-2">
                            <button type="submit" class="btn btn-primary btn-sm" :disabled="submitting" :class="submitting && 'btn-disabled'">
                                <span x-text="submitting ? 'Recording…' : 'Record payment'"></span>
                            </button>
                            <button type="button" @click="open = false" class="btn btn-secondary btn-sm">Cancel</button>
                        </div>
                    </form>
                </div>

                @if ($payments->isNotEmpty())
                    <ul class="-mb-2 divide-y divide-gray-100 dark:divide-gray-800 border-t border-gray-100 dark:border-gray-800">
                        @foreach ($payments as $payment)
                            <li class="flex items-baseline justify-between gap-3 py-2.5 text-sm">
                                <div class="min-w-0">
                                    <p class="text-gray-800 dark:text-white/90">{{ $payment->paid_at->format('M j, Y') }}</p>
                                    <p class="truncate text-xs text-gray-500 dark:text-gray-400">
                                        {{ \App\Models\ResellerPayment::methods()[$payment->method] ?? $payment->method }}
                                        @if ($payment->reference) &middot; {{ $payment->reference }} @endif
                                    </p>
                                </div>
                                <span class="shrink-0 tabular-nums text-gray-800 dark:text-white/90">{{ \App\Helpers\PriceHelper::format($payment->amount) }}</span>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </x-common.component-card>

            <x-common.component-card title="Custom domain">
                @if ($reseller->custom_domain)
                    <div>
                        <p class="font-mono text-sm text-gray-800 dark:text-white/90">{{ $reseller->custom_domain }}</p>
                        <div class="mt-2 flex flex-wrap items-center gap-2">
                            <x-admin.domain-badge :status="$reseller->custom_domain_status" />
                            @if ($reseller->custom_domain_verified_at)
                                <span class="text-xs text-gray-500 dark:text-gray-400">since {{ $reseller->custom_domain_verified_at->format('M j, Y') }}</span>
                            @endif
                        </div>
                    </div>

                    @if ($reseller->custom_domain_status !== \App\Models\Reseller::DOMAIN_VERIFIED)
                        <div class="border-t border-gray-100 dark:border-gray-800 pt-5">
                            <p class="mb-2 text-sm text-gray-500 dark:text-gray-400">The TXT record that proves they own it — add it at their DNS provider, or send it to them:</p>
                            {{-- Asked for from the service that will do the lookup, so the record
                                 shown is by construction the one that gets checked. --}}
                            <x-common.dns-record type="TXT"
                                :host="app(\App\Services\DomainVerificationService::class)->txtHost($reseller->custom_domain)"
                                :value="$reseller->custom_domain_token" />
                            <form action="{{ route('settings.resellers.custom-domain.check', $reseller) }}" method="POST" class="mt-3">
                                @csrf
                                <button type="submit" class="btn btn-secondary btn-sm">Check the TXT record</button>
                            </form>
                        </div>

                        <form action="{{ route('settings.resellers.verify-domain', $reseller) }}" method="POST" class="border-t border-gray-100 dark:border-gray-800 pt-5"
                            onsubmit="return confirm('Mark this domain verified? Only do this once you have confirmed the DNS is correct yourself — it skips the TXT-record check entirely.')">
                            @csrf
                            <button type="submit" class="btn btn-secondary btn-sm">Mark verified manually</button>
                            <p class="mt-2 text-xs text-gray-500 dark:text-gray-400">
                                A support override for when DNS propagation lag is hiding a TXT record that's already correct.
                            </p>
                        </form>
                    @endif

                    {{-- The half of the job the TXT record doesn't do: where their DNS
                         actually has to point. Root domains can't CNAME, so they get the
                         A record; routing and SSL follow automatically once both this and
                         verification are in place. --}}
                    @php
                        $domainIsApex = substr_count($reseller->custom_domain, '.') === 1;
                        $domainPointTarget = $domainIsApex
                            ? \App\Models\SystemSetting::get('domains.target_ip', '')
                            : \App\Models\SystemSetting::get('domains.target_host', '');
                    @endphp
                    <div class="border-t border-gray-100 dark:border-gray-800 pt-5">
                        <p class="mb-2 text-sm text-gray-500 dark:text-gray-400">
                            Their DNS also has to point here{{ $domainIsApex ? ' — an A record, since root domains can\'t take a CNAME' : '' }}:
                        </p>
                        @if ($domainPointTarget)
                            <x-common.dns-record :type="$domainIsApex ? 'A' : 'CNAME'"
                                :host="$reseller->custom_domain"
                                :value="$domainPointTarget" />
                            <p class="mt-2 text-xs text-gray-500 dark:text-gray-400">
                                Once verified and pointed, the proxy picks the domain up and issues its SSL certificate automatically — nothing to click here.
                            </p>
                        @else
                            <p class="text-sm text-amber-600 dark:text-amber-400">
                                The {{ $domainIsApex ? 'A-record IP' : 'CNAME target' }} isn't set in
                                <a href="{{ route('settings.reseller-settings') }}" class="underline">Reseller settings</a>
                                yet — until it is, there's nothing to tell them to point at.
                            </p>
                        @endif
                    </div>

                    <form action="{{ route('settings.resellers.custom-domain.clear', $reseller) }}" method="POST" class="border-t border-gray-100 dark:border-gray-800 pt-5"
                        onsubmit="return confirm('Remove this custom domain? Their {{ $reseller->slug }}.{{ config('reseller.domain') }} address keeps working — visitors on the removed domain will stop reaching their site.')">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="btn btn-danger-soft btn-sm">Remove domain</button>
                    </form>
                @else
                    <form action="{{ route('settings.resellers.custom-domain.set', $reseller) }}" method="POST">
                        @csrf
                        @method('PUT')
                        <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300" for="custom_domain">Domain</label>
                        <input type="text" name="custom_domain" id="custom_domain" value="{{ old('custom_domain') }}" placeholder="memorials.their-brand.com"
                            class="dark:bg-dark-900 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 placeholder:text-gray-400 focus:border-brand-300 focus:ring-3 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90" />
                        @error('custom_domain') <p class="mt-1 text-sm text-red-500">{{ $message }}</p> @enderror
                        <button type="submit" class="btn btn-primary btn-sm mt-3">Set custom domain</button>
                        <p class="mt-2 text-xs text-gray-500 dark:text-gray-400">
                            Setting it here does what the reseller's own settings page does: it stores the domain and mints the TXT challenge, ready to verify. Until one is set they're on {{ $reseller->slug }}.{{ config('reseller.domain') }}.
                        </p>
                    </form>
                @endif
            </x-common.component-card>

            {{-- Partnership lifecycle. Deliberately not on the roster's row menu — these
                 reassign every client and memorial and need their consequence spelled out. --}}
            <div class="rounded-2xl border border-red-200 dark:border-red-500/25 bg-white dark:bg-white/[0.03]">
                <div class="px-6 py-5">
                    <h3 class="text-base font-medium text-gray-800 dark:text-white/90">Partnership</h3>
                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">For when the agreement with this reseller ends or resumes.</p>
                </div>
                <div class="space-y-5 border-t border-gray-100 dark:border-gray-800 p-6">
                    <form action="{{ route('settings.resellers.rollover', $reseller) }}" method="POST"
                        onsubmit="return confirm('Roll over every client and memorial to direct platform ownership? This is meant for when the partnership has ended. It can be reversed with Restore.')">
                        @csrf
                        <button type="submit" class="btn btn-danger-soft btn-sm">Roll over clients</button>
                        <p class="mt-2 text-xs text-gray-500 dark:text-gray-400">
                            Moves all {{ number_format($reseller->staff_count) }} accounts and {{ number_format($reseller->memorials_count) }} memorials to direct platform ownership. Nothing is deleted, and the original link is remembered so this can be undone.
                        </p>
                    </form>

                    <form action="{{ route('settings.resellers.restore', $reseller) }}" method="POST" class="border-t border-gray-100 dark:border-gray-800 pt-5">
                        @csrf
                        <button type="submit" class="btn btn-secondary btn-sm" @disabled($rolledOverCount === 0)>Restore clients</button>
                        <p class="mt-2 text-xs text-gray-500 dark:text-gray-400">
                            @if ($rolledOverCount > 0)
                                Reassigns the {{ number_format($rolledOverCount) }} previously rolled-over account(s) back to this reseller.
                            @else
                                Nothing to restore — no accounts have been rolled over.
                            @endif
                        </p>
                    </form>
                </div>
            </div>
        </div>
    </div>
@endsection
