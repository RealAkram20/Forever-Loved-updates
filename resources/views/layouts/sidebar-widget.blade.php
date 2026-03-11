@php
    $isAdmin = \App\Helpers\MenuHelper::isAdmin();
@endphp

@if ($isAdmin)
    <div class="mx-auto mb-10 w-full max-w-60 rounded-2xl bg-gray-50 dark:bg-white/[0.03] px-4 py-5 text-center">
        <div class="mb-3 flex justify-center">
            <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-brand-500/10 text-brand-500">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.066 2.573c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.573 1.066c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.066-2.573c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
            </div>
        </div>
        <h3 class="mb-1 font-semibold text-gray-900 dark:text-white/90 text-sm">
            Admin Panel
        </h3>
        <p class="mb-4 text-gray-500 dark:text-gray-400 text-theme-xs">
            Manage settings, users, and plans.
        </p>
        <a href="{{ route('settings.general') }}"
            class="flex items-center justify-center p-2.5 font-medium text-white rounded-lg bg-brand-500 text-theme-sm hover:bg-brand-600 transition">
            System Settings
        </a>
    </div>
@else
    <div class="mx-auto mb-10 w-full max-w-60 rounded-2xl bg-gray-50 dark:bg-white/[0.03] px-4 py-5 text-center">
        <div class="mb-3 flex justify-center">
            <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-brand-500/10 text-brand-500">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 4v16m8-8H4"/></svg>
            </div>
        </div>
        <h3 class="mb-1 font-semibold text-gray-900 dark:text-white/90 text-sm">
            Create a Memorial
        </h3>
        <p class="mb-4 text-gray-500 dark:text-gray-400 text-theme-xs">
            Honor and celebrate the life of someone you love.
        </p>
        <a href="{{ route('memorials.create') }}"
            class="flex items-center justify-center p-2.5 font-medium text-white rounded-lg bg-brand-500 text-theme-sm hover:bg-brand-600 transition">
            Get Started
        </a>
    </div>
@endif
