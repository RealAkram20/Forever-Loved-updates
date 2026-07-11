<div x-show="checkoutOpen"
    x-cloak
    class="fixed inset-0 z-[99999] flex items-center justify-center p-4"
    x-transition:enter="transition ease-out duration-200"
    x-transition:enter-start="opacity-0"
    x-transition:enter-end="opacity-100"
    x-transition:leave="transition ease-in duration-150"
    x-transition:leave-start="opacity-100"
    x-transition:leave-end="opacity-0">
    {{-- Backdrop --}}
    <div class="fixed inset-0 bg-gray-900/60 backdrop-blur-sm" @click="closeCheckout()"></div>

    {{-- Modal --}}
    <div class="relative w-full max-w-lg rounded-2xl bg-white dark:bg-gray-900 shadow-2xl border border-gray-200 dark:border-gray-800 overflow-hidden"
        @click.stop
        x-show="checkoutOpen"
        x-transition:enter="transition ease-out duration-200"
        x-transition:enter-start="opacity-0 scale-95"
        x-transition:enter-end="opacity-100 scale-100"
        x-transition:leave="transition ease-in duration-150"
        x-transition:leave-start="opacity-100 scale-100"
        x-transition:leave-end="opacity-0 scale-95">

        {{-- Header --}}
        <div class="flex items-center justify-between px-6 py-4 border-b border-gray-200 dark:border-gray-800">
            <h3 class="text-lg font-semibold text-gray-800 dark:text-white/90">Complete Payment</h3>
            <button type="button" @click="closeCheckout()" x-show="!loading"
                class="rounded-lg p-2 text-gray-500 hover:bg-gray-100 dark:hover:bg-gray-800 hover:text-gray-700 dark:hover:text-gray-300 transition">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
            </button>
        </div>

        {{-- Memorial selection (when not from signup) --}}
        <div x-show="needsMemorialSelection" class="p-6 space-y-4">
            <p class="text-sm text-gray-600 dark:text-gray-400">Select which memorial to upgrade with this plan:</p>
            <select x-model="selectedMemorialId" class="h-11 w-full rounded-lg border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-800 px-4 text-sm text-gray-800 dark:text-white/90 focus:border-brand-300 focus:outline-hidden">
                <option value="">Choose a memorial...</option>
                @foreach ($memorials ?? [] as $m)
                    <option value="{{ $m->id }}">{{ $m->full_name }}</option>
                @endforeach
            </select>
            <p x-show="memorials.length === 0" class="text-sm text-amber-600 dark:text-amber-400">You need to create a memorial first. <a href="{{ route('memorials.create') }}" class="underline">Create memorial</a></p>
            <button type="button" @click="confirmMemorialAndPay()" :disabled="!selectedMemorialId"
                class="btn btn-primary btn-md btn-block w-full disabled:opacity-50">
                Continue to Payment
            </button>
        </div>

        {{-- Pesapal iframe (shown when memorial selected or from signup) --}}
        <div class="p-6 space-y-4" x-show="!needsMemorialSelection">
            <div x-show="error" class="rounded-lg bg-red-50 dark:bg-red-900/20 px-4 py-3 text-sm text-red-700 dark:text-red-400" x-text="error"></div>
            <div class="rounded-xl border border-gray-200 dark:border-gray-700 overflow-hidden bg-gray-50 dark:bg-gray-800/50" style="min-height: 450px;">
                <iframe id="pesapal-checkout-iframe" class="hidden w-full border-0" style="height: 500px;" title="Pesapal Payment"></iframe>
                <div id="pesapal-loading-placeholder" class="flex flex-col items-center justify-center py-20 text-gray-500 dark:text-gray-400">
                    <svg class="animate-spin h-10 w-10 text-brand-500 mb-4" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
                    <p class="text-sm">Redirecting to payment...</p>
                </div>
            </div>
        </div>
    </div>
</div>

<style>[x-cloak]{display:none!important}</style>
