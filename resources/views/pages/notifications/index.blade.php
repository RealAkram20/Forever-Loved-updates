@extends('layouts.app')

@section('content')
    <x-common.page-breadcrumb pageTitle="Notifications" />

    <div x-data="{
        notifications: @js($notifications->items()),
        markAsRead(id, el) {
            fetch(`/notifications/${id}/read`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]')?.content,
                    'Accept': 'application/json',
                }
            }).then(() => {
                const n = this.notifications.find(n => n.id === id);
                if (n) n.read_at = new Date().toISOString();
            });
        },
        markAllRead() {
            fetch('{{ route('notifications.mark-all-read') }}', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]')?.content,
                    'Accept': 'application/json',
                }
            }).then(() => {
                this.notifications.forEach(n => n.read_at = new Date().toISOString());
            });
        },
        deleteNotification(id) {
            fetch(`/notifications/${id}`, {
                method: 'DELETE',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]')?.content,
                    'Accept': 'application/json',
                }
            }).then(() => {
                this.notifications = this.notifications.filter(n => n.id !== id);
            });
        },
        handleClick(notification) {
            if (!notification.read_at) {
                this.markAsRead(notification.id);
            }
            if (notification.action_url) {
                window.location.href = notification.action_url;
            }
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
        },
        timeAgo(dateStr) {
            if (!dateStr) return '';
            const date = new Date(dateStr);
            const now = new Date();
            const seconds = Math.floor((now - date) / 1000);
            if (seconds < 60) return 'just now';
            const minutes = Math.floor(seconds / 60);
            if (minutes < 60) return minutes + 'm ago';
            const hours = Math.floor(minutes / 60);
            if (hours < 24) return hours + 'h ago';
            const days = Math.floor(hours / 24);
            if (days < 30) return days + 'd ago';
            return date.toLocaleDateString();
        }
    }">
        {{-- Header actions --}}
        <div class="mb-6 flex items-center justify-between">
            <div>
                <p class="text-sm text-gray-500 dark:text-gray-400">
                    <span x-text="notifications.filter(n => !n.read_at).length"></span> unread notifications
                </p>
            </div>
            <button
                @click="markAllRead()"
                x-show="notifications.some(n => !n.read_at)"
                class="btn btn-secondary btn-md"
            >
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                </svg>
                Mark All as Read
            </button>
        </div>

        {{-- Notifications list --}}
        <div class="rounded-2xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 overflow-hidden">
            <template x-if="notifications.length === 0">
                <div class="py-16 text-center">
                    <svg class="mx-auto mb-4 w-16 h-16 text-gray-300 dark:text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9" />
                    </svg>
                    <h3 class="text-lg font-medium text-gray-800 dark:text-white/90 mb-1">No notifications</h3>
                    <p class="text-sm text-gray-500 dark:text-gray-400">You're all caught up! Notifications will appear here when there's activity.</p>
                </div>
            </template>

            <template x-for="(notification, index) in notifications" :key="notification.id">
                <div
                    class="flex items-start gap-4 px-5 py-4 border-b border-gray-100 dark:border-gray-700/50 last:border-b-0 transition-all duration-200"
                    :class="{
                        'bg-brand-50/40 dark:bg-brand-900/10': !notification.read_at,
                        'hover:bg-gray-50 dark:hover:bg-gray-700/30': true
                    }"
                >
                    {{-- Icon --}}
                    <div
                        class="flex-shrink-0 flex items-center justify-center w-10 h-10 rounded-full cursor-pointer"
                        :class="getIconColor(notification.icon)"
                        x-html="getIconSvg(notification.icon)"
                        @click="handleClick(notification)"
                    ></div>

                    {{-- Content --}}
                    <div class="flex-1 min-w-0 cursor-pointer" @click="handleClick(notification)">
                        <p class="text-sm text-gray-800 dark:text-white/90" :class="{ 'font-semibold': !notification.read_at }">
                            <span x-text="notification.title"></span>
                        </p>
                        <p class="mt-0.5 text-sm text-gray-500 dark:text-gray-400 line-clamp-2" x-text="notification.message"></p>
                        <div class="mt-1.5 flex items-center gap-2">
                            <span class="text-xs text-gray-400 dark:text-gray-500" x-text="timeAgo(notification.created_at)"></span>
                            <span x-show="!notification.read_at" class="w-1.5 h-1.5 bg-brand-500 rounded-full"></span>
                        </div>
                    </div>

                    {{-- Actions --}}
                    <div class="flex-shrink-0 flex items-center gap-1">
                        <button
                            x-show="!notification.read_at"
                            @click.stop="markAsRead(notification.id)"
                            class="p-1.5 rounded-lg text-gray-400 hover:text-brand-500 hover:bg-gray-100 dark:hover:bg-gray-700 transition"
                            title="Mark as read"
                        >
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                            </svg>
                        </button>
                        <button
                            @click.stop="deleteNotification(notification.id)"
                            class="p-1.5 rounded-lg text-gray-400 hover:text-red-500 hover:bg-gray-100 dark:hover:bg-gray-700 transition"
                            title="Delete"
                        >
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                            </svg>
                        </button>
                    </div>
                </div>
            </template>
        </div>

        {{-- Pagination --}}
        @if ($notifications->hasPages())
            <div class="mt-6">
                {{ $notifications->links() }}
            </div>
        @endif
    </div>
@endsection
