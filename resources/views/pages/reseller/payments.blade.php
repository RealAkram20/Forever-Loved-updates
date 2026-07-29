@extends('layouts.app')

@section('content')
    <x-common.page-breadcrumb pageTitle="Payment Settings" />

    @if (session('success'))
        <div class="mb-4 rounded-lg bg-green-50 dark:bg-green-900/20 px-4 py-3 text-sm text-green-700 dark:text-green-400">{{ session('success') }}</div>
    @endif
    @if (session('error'))
        <div class="mb-4 rounded-lg bg-red-50 dark:bg-red-900/20 px-4 py-3 text-sm text-red-700 dark:text-red-400">{{ session('error') }}</div>
    @endif

    <x-common.component-card title="Your Pesapal Account" desc="Connect your own Pesapal merchant account so payments from your clients go directly to you.">
        <form action="{{ route('reseller.payments.update') }}" method="POST" class="space-y-5">
            @csrf @method('PUT')

            <label class="flex items-center gap-2 text-sm text-gray-700 dark:text-gray-300">
                <input type="hidden" name="pesapal_enabled" value="0">
                <input type="checkbox" name="pesapal_enabled" value="1" {{ $reseller->pesapal_enabled ? 'checked' : '' }} class="rounded border-gray-300 dark:border-gray-700 text-brand-500 focus:ring-brand-500" />
                Enable my own Pesapal account for checkout
            </label>

            <div>
                <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">Environment</label>
                <select name="pesapal_environment" class="h-11 w-full rounded-lg border border-gray-300 dark:border-gray-700 bg-transparent px-4 py-2.5 text-sm text-gray-800 dark:text-white/90 focus:border-brand-300 focus:ring-3 focus:ring-brand-500/10 focus:outline-hidden">
                    <option value="sandbox" {{ $reseller->pesapal_environment === 'sandbox' ? 'selected' : '' }}>Sandbox (testing)</option>
                    <option value="live" {{ $reseller->pesapal_environment === 'live' ? 'selected' : '' }}>Live</option>
                </select>
            </div>

            <div class="grid grid-cols-1 gap-5 sm:grid-cols-2">
                <div>
                    <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">Consumer Key</label>
                    <input type="text" name="pesapal_consumer_key" value="{{ $reseller->pesapal_consumer_key }}"
                        class="h-11 w-full rounded-lg border border-gray-300 dark:border-gray-700 bg-transparent px-4 py-2.5 text-sm text-gray-800 dark:text-white/90 focus:border-brand-300 focus:ring-3 focus:ring-brand-500/10 focus:outline-hidden" />
                </div>
                <div>
                    <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">Consumer Secret</label>
                    <input type="password" name="pesapal_consumer_secret" value="{{ $reseller->pesapal_consumer_secret ? '••••••••' : '' }}"
                        class="h-11 w-full rounded-lg border border-gray-300 dark:border-gray-700 bg-transparent px-4 py-2.5 text-sm text-gray-800 dark:text-white/90 focus:border-brand-300 focus:ring-3 focus:ring-brand-500/10 focus:outline-hidden" />
                </div>
            </div>

            <div class="flex justify-end">
                <button type="submit" class="btn btn-primary btn-md">Save</button>
            </div>
        </form>

        @if ($reseller->pesapal_enabled)
            <form action="{{ route('reseller.payments.register-ipn') }}" method="POST" class="mt-4">
                @csrf
                <button type="submit" class="btn btn-secondary btn-sm">Generate IPN</button>
                @if ($reseller->pesapal_ipn_id)
                    <span class="ml-2 text-xs text-gray-500 dark:text-gray-400">IPN registered.</span>
                @endif
            </form>
        @endif
    </x-common.component-card>
@endsection
