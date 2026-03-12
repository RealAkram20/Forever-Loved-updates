@extends('layouts.app')

@section('content')
    <x-common.page-breadcrumb pageTitle="Notification Settings" />

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

    <form action="{{ route('settings.notifications.update') }}" method="POST" class="space-y-6">
        @csrf
        @method('PUT')

        {{-- Email Notifications --}}
        <x-common.component-card title="Email Notifications" desc="Send notification emails when important events occur. Requires SMTP to be configured.">
            <div class="space-y-6">
                <div class="flex items-center justify-between"
                    x-data="{ enabled: @json((bool) old('notifications.email_enabled', $settings['notifications.email_enabled'] ?? false)) }">
                    <div>
                        <p class="text-sm font-medium text-gray-800 dark:text-white/90">Enable Email Notifications</p>
                        <p class="text-sm text-gray-500 dark:text-gray-400">Send email alerts for new signups, tributes, and other events.</p>
                    </div>
                    <input type="hidden" name="notifications[email_enabled]" :value="enabled ? '1' : '0'">
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

                <div class="rounded-lg border border-amber-200 dark:border-amber-800 bg-amber-50 dark:bg-amber-900/20 p-4">
                    <div class="flex gap-3">
                        <svg class="w-5 h-5 text-amber-500 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                        <p class="text-sm text-amber-700 dark:text-amber-300">
                            Email notifications require SMTP to be configured. <a href="{{ route('settings.smtp') }}" class="font-medium underline hover:text-amber-800 dark:hover:text-amber-200">Configure SMTP &rarr;</a>
                        </p>
                    </div>
                </div>
            </div>
        </x-common.component-card>

        {{-- Push Notifications --}}
        <div id="push-notifications-section">
        @if (isset($pushExtensionOk) && !$pushExtensionOk)
        <div class="mb-4 rounded-lg border border-red-200 dark:border-red-800 bg-red-50 dark:bg-red-900/20 p-4">
            <p class="text-sm font-medium text-red-800 dark:text-red-200">Push requires BCMath or GMP PHP extension</p>
            <p class="mt-1 text-sm text-red-700 dark:text-red-300">Enable <strong>BCMath</strong> in Hostinger: Advanced → PHP Configuration → PHP Extensions. Wait a few minutes after enabling.</p>
        </div>
        @endif
        <x-common.component-card title="Push Notifications" desc="Send browser push notifications to users. Uses the free Web Push API (VAPID) — no paid services required.">
            <div class="space-y-6">
                <div class="flex items-center justify-between"
                    x-data="{ enabled: @json((bool) old('notifications.push_enabled', $settings['notifications.push_enabled'] ?? false)) }">
                    <div>
                        <p class="text-sm font-medium text-gray-800 dark:text-white/90">Enable Push Notifications</p>
                        <p class="text-sm text-gray-500 dark:text-gray-400">Deliver real-time browser notifications to subscribed users.</p>
                    </div>
                    <input type="hidden" name="notifications[push_enabled]" :value="enabled ? '1' : '0'">
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

                {{-- Test Push Button --}}
                <div x-data="{ testing: false, result: null, resetting: false }"
                    class="rounded-lg border border-gray-200 dark:border-gray-700 p-4">
                    <p class="text-sm font-medium text-gray-800 dark:text-white/90 mb-2">Test Push Notification</p>
                    <p class="text-xs text-gray-500 dark:text-gray-400 mb-3">Send a test push to your browser. Enable push when the popup appears, or via the bell dropdown.</p>
                    <div class="flex flex-wrap gap-2">
                        <button type="button"
                            @click="
                                testing = true;
                                result = null;
                                fetch('{{ route('notifications.push.test') }}', {
                                    method: 'POST',
                                    headers: {
                                        'Content-Type': 'application/json',
                                        'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]')?.content,
                                        'Accept': 'application/json',
                                    },
                                })
                                .then(r => r.json())
                                .then(data => { result = data; testing = false; })
                                .catch(e => { result = { success: false, message: e.message }; testing = false; });
                            "
                            :disabled="testing"
                            class="inline-flex items-center gap-2 rounded-lg bg-brand-500 px-4 py-2 text-sm font-medium text-white hover:bg-brand-600 disabled:opacity-50 transition">
                            <span x-show="testing">Sending...</span>
                            <span x-show="!testing">Send Test Push</span>
                        </button>
                        <button type="button"
                            @click="
                                resetting = true;
                                (async () => {
                                    try {
                                        const reg = await navigator.serviceWorker.ready;
                                        const sub = await reg.pushManager.getSubscription();
                                        if (sub) await sub.unsubscribe();
                                    } catch (e) {}
                                    await fetch('{{ route('notifications.push.reset') }}', {
                                        method: 'POST',
                                        headers: {
                                            'Content-Type': 'application/json',
                                            'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]')?.content,
                                            'Accept': 'application/json',
                                        },
                                    });
                                    resetting = false;
                                    result = { success: true, message: 'Reset complete. Refresh the page and allow notifications when the popup appears.' };
                                })();
                            "
                            :disabled="resetting"
                            class="inline-flex items-center gap-2 rounded-lg border border-amber-300 dark:border-amber-700 px-4 py-2 text-sm font-medium text-amber-700 dark:text-amber-400 hover:bg-amber-50 dark:hover:bg-amber-900/20 disabled:opacity-50 transition">
                            <span x-show="resetting">Resetting...</span>
                            <span x-show="!resetting">Reset & Re-subscribe</span>
                        </button>
                    </div>
                    <div x-show="result" x-cloak class="mt-3 text-sm"
                        :class="result?.success ? 'text-green-600 dark:text-green-400' : 'text-red-600 dark:text-red-400'">
                        <span x-text="result?.message"></span>
                    </div>
                    <p class="mt-2 text-xs text-amber-600 dark:text-amber-400" x-show="result && !result?.success && ((result?.message || '').includes('VAPID') || (result?.message || '').includes('403'))" x-cloak>
                        Your subscription was created with different VAPID keys. Click <strong>Reset & Re-subscribe</strong> above, then refresh the page.
                    </p>
                </div>

                <div class="grid grid-cols-1 gap-6">
                    <div>
                        <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">VAPID Public Key</label>
                        <input type="text" name="notifications[vapid_public_key]"
                            value="{{ old('notifications.vapid_public_key', $settings['notifications.vapid_public_key'] ?? '') }}"
                            placeholder="BEl62iUYgU..."
                            class="h-11 w-full rounded-lg border border-gray-300 dark:border-gray-700 bg-transparent px-4 py-2.5 text-sm text-gray-800 dark:text-white/90 placeholder:text-gray-400 focus:border-brand-300 focus:ring-3 focus:ring-brand-500/10 focus:outline-hidden font-mono text-xs" />
                    </div>
                    <div>
                        <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">VAPID Private Key</label>
                        <input type="password" name="notifications[vapid_private_key]"
                            value="{{ !empty($settings['notifications.vapid_private_key']) ? '••••••••' : '' }}"
                            placeholder="Private key..."
                            class="h-11 w-full rounded-lg border border-gray-300 dark:border-gray-700 bg-transparent px-4 py-2.5 text-sm text-gray-800 dark:text-white/90 placeholder:text-gray-400 focus:border-brand-300 focus:ring-3 focus:ring-brand-500/10 focus:outline-hidden" />
                        <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Leave as dots to keep existing key.</p>
                    </div>
                </div>

                <div class="rounded-lg border border-blue-200 dark:border-blue-800 bg-blue-50 dark:bg-blue-900/20 p-4">
                    <div class="flex gap-3">
                        <svg class="w-5 h-5 text-blue-500 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                        <div class="text-sm text-blue-700 dark:text-blue-300">
                            <p class="font-medium mb-1">How to generate VAPID keys (free):</p>
                            <ol class="list-decimal list-inside space-y-1 text-xs">
                                <li>Install the <code class="bg-blue-100 dark:bg-blue-800 px-1 rounded">web-push</code> PHP package: <code class="bg-blue-100 dark:bg-blue-800 px-1 rounded">composer require minishlink/web-push</code></li>
                                <li>Generate keys: <code class="bg-blue-100 dark:bg-blue-800 px-1 rounded">php artisan tinker</code> &rarr; <code class="bg-blue-100 dark:bg-blue-800 px-1 rounded">Minishlink\WebPush\VAPID::createVapidKeys()</code></li>
                                <li>Or use: <code class="bg-blue-100 dark:bg-blue-800 px-1 rounded">npx web-push generate-vapid-keys</code></li>
                            </ol>
                            <p class="mt-2 text-xs">Push notifications require HTTPS and a service worker. They are completely free with no third-party costs.</p>
                        </div>
                    </div>
                </div>
            </div>
        </x-common.component-card>
        </div>

        {{-- Notification Types Info --}}
        <x-common.component-card title="Notification Events" desc="These events trigger notifications in the system.">
            <div class="grid grid-cols-1 gap-4 lg:grid-cols-2">
                <div class="rounded-lg border border-gray-200 dark:border-gray-700 p-4">
                    <h4 class="text-sm font-semibold text-gray-800 dark:text-white/90 mb-3 flex items-center gap-2">
                        <svg class="w-4 h-4 text-brand-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/></svg>
                        Admin Notifications
                    </h4>
                    <ul class="space-y-2 text-xs text-gray-500 dark:text-gray-400">
                        <li class="flex items-start gap-2">
                            <span class="w-1.5 h-1.5 bg-blue-400 rounded-full mt-1.5 flex-shrink-0"></span>
                            New user signups
                        </li>
                        <li class="flex items-start gap-2">
                            <span class="w-1.5 h-1.5 bg-green-400 rounded-full mt-1.5 flex-shrink-0"></span>
                            New payments received
                        </li>
                        <li class="flex items-start gap-2">
                            <span class="w-1.5 h-1.5 bg-pink-400 rounded-full mt-1.5 flex-shrink-0"></span>
                            New tributes on any memorial
                        </li>
                        <li class="flex items-start gap-2">
                            <span class="w-1.5 h-1.5 bg-purple-400 rounded-full mt-1.5 flex-shrink-0"></span>
                            New life chapters added
                        </li>
                    </ul>
                </div>
                <div class="rounded-lg border border-gray-200 dark:border-gray-700 p-4">
                    <h4 class="text-sm font-semibold text-gray-800 dark:text-white/90 mb-3 flex items-center gap-2">
                        <svg class="w-4 h-4 text-brand-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
                        User Notifications
                    </h4>
                    <ul class="space-y-2 text-xs text-gray-500 dark:text-gray-400">
                        <li class="flex items-start gap-2">
                            <span class="w-1.5 h-1.5 bg-amber-400 rounded-full mt-1.5 flex-shrink-0"></span>
                            Memorial activated or suspended
                        </li>
                        <li class="flex items-start gap-2">
                            <span class="w-1.5 h-1.5 bg-indigo-400 rounded-full mt-1.5 flex-shrink-0"></span>
                            Memorial assigned to them
                        </li>
                        <li class="flex items-start gap-2">
                            <span class="w-1.5 h-1.5 bg-pink-400 rounded-full mt-1.5 flex-shrink-0"></span>
                            Their memorial receives a tribute
                        </li>
                        <li class="flex items-start gap-2">
                            <span class="w-1.5 h-1.5 bg-purple-400 rounded-full mt-1.5 flex-shrink-0"></span>
                            New life chapter on their memorial
                        </li>
                    </ul>
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
