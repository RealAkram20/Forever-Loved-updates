@props(['currentStep' => 1])

@php
    $canAccessStep2 = !empty(session('memorial_signup')['first_name'] ?? null);
    $canAccessStep3 = auth()->check();
@endphp

<nav class="mb-8 flex items-center gap-2 rounded-lg border border-gray-200 bg-gray-50/50 p-1 dark:border-gray-700 dark:bg-gray-800/60" aria-label="Create memorial steps">
    <a href="{{ route('memorial.create.step1') }}"
        class="flex flex-1 items-center justify-center gap-2 rounded-md px-3 py-2 text-sm font-medium transition
            {{ $currentStep === 1 ? 'bg-white text-brand-600 shadow-theme-xs dark:bg-gray-900 dark:text-brand-400' : 'text-gray-600 hover:text-gray-800 dark:text-gray-400 dark:hover:text-gray-200' }}">
        <span class="flex h-6 w-6 items-center justify-center rounded-full {{ $currentStep === 1 ? 'bg-brand-500 text-white' : 'bg-gray-200 text-gray-600 dark:bg-gray-600 dark:text-gray-300' }}">1</span>
        <span class="hidden sm:inline">Details</span>
    </a>
    <a href="{{ $canAccessStep2 ? route('memorial.create.step2') : '#' }}"
        class="flex flex-1 items-center justify-center gap-2 rounded-md px-3 py-2 text-sm font-medium transition
            {{ $currentStep === 2 ? 'bg-white text-brand-600 shadow-theme-xs dark:bg-gray-900 dark:text-brand-400' : ($canAccessStep2 ? 'text-gray-600 hover:text-gray-800 dark:text-gray-400 dark:hover:text-gray-200' : 'cursor-not-allowed text-gray-400 dark:text-gray-600') }}"
        @if(!$canAccessStep2) aria-disabled="true" @endif>
        <span class="flex h-6 w-6 items-center justify-center rounded-full {{ $currentStep === 2 ? 'bg-brand-500 text-white' : ($canAccessStep2 ? 'bg-gray-200 text-gray-600 dark:bg-gray-600 dark:text-gray-300' : 'bg-gray-100 text-gray-400 dark:bg-gray-800 dark:text-gray-600') }}">2</span>
        <span class="hidden sm:inline">Account</span>
    </a>
    <a href="{{ $canAccessStep3 ? route('memorial.create.step3') : '#' }}"
        class="flex flex-1 items-center justify-center gap-2 rounded-md px-3 py-2 text-sm font-medium transition
            {{ $currentStep === 3 ? 'bg-white text-brand-600 shadow-theme-xs dark:bg-gray-900 dark:text-brand-400' : ($canAccessStep3 ? 'text-gray-600 hover:text-gray-800 dark:text-gray-400 dark:hover:text-gray-200' : 'cursor-not-allowed text-gray-400 dark:text-gray-600') }}"
        @if(!$canAccessStep3) aria-disabled="true" @endif>
        <span class="flex h-6 w-6 items-center justify-center rounded-full {{ $currentStep === 3 ? 'bg-brand-500 text-white' : ($canAccessStep3 ? 'bg-gray-200 text-gray-600 dark:bg-gray-600 dark:text-gray-300' : 'bg-gray-100 text-gray-400 dark:bg-gray-800 dark:text-gray-600') }}">3</span>
        <span class="hidden sm:inline">Plan</span>
    </a>
</nav>
