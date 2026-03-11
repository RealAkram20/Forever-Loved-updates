@extends('layouts.app')

@section('content')
    <x-common.page-breadcrumb pageTitle="Payment Settings" />

    @if (session('success'))
        <div class="mb-4 rounded-lg bg-green-50 dark:bg-green-900/20 px-4 py-3 text-sm text-green-700 dark:text-green-400">
            {{ session('success') }}
        </div>
    @endif

    <form action="{{ route('settings.payments.update') }}" method="POST" class="space-y-6">
        @csrf
        @method('PUT')

        {{-- Global Payment Settings --}}
        <x-common.component-card title="Payment Settings" desc="Enable payments and set the default currency.">
            <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
                <div class="flex items-center justify-between lg:col-span-2" x-data="{ enabled: {{ old('payments.enabled', $settings['payments.enabled'] ?? false) ? 'true' : 'false' }} }">
                    <div>
                        <p class="text-sm font-medium text-gray-800 dark:text-white/90">Enable Payments</p>
                        <p class="text-sm text-gray-500 dark:text-gray-400">Allow users to purchase premium plans.</p>
                    </div>
                    <input type="hidden" name="payments.enabled" :value="enabled ? '1' : '0'">
                    <button type="button" @click="enabled = !enabled"
                        :class="enabled ? 'bg-brand-500' : 'bg-gray-200 dark:bg-gray-700'"
                        class="relative inline-flex h-6 w-11 shrink-0 cursor-pointer rounded-full transition-colors duration-200 ease-in-out">
                        <span :class="enabled ? 'translate-x-5' : 'translate-x-0'"
                            class="pointer-events-none inline-block h-6 w-6 transform rounded-full bg-white shadow ring-0 transition duration-200 ease-in-out"></span>
                    </button>
                </div>

                <div>
                    <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">Currency</label>
                    <select name="payments.currency"
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
                <div class="flex items-center justify-between" x-data="{ enabled: {{ old('payments.stripe_enabled', $settings['payments.stripe_enabled'] ?? false) ? 'true' : 'false' }} }">
                    <div>
                        <p class="text-sm font-medium text-gray-800 dark:text-white/90">Enable Stripe</p>
                        <p class="text-sm text-gray-500 dark:text-gray-400">Accept credit/debit card payments via Stripe.</p>
                    </div>
                    <input type="hidden" name="payments.stripe_enabled" :value="enabled ? '1' : '0'">
                    <button type="button" @click="enabled = !enabled"
                        :class="enabled ? 'bg-brand-500' : 'bg-gray-200 dark:bg-gray-700'"
                        class="relative inline-flex h-6 w-11 shrink-0 cursor-pointer rounded-full transition-colors duration-200 ease-in-out">
                        <span :class="enabled ? 'translate-x-5' : 'translate-x-0'"
                            class="pointer-events-none inline-block h-6 w-6 transform rounded-full bg-white shadow ring-0 transition duration-200 ease-in-out"></span>
                    </button>
                </div>

                <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
                    <div>
                        <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">Public Key</label>
                        <input type="text" name="payments.stripe_public_key"
                            value="{{ old('payments.stripe_public_key', $settings['payments.stripe_public_key'] ?? '') }}"
                            placeholder="pk_live_..."
                            class="h-11 w-full rounded-lg border border-gray-300 dark:border-gray-700 bg-transparent px-4 py-2.5 text-sm text-gray-800 dark:text-white/90 placeholder:text-gray-400 focus:border-brand-300 focus:ring-3 focus:ring-brand-500/10 focus:outline-hidden" />
                    </div>
                    <div>
                        <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">Secret Key</label>
                        <input type="password" name="payments.stripe_secret_key"
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
                <div class="flex items-center justify-between" x-data="{ enabled: {{ old('payments.pesapal_enabled', $settings['payments.pesapal_enabled'] ?? false) ? 'true' : 'false' }} }">
                    <div>
                        <p class="text-sm font-medium text-gray-800 dark:text-white/90">Enable Pesapal</p>
                        <p class="text-sm text-gray-500 dark:text-gray-400">Accept mobile money and local payments via Pesapal.</p>
                    </div>
                    <input type="hidden" name="payments.pesapal_enabled" :value="enabled ? '1' : '0'">
                    <button type="button" @click="enabled = !enabled"
                        :class="enabled ? 'bg-brand-500' : 'bg-gray-200 dark:bg-gray-700'"
                        class="relative inline-flex h-6 w-11 shrink-0 cursor-pointer rounded-full transition-colors duration-200 ease-in-out">
                        <span :class="enabled ? 'translate-x-5' : 'translate-x-0'"
                            class="pointer-events-none inline-block h-6 w-6 transform rounded-full bg-white shadow ring-0 transition duration-200 ease-in-out"></span>
                    </button>
                </div>

                <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
                    <div>
                        <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">Consumer Key</label>
                        <input type="text" name="payments.pesapal_consumer_key"
                            value="{{ old('payments.pesapal_consumer_key', $settings['payments.pesapal_consumer_key'] ?? '') }}"
                            class="h-11 w-full rounded-lg border border-gray-300 dark:border-gray-700 bg-transparent px-4 py-2.5 text-sm text-gray-800 dark:text-white/90 placeholder:text-gray-400 focus:border-brand-300 focus:ring-3 focus:ring-brand-500/10 focus:outline-hidden" />
                    </div>
                    <div>
                        <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">Consumer Secret</label>
                        <input type="password" name="payments.pesapal_consumer_secret"
                            value="{{ !empty($settings['payments.pesapal_consumer_secret']) ? '••••••••' : '' }}"
                            class="h-11 w-full rounded-lg border border-gray-300 dark:border-gray-700 bg-transparent px-4 py-2.5 text-sm text-gray-800 dark:text-white/90 placeholder:text-gray-400 focus:border-brand-300 focus:ring-3 focus:ring-brand-500/10 focus:outline-hidden" />
                    </div>
                    <div>
                        <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">Environment</label>
                        <select name="payments.pesapal_environment"
                            class="h-11 w-full rounded-lg border border-gray-300 dark:border-gray-700 bg-transparent px-4 py-2.5 text-sm text-gray-800 dark:text-white/90 focus:border-brand-300 focus:ring-3 focus:ring-brand-500/10 focus:outline-hidden">
                            <option value="sandbox" {{ old('payments.pesapal_environment', $settings['payments.pesapal_environment'] ?? 'sandbox') === 'sandbox' ? 'selected' : '' }}>Sandbox (Testing)</option>
                            <option value="live" {{ old('payments.pesapal_environment', $settings['payments.pesapal_environment'] ?? 'sandbox') === 'live' ? 'selected' : '' }}>Live (Production)</option>
                        </select>
                    </div>
                </div>
            </div>
        </x-common.component-card>

        <div class="flex justify-end">
            <button type="submit"
                class="inline-flex items-center gap-2 rounded-lg bg-brand-500 px-5 py-2.5 text-sm font-medium text-white shadow-theme-xs hover:bg-brand-600 transition">
                Save Changes
            </button>
        </div>
    </form>
@endsection
