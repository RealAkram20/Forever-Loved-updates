<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ $title ?? 'Dashboard' }} | {{ config('app.name') }}</title>

    <!-- Scripts -->
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <link rel="icon" href="{{ \App\Helpers\BrandingHelper::faviconUrl() }}" type="image/x-icon" />
    <style>{{ \App\Helpers\BrandingHelper::brandColorCss() }}</style>
    @stack('head')

    <!-- Theme Store -->
    <script>
        document.addEventListener('alpine:init', () => {
            Alpine.store('theme', {
                init() {
                    const savedTheme = localStorage.getItem('theme');
                    this.theme = savedTheme || 'light';
                    this.updateTheme();
                },
                theme: 'light',
                toggle() {
                    this.theme = this.theme === 'light' ? 'dark' : 'light';
                    localStorage.setItem('theme', this.theme);
                    this.updateTheme();
                },
                updateTheme() {
                    const html = document.documentElement;
                    const body = document.body;
                    if (this.theme === 'dark') {
                        html.classList.add('dark');
                        body.classList.add('dark', 'bg-gray-900');
                    } else {
                        html.classList.remove('dark');
                        body.classList.remove('dark', 'bg-gray-900');
                    }
                }
            });

            Alpine.store('sidebar', {
                isExpanded: window.innerWidth >= 1280,
                isMobileOpen: false,
                isHovered: false,

                toggleExpanded() {
                    this.isExpanded = !this.isExpanded;
                    this.isMobileOpen = false;
                },

                toggleMobileOpen() {
                    this.isMobileOpen = !this.isMobileOpen;
                },

                setMobileOpen(val) {
                    this.isMobileOpen = val;
                },

                setHovered(val) {
                    if (window.innerWidth >= 1280 && !this.isExpanded) {
                        this.isHovered = val;
                    }
                }
            });
        });
    </script>

    <!-- Apply theme immediately to prevent flash (default: light) -->
    <script>
        (function() {
            const savedTheme = localStorage.getItem('theme');
            const theme = savedTheme || 'light';
            if (theme === 'dark') {
                document.documentElement.classList.add('dark');
                document.body.classList.add('dark', 'bg-gray-900');
            } else {
                document.documentElement.classList.remove('dark');
                document.body.classList.remove('dark', 'bg-gray-900');
            }
        })();
    </script>

</head>

