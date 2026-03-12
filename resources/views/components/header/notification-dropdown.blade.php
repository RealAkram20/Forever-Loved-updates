{{-- Notification Dropdown Component --}}
@php
    $unreadCount = $unreadCount ?? 0;
    $notifications = $notifications ?? [];
@endphp
<div class="relative" x-data="{
    dropdownOpen: false,
    notifying: {{ $unreadCount > 0 ? 'true' : 'false' }},
    unreadCount: {{ $unreadCount }},
    notifications: @js($notifications),
    toggleDropdown() {
        this.dropdownOpen = !this.dropdownOpen;
        if (this.dropdownOpen) {
            this.refreshNotifications();
        }
    },
    closeDropdown() {
        this.dropdownOpen = false;
    },
    async refreshNotifications() {
        try {
            const res = await fetch('{{ route('notifications.dropdown') }}', {
                headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
            });
            const data = await res.json();
            this.notifications = data.notifications;
            this.unreadCount = data.unread_count;
            this.notifying = data.unread_count > 0;
        } catch (e) {}
    },
    async markAsRead(id) {
        try {
            await fetch(`/notifications/${id}/read`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]')?.content,
                    'Accept': 'application/json',
                }
            });
            const n = this.notifications.find(n => n.id === id);
            if (n) n.is_read = true;
            this.unreadCount = Math.max(0, this.unreadCount - 1);
            this.notifying = this.unreadCount > 0;
        } catch (e) {}
    },
    async markAllRead() {
        try {
            await fetch('{{ route('notifications.mark-all-read') }}', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]')?.content,
                    'Accept': 'application/json',
                }
            });
            this.notifications.forEach(n => n.is_read = true);
            this.unreadCount = 0;
            this.notifying = false;
        } catch (e) {}
    },
    handleItemClick(notification) {
        if (!notification.is_read) {
            this.markAsRead(notification.id);
        }
        if (notification.action_url) {
            window.location.href = notification.action_url;
        }
        this.closeDropdown();
    },
    getIconSvg(icon) {
        const icons = {
            user: `<svg class='w-5 h-5' fill='none' stroke='currentColor' viewBox='0 0 24 24'><path stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z'/></svg>`,
            payment: `<svg class='w-5 h-5' fill='none' stroke='currentColor' viewBox='0 0 24 24'><path stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z'/></svg>`,
            tribute: `<svg class='w-5 h-5' fill='none' stroke='currentColor' viewBox='0 0 24 24'><path stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z'/></svg>`,
            chapter: `<svg class='w-5 h-5' fill='none' stroke='currentColor' viewBox='0 0 24 24'><path stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253'/></svg>`,
            status: `<svg class='w-5 h-5' fill='none' stroke='currentColor' viewBox='0 0 24 24'><path stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z'/></svg>`,
            memorial: `<svg class='w-5 h-5' fill='none' stroke='currentColor' viewBox='0 0 24 24'><path stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253'/></svg>`,
            info: `<svg class='w-5 h-5' fill='none' stroke='currentColor' viewBox='0 0 24 24'><path stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z'/></svg>`,
        };
        return icons[icon] || icons.info;
    },
    getIconColor(icon) {
        const colors = {
            user: 'text-blue-500 bg-blue-50 dark:bg-blue-900/30',
            payment: 'text-green-500 bg-green-50 dark:bg-green-900/30',
            tribute: 'text-pink-500 bg-pink-50 dark:bg-pink-900/30',
            chapter: 'text-purple-500 bg-purple-50 dark:bg-purple-900/30',
            status: 'text-amber-500 bg-amber-50 dark:bg-amber-900/30',
            memorial: 'text-indigo-500 bg-indigo-50 dark:bg-indigo-900/30',
            info: 'text-gray-500 bg-gray-50 dark:bg-gray-700',
        };
        return colors[icon] || colors.info;
    }
}" @click.away="closeDropdown()">
    <!-- Notification Button -->
    <button
        class="relative flex items-center justify-center text-gray-500 dark:text-gray-400 transition-colors bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-full h-11 w-11 hover:bg-gray-100 dark:hover:bg-gray-700 hover:text-gray-700 dark:hover:text-gray-200"
        @click="toggleDropdown()"
        type="button"
    >
        <!-- Notification Badge -->
        <span
            x-show="notifying"
            x-cloak
            class="absolute right-0 top-0.5 z-1 flex items-center justify-center"
        >
            <span
                class="absolute inline-flex w-2 h-2 bg-orange-400 rounded-full opacity-75 animate-ping"
            ></span>
            <span class="relative inline-flex h-2 w-2 rounded-full bg-orange-400"></span>
        </span>

        <!-- Bell Icon -->
        <svg
            class="fill-current"
            width="20"
            height="20"
            viewBox="0 0 20 20"
            fill="none"
            xmlns="http://www.w3.org/2000/svg"
        >
            <path
                fill-rule="evenodd"
                clip-rule="evenodd"
                d="M10.75 2.29248C10.75 1.87827 10.4143 1.54248 10 1.54248C9.58583 1.54248 9.25004 1.87827 9.25004 2.29248V2.83613C6.08266 3.20733 3.62504 5.9004 3.62504 9.16748V14.4591H3.33337C2.91916 14.4591 2.58337 14.7949 2.58337 15.2091C2.58337 15.6234 2.91916 15.9591 3.33337 15.9591H4.37504H15.625H16.6667C17.0809 15.9591 17.4167 15.6234 17.4167 15.2091C17.4167 14.7949 17.0809 14.4591 16.6667 14.4591H16.375V9.16748C16.375 5.9004 13.9174 3.20733 10.75 2.83613V2.29248ZM14.875 14.4591V9.16748C14.875 6.47509 12.6924 4.29248 10 4.29248C7.30765 4.29248 5.12504 6.47509 5.12504 9.16748V14.4591H14.875ZM8.00004 17.7085C8.00004 18.1228 8.33583 18.4585 8.75004 18.4585H11.25C11.6643 18.4585 12 18.1228 12 17.7085C12 17.2943 11.6643 16.9585 11.25 16.9585H8.75004C8.33583 16.9585 8.00004 17.2943 8.00004 17.7085Z"
                fill=""
            />
        </svg>
    </button>

    <!-- Dropdown Start -->
    <div
        x-show="dropdownOpen"
        x-cloak
        x-transition:enter="transition ease-out duration-100"
        x-transition:enter-start="transform opacity-0 scale-95"
        x-transition:enter-end="transform opacity-100 scale-100"
        x-transition:leave="transition ease-in duration-75"
        x-transition:leave-start="transform opacity-100 scale-100"
        x-transition:leave-end="transform opacity-0 scale-95"
        class="absolute right-0 mt-[17px] flex max-h-[80vh] w-[calc(100vw-2rem)] max-w-[361px] flex-col rounded-2xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 p-3 shadow-theme-lg sm:w-[361px]"
        style="display: none;"
    >
        <!-- Dropdown Header -->
        <div class="flex items-center justify-between pb-3 mb-3 border-b border-gray-100 dark:border-gray-700">
            <div class="flex items-center gap-2">
                <h5 class="text-lg font-semibold text-gray-800 dark:text-white/90">Notifications</h5>
                <span
                    x-show="unreadCount > 0"
                    x-text="unreadCount"
                    class="inline-flex items-center justify-center h-5 min-w-5 px-1.5 rounded-full bg-brand-500 text-[11px] font-medium text-white"
                ></span>
            </div>

            <div class="flex items-center gap-1">
                <button
                    x-show="unreadCount > 0"
                    @click="markAllRead()"
                    class="text-xs text-brand-500 hover:text-brand-600 dark:text-brand-400 font-medium px-2 py-1 rounded hover:bg-gray-50 dark:hover:bg-gray-700"
                    type="button"
                >
                    Mark all read
                </button>
                <button @click="closeDropdown()" class="text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-200 p-1 rounded-lg hover:bg-gray-100 dark:hover:bg-gray-700" type="button">
                    <svg class="fill-current" width="20" height="20" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <path fill-rule="evenodd" clip-rule="evenodd" d="M6.21967 7.28131C5.92678 6.98841 5.92678 6.51354 6.21967 6.22065C6.51256 5.92775 6.98744 5.92775 7.28033 6.22065L11.999 10.9393L16.7176 6.22078C17.0105 5.92789 17.4854 5.92788 17.7782 6.22078C18.0711 6.51367 18.0711 6.98855 17.7782 7.28144L13.0597 12L17.7782 16.7186C18.0711 17.0115 18.0711 17.4863 17.7782 17.7792C17.4854 18.0721 17.0105 18.0721 16.7176 17.7792L11.999 13.0607L7.28033 17.7794C6.98744 18.0722 6.51256 18.0722 6.21967 17.7794C5.92678 17.4865 5.92678 17.0116 6.21967 16.7187L10.9384 12L6.21967 7.28131Z" fill="" />
                    </svg>
                </button>
            </div>
        </div>

        <!-- Notification List -->
        <ul class="flex flex-col h-auto overflow-y-auto custom-scrollbar">
            <template x-if="notifications.length === 0">
                <li class="py-8 text-center">
                    <svg class="mx-auto mb-3 w-10 h-10 text-gray-300 dark:text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9" />
                    </svg>
                    <p class="text-sm text-gray-500 dark:text-gray-400">No notifications yet</p>
                </li>
            </template>

            <template x-for="notification in notifications" :key="notification.id">
                <li @click="handleItemClick(notification)" class="cursor-pointer">
                    <div
                        class="flex gap-3 rounded-lg border-b border-gray-100 dark:border-gray-700 p-3 px-4.5 py-3 hover:bg-gray-100 dark:hover:bg-gray-700/50 transition-colors"
                        :class="{ 'bg-brand-50/50 dark:bg-brand-900/10': !notification.is_read }"
                    >
                        <span
                            class="relative flex-shrink-0 flex items-center justify-center w-10 h-10 rounded-full"
                            :class="getIconColor(notification.icon)"
                            x-html="getIconSvg(notification.icon)"
                        ></span>

                        <span class="block min-w-0 flex-1">
                            <span class="mb-1 block text-theme-sm text-gray-500 dark:text-gray-400">
                                <span
                                    class="font-medium text-gray-800 dark:text-white/90"
                                    :class="{ 'font-semibold': !notification.is_read }"
                                    x-text="notification.title"
                                ></span>
                            </span>
                            <span class="block text-theme-xs text-gray-500 dark:text-gray-400 line-clamp-2" x-text="notification.message"></span>
                            <span class="flex items-center gap-2 mt-1 text-gray-400 text-theme-xs">
                                <span x-text="notification.time"></span>
                                <span x-show="!notification.is_read" class="w-1.5 h-1.5 bg-brand-500 rounded-full flex-shrink-0"></span>
                            </span>
                        </span>
                    </div>
                </li>
            </template>
        </ul>

        <!-- Push Notification Prompt -->
        @if(App\Models\SystemSetting::get('notifications.push_enabled', false) && App\Models\SystemSetting::get('notifications.vapid_public_key'))
        <div x-data="{ showPrompt: false, subscribed: false }"
             x-init="
                if (!('Notification' in window) || !('serviceWorker' in navigator)) { showPrompt = false; }
                else if (Notification.permission === 'denied') { showPrompt = false; }
                else {
                    const done = (sub) => { showPrompt = !sub; subscribed = !!sub; };
                    navigator.serviceWorker.ready
                        .then(reg => reg.pushManager.getSubscription())
                        .then(done)
                        .catch(() => done(null));
                    setTimeout(() => { if (!subscribed && Notification.permission !== 'denied') showPrompt = true; }, 2500);
                }
             "
             x-show="showPrompt"
             x-cloak
             class="mt-2 rounded-lg border border-brand-200 dark:border-brand-800 bg-brand-50 dark:bg-brand-900/20 p-3">
            <div class="flex items-start gap-2">
                <svg class="w-4 h-4 text-brand-500 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9" />
                </svg>
                <div class="flex-1">
                    <p class="text-xs text-brand-700 dark:text-brand-300 mb-2">Get instant browser notifications for new activity.</p>
                    <button @click="if(window.__subscribePush) { window.__subscribePush().then(ok => { if(ok) { showPrompt = false; subscribed = true; } }); }"
                        type="button"
                        class="text-xs font-medium text-white bg-brand-500 hover:bg-brand-600 px-3 py-1.5 rounded-md transition">
                        Enable Push Notifications
                    </button>
                </div>
            </div>
        </div>
        @endif

        <!-- View All Button -->
        <a
            href="{{ route('notifications.index') }}"
            class="mt-3 flex justify-center rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 p-3 text-theme-sm font-medium text-gray-700 dark:text-gray-300 shadow-theme-xs hover:bg-gray-50 dark:hover:bg-gray-600 hover:text-gray-800 dark:hover:text-white transition"
            @click="closeDropdown()"
        >
            View All Notifications
        </a>
    </div>
    <!-- Dropdown End -->
</div>
