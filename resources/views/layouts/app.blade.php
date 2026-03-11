<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ $title ?? 'Dashboard' }} | {{ config('app.name') }}</title>

    <!-- Scripts -->
    @vite(['resources/css/app.css', 'resources/js/app.js'])
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
</body>

@stack('scripts')

</html>