<body
    class="overflow-x-hidden"
    x-data="{ 'loaded': true}"
    x-init="$store.sidebar.isExpanded = window.innerWidth >= 1280;
    const checkMobile = () => {
        if (window.innerWidth < 1280) {
            $store.sidebar.setMobileOpen(false);
            $store.sidebar.isExpanded = false;
        } else {
            $store.sidebar.isMobileOpen = false;
            $store.sidebar.isExpanded = true;
        }
    };
    window.addEventListener('resize', checkMobile);">

    <x-common.preloader/>

    {{-- Global toast notification system (bottom-right, above everything) --}}
    <div x-data="toastSystem()" x-on:toast.window="addToast($event.detail)"
         class="fixed bottom-4 right-4 left-4 sm:left-auto sm:right-6 sm:bottom-6 z-[99999] flex flex-col-reverse gap-3 pointer-events-none sm:max-w-[400px]">
        <template x-for="toast in toasts" :key="toast.id">
            <div x-show="toast.visible"
                 x-transition:enter="transition ease-out duration-300"
                 x-transition:enter-start="opacity-0 translate-y-4 scale-95"
                 x-transition:enter-end="opacity-100 translate-y-0 scale-100"
                 x-transition:leave="transition ease-in duration-200"
                 x-transition:leave-start="opacity-100 translate-y-0 scale-100"
                 x-transition:leave-end="opacity-0 translate-y-4 scale-95"
                 class="pointer-events-auto overflow-hidden rounded-xl border shadow-xl backdrop-blur-sm"
                 :class="{
                     'bg-red-50/95 border-red-200 dark:bg-red-950/80 dark:border-red-800': toast.type === 'error',
                     'bg-green-50/95 border-green-200 dark:bg-green-950/80 dark:border-green-800': toast.type === 'success',
                     'bg-amber-50/95 border-amber-200 dark:bg-amber-950/80 dark:border-amber-800': toast.type === 'warning',
                     'bg-blue-50/95 border-blue-200 dark:bg-blue-950/80 dark:border-blue-800': toast.type === 'info',
                 }">
                <div class="flex items-start gap-3 px-4 py-3">
                    <div class="shrink-0 mt-0.5">
                        <template x-if="toast.type === 'error'">
                            <svg class="w-5 h-5 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                        </template>
                        <template x-if="toast.type === 'success'">
                            <svg class="w-5 h-5 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                        </template>
                        <template x-if="toast.type === 'warning'">
                            <svg class="w-5 h-5 text-amber-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L3.34 16.5c-.77.833.192 2.5 1.732 2.5z"/></svg>
                        </template>
                        <template x-if="toast.type === 'info'">
                            <svg class="w-5 h-5 text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                        </template>
                    </div>
                    <div class="flex-1 min-w-0">
                        <p class="text-sm font-semibold leading-tight"
                           :class="{
                               'text-red-800 dark:text-red-200': toast.type === 'error',
                               'text-green-800 dark:text-green-200': toast.type === 'success',
                               'text-amber-800 dark:text-amber-200': toast.type === 'warning',
                               'text-blue-800 dark:text-blue-200': toast.type === 'info',
                           }" x-text="toast.message"></p>
                    </div>
                    <button @click="removeToast(toast.id)" class="shrink-0 -mr-1 -mt-0.5 rounded-lg p-1 hover:bg-black/10 dark:hover:bg-white/10 transition-colors">
                        <svg class="w-4 h-4" :class="{
                            'text-red-400 dark:text-red-400': toast.type === 'error',
                            'text-green-400 dark:text-green-400': toast.type === 'success',
                            'text-amber-400 dark:text-amber-400': toast.type === 'warning',
                            'text-blue-400 dark:text-blue-400': toast.type === 'info',
                        }" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                    </button>
                </div>
                {{-- Progress bar countdown --}}
                <div class="h-1 w-full"
                     :class="{
                         'bg-red-100 dark:bg-red-900/50': toast.type === 'error',
                         'bg-green-100 dark:bg-green-900/50': toast.type === 'success',
                         'bg-amber-100 dark:bg-amber-900/50': toast.type === 'warning',
                         'bg-blue-100 dark:bg-blue-900/50': toast.type === 'info',
                     }">
                    <div class="h-full toast-progress-bar"
                         :class="{
                             'bg-red-500': toast.type === 'error',
                             'bg-green-500': toast.type === 'success',
                             'bg-amber-500': toast.type === 'warning',
                             'bg-blue-500': toast.type === 'info',
                         }"
                         :style="`animation-duration: ${toast.duration}ms`">
                    </div>
                </div>
            </div>
        </template>
    </div>
    <script>
        function toastSystem() {
            return {
                toasts: [],
                nextId: 0,
                addToast(detail) {
                    const id = this.nextId++;
                    const type = detail.type || 'info';
                    const message = detail.message || '';
                    const duration = detail.duration || (type === 'error' ? 8000 : type === 'warning' ? 6000 : 4000);
                    this.toasts.push({ id, type, message, duration, visible: true });
                    setTimeout(() => this.removeToast(id), duration);
                },
                removeToast(id) {
                    const t = this.toasts.find(t => t.id === id);
                    if (t) t.visible = false;
                    setTimeout(() => { this.toasts = this.toasts.filter(t => t.id !== id); }, 300);
                }
            };
        }
        window.$toast = function(type, message, duration) {
            window.dispatchEvent(new CustomEvent('toast', { detail: { type, message, duration } }));
        };
    </script>

    <div class="min-h-screen xl:flex">
        @include('layouts.backdrop')
        @include('layouts.sidebar')

        <div class="flex-1 min-w-0 transition-all duration-300 ease-in-out dark:bg-gray-900"
            :class="{
                'xl:ml-[290px]': $store.sidebar.isExpanded || $store.sidebar.isHovered,
                'xl:ml-[90px]': !$store.sidebar.isExpanded && !$store.sidebar.isHovered,
                'ml-0': $store.sidebar.isMobileOpen
            }">
            @include('layouts.app-header')
            <div class="p-4 mx-auto max-w-(--breakpoint-2xl) md:p-6 dark:bg-gray-900">
                @yield('content')
            </div>
        </div>
    </div>

    {{-- Admin push opt-in popup: system is enabled, ask if they want to receive push in this browser --}}
    @auth
    @php
        $vapidSet = !empty(App\Models\SystemSetting::get('notifications.vapid_public_key', ''));
        $pushConfigured = App\Models\SystemSetting::get('notifications.push_enabled', false) && $vapidSet;
        $userHasNoPushSub = auth()->user()->pushSubscriptions()->count() === 0;
        $showPushModal = auth()->user()->hasRole(['admin','super-admin']) && $pushConfigured && $userHasNoPushSub && !session('admin_push_onboarding_dismissed');
    @endphp
    @if($showPushModal)
    <div x-data="{ open: true, enabling: false }" x-show="open" x-cloak
        class="fixed inset-0 z-[99999] flex items-center justify-center p-4 bg-black/50"
        x-transition:enter="transition ease-out duration-200"
        x-transition:enter-start="opacity-0"
        x-transition:enter-end="opacity-100">
        <div class="w-full max-w-md rounded-2xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 p-6 shadow-xl"
            @click.self="open = false">
            <div class="flex items-start gap-4">
                <div class="flex h-12 w-12 shrink-0 items-center justify-center rounded-full bg-brand-100 dark:bg-brand-900/30">
                    <svg class="h-6 w-6 text-brand-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9" />
                    </svg>
                </div>
                <div class="flex-1 min-w-0">
                    <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Receive Push Notifications?</h3>
                    <p class="mt-2 text-sm text-gray-600 dark:text-gray-400">Push is enabled for the system. Enable in this browser to get instant alerts for new signups, tributes, and payments.</p>
                    <div class="mt-4 flex flex-wrap gap-3">
                        <button type="button" @click="
                            enabling = true;
                            if (typeof window.__subscribePush === 'function') {
                                window.__subscribePush().then(ok => {
                                    enabling = false;
                                    if (ok) { open = false; if (window.$toast) $toast('success', 'Push notifications enabled!'); }
                                    else { if (window.$toast) $toast('error', 'Permission denied.'); }
                                }).catch(e => {
                                    enabling = false;
                                    if (window.$toast) $toast('error', e.message || 'Failed to enable.');
                                });
                            } else {
                                enabling = false;
                                if (window.$toast) $toast('error', 'Please refresh the page and try again.');
                            }
                        " :disabled="enabling"
                            class="inline-flex items-center rounded-lg bg-brand-500 px-4 py-2 text-sm font-medium text-white hover:bg-brand-600 disabled:opacity-50 transition">
                            <span x-show="enabling">Enabling...</span>
                            <span x-show="!enabling">Yes, Enable</span>
                        </button>
                        <button type="button" @click="
                            open = false;
                            fetch('{{ route('admin.dismiss-push-onboarding') }}', { method: 'POST', headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]')?.content, 'Accept': 'application/json' } });
                        "
                            class="inline-flex items-center rounded-lg border border-gray-300 dark:border-gray-600 px-4 py-2 text-sm font-medium text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700 transition">
                            No
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
    @endif
    @endauth
</body>

@stack('scripts')

@auth
<script>
(function() {
    const VAPID_PUBLIC_KEY = @json(App\Models\SystemSetting::get('notifications.vapid_public_key', ''));
    const PUSH_ENABLED = @json((bool) App\Models\SystemSetting::get('notifications.push_enabled', false));

    if (!PUSH_ENABLED || !VAPID_PUBLIC_KEY || !('serviceWorker' in navigator) || !('PushManager' in window)) {
        return;
    }

    function urlBase64ToUint8Array(base64String) {
        const padding = '='.repeat((4 - base64String.length % 4) % 4);
        const base64 = (base64String + padding).replace(/-/g, '+').replace(/_/g, '/');
        const rawData = window.atob(base64);
        const outputArray = new Uint8Array(rawData.length);
        for (let i = 0; i < rawData.length; ++i) {
            outputArray[i] = rawData.charCodeAt(i);
        }
        return outputArray;
    }

    async function syncSubscriptionToServer(subscription) {
        try {
            const subJson = subscription.toJSON();
            subJson.contentEncoding = (PushManager.supportedContentEncodings?.includes('aes128gcm')) ? 'aes128gcm' : 'aesgcm';
            await fetch('/notifications/push/subscribe', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content,
                    'Accept': 'application/json',
                },
                body: JSON.stringify(subJson),
            });
        } catch (e) {
            console.warn('Push sync failed:', e);
        }
    }

    async function initPush() {
        try {
            const registration = await navigator.serviceWorker.register('{{ asset("sw.js") }}');
            const existingSub = await registration.pushManager.getSubscription();
            if (existingSub) {
                await syncSubscriptionToServer(existingSub);
                return;
            }

            if (Notification.permission === 'denied') {
                return;
            }

            if (Notification.permission === 'default') {
                window.__pushRegistration = registration;
                return;
            }

            await subscribePush(registration);
        } catch (e) {
            console.warn('Push init failed:', e);
        }
    }

    async function subscribePush(registration) {
        try {
            const subscription = await registration.pushManager.subscribe({
                userVisibleOnly: true,
                applicationServerKey: urlBase64ToUint8Array(VAPID_PUBLIC_KEY),
            });

            const subJson = subscription.toJSON();
            subJson.contentEncoding = (PushManager.supportedContentEncodings?.includes('aes128gcm')) ? 'aes128gcm' : 'aesgcm';

            await fetch('/notifications/push/subscribe', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content,
                    'Accept': 'application/json',
                },
                body: JSON.stringify(subJson),
            });
        } catch (e) {
            console.warn('Push subscribe failed:', e);
        }
    }

    window.__subscribePush = async function() {
        const permission = await Notification.requestPermission();
        if (permission === 'granted') {
            const reg = window.__pushRegistration || await navigator.serviceWorker.ready;
            await subscribePush(reg);
            return true;
        }
        return false;
    };

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initPush);
    } else {
        initPush();
    }
})();
</script>
@endauth

</html>
