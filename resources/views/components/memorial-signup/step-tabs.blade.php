@props(['currentStep' => 1])

@php
    $canAccessStep2 = !empty(session('memorial_signup')['first_name'] ?? null);
    $canAccessStep3 = auth()->check();
@endphp

<nav class="mb-8 flex items-center gap-2 rounded-lg border border-gray-200 bg-gray-50/50 p-1" aria-label="Create memorial steps">
    <a href="{{ route('memorial.create.step1') }}"
        class="flex flex-1 items-center justify-center gap-2 rounded-md px-3 py-2 text-sm font-medium transition
            {{ $currentStep === 1 ? 'bg-white text-brand-600 shadow-theme-xs' : 'text-gray-600 hover:text-gray-800' }}">
        <span class="flex h-6 w-6 items-center justify-center rounded-full {{ $currentStep === 1 ? 'bg-brand-500 text-white' : 'bg-gray-200 text-gray-600' }}">1</span>
        <span class="hidden sm:inline">Details</span>
    </a>
    <a href="{{ $canAccessStep2 ? route('memorial.create.step2') : '#' }}"
        class="flex flex-1 items-center justify-center gap-2 rounded-md px-3 py-2 text-sm font-medium transition
            {{ $currentStep === 2 ? 'bg-white text-brand-600 shadow-theme-xs' : ($canAccessStep2 ? 'text-gray-600 hover:text-gray-800' : 'cursor-not-allowed text-gray-400') }}"
        @if(!$canAccessStep2) aria-disabled="true" @endif>
        <span class="flex h-6 w-6 items-center justify-center rounded-full {{ $currentStep === 2 ? 'bg-brand-500 text-white' : ($canAccessStep2 ? 'bg-gray-200 text-gray-600' : 'bg-gray-100 text-gray-400') }}">2</span>
        <span class="hidden sm:inline">Account</span>
    </a>
    <a href="{{ $canAccessStep3 ? route('memorial.create.step3') : '#' }}"
        class="flex flex-1 items-center justify-center gap-2 rounded-md px-3 py-2 text-sm font-medium transition
            {{ $currentStep === 3 ? 'bg-white text-brand-600 shadow-theme-xs' : ($canAccessStep3 ? 'text-gray-600 hover:text-gray-800' : 'cursor-not-allowed text-gray-400') }}"
        @if(!$canAccessStep3) aria-disabled="true" @endif>
        <span class="flex h-6 w-6 items-center justify-center rounded-full {{ $currentStep === 3 ? 'bg-brand-500 text-white' : ($canAccessStep3 ? 'bg-gray-200 text-gray-600' : 'bg-gray-100 text-gray-400') }}">3</span>
        <span class="hidden sm:inline">Plan</span>
    </a>
</nav>
