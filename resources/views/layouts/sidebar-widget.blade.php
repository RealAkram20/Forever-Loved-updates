@php
    $isAdmin = \App\Helpers\MenuHelper::isAdmin();
    $support = config('support');
@endphp

@if ($isAdmin)
    {{-- Admin: Keep original Purchase Plan widget --}}
    <div class="mx-auto mb-10 w-full max-w-60 rounded-2xl bg-gray-50 dark:bg-white/[0.03] px-4 py-5 text-center">
        <h3 class="mb-2 font-semibold text-gray-900 dark:text-white/90">
            #1 Tailwind CSS Dashboard
        </h3>
        <p class="mb-4 text-gray-500 dark:text-gray-400 text-theme-sm">
            Leading Tailwind CSS Admin Template with 500+ UI Component and Pages.
        </p>
        <a href="https://tailadmin.com/pricing" target="_blank" rel="nofollow"
            class="flex items-center justify-center p-3 font-medium text-white rounded-lg bg-brand-500 text-theme-sm hover:bg-brand-600">
            Purchase Plan
        </a>
    </div>
@else
    {{-- User: Support information --}}
    <div class="mx-auto mb-10 w-full max-w-60 rounded-2xl bg-gray-50 dark:bg-white/[0.03] px-4 py-5">
        <h3 class="mb-3 font-semibold text-gray-900 dark:text-white text-center">Support</h3>
        <div class="space-y-2 text-sm text-gray-600 dark:text-gray-400">
            <a href="tel:{{ preg_replace('/[^0-9+]/', '', $support['phone']) }}" class="flex items-center gap-2 hover:text-brand-600">
                <svg class="size-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"/></svg>
                {{ $support['phone'] }}
            </a>
            <a href="mailto:{{ $support['email'] }}" class="flex items-center gap-2 hover:text-brand-600">
                <svg class="size-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
                {{ $support['email'] }}
            </a>
            <p class="flex items-center gap-2">
                <svg class="size-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                {{ $support['working_hours'] }}
            </p>
        </div>
        <a href="{{ $support['live_chat_url'] }}" target="_blank" rel="noopener"
            class="mt-4 flex items-center justify-center gap-2 p-3 font-medium text-white rounded-lg bg-brand-500 text-theme-sm hover:bg-brand-600">
            <svg class="size-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"/></svg>
            Live Chat
        </a>
    </div>
@endif
