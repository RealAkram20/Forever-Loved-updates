@extends('layouts.app')

@section('content')
    <x-common.page-breadcrumb pageTitle="Payment Settings" />

    @if (session('success'))
        <div class="mb-4 rounded-lg bg-green-50 dark:bg-green-900/20 px-4 py-3 text-sm text-green-700 dark:text-green-400">
            {{ session('success') }}
        </div>
    @endif

    @if ($errors->any())
        <div class="mb-4 rounded-lg bg-red-50 dark:bg-red-900/20 px-4 py-3">
            <p class="text-sm font-medium text-red-700 dark:text-red-400 mb-1">Please fix the following errors:</p>
            <ul class="list-disc list-inside text-sm text-red-600 dark:text-red-400">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form action="{{ route('settings.payments.update') }}" method="POST" class="space-y-6">
        @csrf
        @method('PUT')

        <div class="mb-4 flex justify-end">
            <a href="{{ route('settings.payment-orders') }}"
                class="btn btn-secondary btn-md">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"/></svg>
                View Payment Orders
            </a>
        </div>

        {{-- Global Payment Settings --}}
        <x-common.component-card title="Payment Settings" desc="Enable payments and set the default currency.">
            <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
                <div class="flex items-center justify-between lg:col-span-2"
                    x-data="{ enabled: @json((bool) old('payments.enabled', $settings['payments.enabled'] ?? false)) }">
                    <div>
                        <p class="text-sm font-medium text-gray-800 dark:text-white/90">Enable Payments</p>
                        <p class="text-sm text-gray-500 dark:text-gray-400">Allow users to purchase premium plans.</p>
                    </div>
                    <input type="hidden" name="payments[enabled]" :value="enabled ? '1' : '0'">
                    <label class="flex cursor-pointer select-none items-center">
                        <div class="relative">
                            <input type="checkbox" class="sr-only" @change="enabled = !enabled" :checked="enabled">
                            <div class="block h-6 w-11 rounded-full transition-colors duration-200"
                                :class="enabled ? 'bg-brand-500' : 'bg-gray-200 dark:bg-gray-700'"></div>
                            <div class="shadow-theme-sm absolute top-0.5 left-0.5 h-5 w-5 rounded-full bg-white duration-300 ease-linear"
                                :class="enabled ? 'translate-x-full' : 'translate-x-0'"></div>
                        </div>
                    </label>
                </div>

                <div>
                    <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">Currency</label>
                    <select name="payments[currency]"
                        class="h-11 w-full rounded-lg border border-gray-300 dark:border-gray-700 bg-transparent px-4 py-2.5 text-sm text-gray-800 dark:text-white/90 focus:border-brand-300 focus:ring-3 focus:ring-brand-500/10 focus:outline-hidden">
                        @foreach (['USD' => 'USD - US Dollar', 'EUR' => 'EUR - Euro', 'GBP' => 'GBP - British Pound', 'UGX' => 'UGX - Ugandan Shilling', 'KES' => 'KES - Kenyan Shilling', 'NGN' => 'NGN - Nigerian Naira'] as $code => $label)
                            <option value="{{ $code }}" {{ old('payments.currency', $settings['payments.currency'] ?? 'USD') === $code ? 'selected' : '' }}>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
        </x-common.component-card>

        {{-- Stripe --}}
        <x-common.component-card title="Stripe" desc="Configure Stripe for card payments.">
            <div class="space-y-6">
                <div class="flex items-center justify-between"
                    x-data="{ enabled: @json((bool) old('payments.stripe_enabled', $settings['payments.stripe_enabled'] ?? false)) }">
                    <div>
                        <p class="text-sm font-medium text-gray-800 dark:text-white/90">Enable Stripe</p>
                        <p class="text-sm text-gray-500 dark:text-gray-400">Accept credit/debit card payments via Stripe.</p>
                    </div>
                    <input type="hidden" name="payments[stripe_enabled]" :value="enabled ? '1' : '0'">
                    <label class="flex cursor-pointer select-none items-center">
                        <div class="relative">
                            <input type="checkbox" class="sr-only" @change="enabled = !enabled" :checked="enabled">
                            <div class="block h-6 w-11 rounded-full transition-colors duration-200"
                                :class="enabled ? 'bg-brand-500' : 'bg-gray-200 dark:bg-gray-700'"></div>
                            <div class="shadow-theme-sm absolute top-0.5 left-0.5 h-5 w-5 rounded-full bg-white duration-300 ease-linear"
                                :class="enabled ? 'translate-x-full' : 'translate-x-0'"></div>
                        </div>
                    </label>
                </div>

                <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
                    <div>
                        <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">Public Key</label>
                        <input type="text" name="payments[stripe_public_key]"
                            value="{{ old('payments.stripe_public_key', $settings['payments.stripe_public_key'] ?? '') }}"
                            placeholder="pk_live_..."
                            class="h-11 w-full rounded-lg border border-gray-300 dark:border-gray-700 bg-transparent px-4 py-2.5 text-sm text-gray-800 dark:text-white/90 placeholder:text-gray-400 focus:border-brand-300 focus:ring-3 focus:ring-brand-500/10 focus:outline-hidden" />
                    </div>
                    <div>
                        <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">Secret Key</label>
                        <input type="password" name="payments[stripe_secret_key]"
                            value="{{ !empty($settings['payments.stripe_secret_key']) ? '••••••••' : '' }}"
                            placeholder="sk_live_..."
                            class="h-11 w-full rounded-lg border border-gray-300 dark:border-gray-700 bg-transparent px-4 py-2.5 text-sm text-gray-800 dark:text-white/90 placeholder:text-gray-400 focus:border-brand-300 focus:ring-3 focus:ring-brand-500/10 focus:outline-hidden" />
                        <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Leave as dots to keep existing key.</p>
                    </div>
                </div>
            </div>
        </x-common.component-card>

        {{-- Pesapal --}}
        <x-common.component-card title="Pesapal" desc="Configure Pesapal for mobile money and local payments.">
            <div class="space-y-6">
                <div class="flex items-center justify-between"
                    x-data="{ enabled: @json((bool) old('payments.pesapal_enabled', $settings['payments.pesapal_enabled'] ?? false)) }">
                    <div>
                        <p class="text-sm font-medium text-gray-800 dark:text-white/90">Enable Pesapal</p>
                        <p class="text-sm text-gray-500 dark:text-gray-400">Accept mobile money and local payments via Pesapal.</p>
                    </div>
                    <input type="hidden" name="payments[pesapal_enabled]" :value="enabled ? '1' : '0'">
                    <label class="flex cursor-pointer select-none items-center">
                        <div class="relative">
                            <input type="checkbox" class="sr-only" @change="enabled = !enabled" :checked="enabled">
                            <div class="block h-6 w-11 rounded-full transition-colors duration-200"
                                :class="enabled ? 'bg-brand-500' : 'bg-gray-200 dark:bg-gray-700'"></div>
                            <div class="shadow-theme-sm absolute top-0.5 left-0.5 h-5 w-5 rounded-full bg-white duration-300 ease-linear"
                                :class="enabled ? 'translate-x-full' : 'translate-x-0'"></div>
                        </div>
                    </label>
                </div>

                <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
                    <div>
                        <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">Consumer Key</label>
                        <input type="text" name="payments[pesapal_consumer_key]"
                            value="{{ old('payments.pesapal_consumer_key', $settings['payments.pesapal_consumer_key'] ?? '') }}"
                            class="h-11 w-full rounded-lg border border-gray-300 dark:border-gray-700 bg-transparent px-4 py-2.5 text-sm text-gray-800 dark:text-white/90 placeholder:text-gray-400 focus:border-brand-300 focus:ring-3 focus:ring-brand-500/10 focus:outline-hidden" />
                    </div>
                    <div>
                        <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">Consumer Secret</label>
                        <input type="password" name="payments[pesapal_consumer_secret]"
                            value="{{ !empty($settings['payments.pesapal_consumer_secret']) ? '••••••••' : '' }}"
                            class="h-11 w-full rounded-lg border border-gray-300 dark:border-gray-700 bg-transparent px-4 py-2.5 text-sm text-gray-800 dark:text-white/90 placeholder:text-gray-400 focus:border-brand-300 focus:ring-3 focus:ring-brand-500/10 focus:outline-hidden" />
                    </div>
                    <div>
                        <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">Environment</label>
                        <select name="payments[pesapal_environment]"
                            class="h-11 w-full rounded-lg border border-gray-300 dark:border-gray-700 bg-transparent px-4 py-2.5 text-sm text-gray-800 dark:text-white/90 focus:border-brand-300 focus:ring-3 focus:ring-brand-500/10 focus:outline-hidden">
                            <option value="sandbox" {{ old('payments.pesapal_environment', $settings['payments.pesapal_environment'] ?? 'sandbox') === 'sandbox' ? 'selected' : '' }}>Sandbox (Testing)</option>
                            <option value="live" {{ old('payments.pesapal_environment', $settings['payments.pesapal_environment'] ?? 'sandbox') === 'live' ? 'selected' : '' }}>Live (Production)</option>
                        </select>
                    </div>
                    <div class="lg:col-span-3" x-data="pesapalIpn()">
                        <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">IPN ID (Required for Pesapal)</label>
                        <div class="flex flex-col gap-2 sm:flex-row">
                            <input type="text" name="payments[pesapal_ipn_id]" x-model="ipnId"
                                placeholder="Click Generate to register your IPN with Pesapal"
                                class="h-11 w-full rounded-lg border border-gray-300 dark:border-gray-700 bg-transparent px-4 py-2.5 text-sm text-gray-800 dark:text-white/90 placeholder:text-gray-400 focus:border-brand-300 focus:ring-3 focus:ring-brand-500/10 focus:outline-hidden" />
                            <button type="button" @click="generate()" :disabled="busy"
                                class="btn btn-secondary btn-md shrink-0 disabled:opacity-50">
                                <svg x-show="busy" x-cloak class="h-4 w-4 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
                                <span x-text="busy ? 'Registering...' : (ipnId ? 'Re-generate IPN' : 'Generate IPN')"></span>
                            </button>
                        </div>
                        <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                            Generate registers <code class="text-xs">{{ url('/payment/ipn') }}</code> with Pesapal and fills in the returned ID. Save your consumer key, secret and environment first — the URL must be publicly reachable.
                        </p>
                        <p x-show="message" x-cloak class="mt-2 text-xs" :class="ok ? 'text-green-600 dark:text-green-400' : 'text-red-600 dark:text-red-400'" x-text="message"></p>
                    </div>
                </div>
            </div>
        </x-common.component-card>

        <div class="flex justify-end">
            <button type="submit"
                class="btn btn-primary btn-md">
                Save Changes
            </button>
        </div>
    </form>
@endsection

@push('scripts')
<script>
    function pesapalIpn() {
        return {
            ipnId: @json(old('payments.pesapal_ipn_id', $settings['payments.pesapal_ipn_id'] ?? '')),
            busy: false,
            ok: false,
            message: '',

            async generate() {
                this.busy = true;
                this.message = '';
                try {
                    const res = await fetch('{{ route('settings.payments.register-ipn') }}', {
                        method: 'POST',
                        credentials: 'same-origin',
                        headers: {
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content ?? '',
                            'X-Requested-With': 'XMLHttpRequest',
                            'Accept': 'application/json',
                        },
                    });
                    const data = await res.json().catch(() => ({}));
                    this.ok = !!data.success;
                    if (data.success) {
                        this.ipnId = data.ipn_id;
                        this.message = `Registered ${data.ipn_url} with Pesapal. The IPN ID has been saved.`;
                    } else {
                        this.message = data.error || 'IPN registration failed.';
                    }
                } catch (e) {
                    this.ok = false;
                    this.message = 'Network error. Please try again.';
                } finally {
                    this.busy = false;
                }
            },
        };
    }
</script>
@endpush
