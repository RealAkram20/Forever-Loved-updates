@extends('layouts.fullscreen-layout')

@section('content')
<div class="relative z-1 bg-white p-6 sm:p-0 dark:bg-gray-900">
    <div class="relative flex min-h-screen w-full flex-col items-center justify-center py-12 sm:p-0 dark:bg-gray-900">
        <div class="mx-auto w-full max-w-md px-6">
            @if ($result === 'success')
                <div class="mb-6 flex justify-center">
                    <div class="flex h-16 w-16 items-center justify-center rounded-full bg-green-100 dark:bg-green-900/30">
                        <svg class="h-8 w-8 text-green-600 dark:text-green-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                        </svg>
                    </div>
                </div>
                <h1 class="text-center text-xl font-semibold text-gray-800 dark:text-white/90">Payment Successful</h1>
            @elseif ($result === 'error')
                <div class="mb-6 flex justify-center">
                    <div class="flex h-16 w-16 items-center justify-center rounded-full bg-red-100 dark:bg-red-900/30">
                        <svg class="h-8 w-8 text-red-600 dark:text-red-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                        </svg>
                    </div>
                </div>
                <h1 class="text-center text-xl font-semibold text-gray-800 dark:text-white/90">Payment Failed</h1>
            @elseif ($result === 'cancelled')
                <div class="mb-6 flex justify-center">
                    <div class="flex h-16 w-16 items-center justify-center rounded-full bg-amber-100 dark:bg-amber-900/30">
                        <svg class="h-8 w-8 text-amber-600 dark:text-amber-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                        </svg>
                    </div>
                </div>
                <h1 class="text-center text-xl font-semibold text-gray-800 dark:text-white/90">Payment Cancelled</h1>
            @else
                <div class="mb-6 flex justify-center">
                    <div class="flex h-16 w-16 items-center justify-center rounded-full bg-blue-100 dark:bg-blue-900/30">
                        <svg class="h-8 w-8 animate-spin text-blue-600 dark:text-blue-400" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>
                        </svg>
                    </div>
                </div>
                <h1 class="text-center text-xl font-semibold text-gray-800 dark:text-white/90">Processing</h1>
            @endif

            <p class="mt-4 text-center text-sm text-gray-600 dark:text-gray-400">{{ $message }}</p>

            <div class="mt-8 flex flex-col gap-3">
                <a href="{{ $redirect_url }}"
                    class="inline-flex w-full items-center justify-center rounded-lg bg-brand-500 px-4 py-3 text-sm font-medium text-white hover:bg-brand-600 transition">
                    {{ $redirect_label }}
                </a>
                <a href="{{ route('home') }}"
                    class="inline-flex w-full items-center justify-center rounded-lg border border-gray-300 dark:border-gray-700 px-4 py-3 text-sm font-medium text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-800 transition">
                    Back to Home
                </a>
            </div>
        </div>
    </div>
</div>

{{-- Notify parent window when in iframe (payment popup) --}}
<script>
(function() {
    if (window.self !== window.top) {
        try {
            window.parent.postMessage({
                type: 'pesapal_payment_complete',
                result: '{{ $result }}',
                message: '{{ addslashes($message) }}',
                redirect_url: '{{ addslashes($redirect_url ?? '') }}'
            }, '*');
        } catch (e) {}
    }
})();
</script>
@endsection
