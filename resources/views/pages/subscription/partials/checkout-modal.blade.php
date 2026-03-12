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
    <div class="fixed inset-0 bg-gray-900/50 backdrop-blur-sm" @click="closeCheckout()"></div>

    {{-- Modal --}}
    <div class="relative w-full max-w-lg rounded-2xl bg-white dark:bg-gray-900 shadow-xl border border-gray-200 dark:border-gray-800 overflow-hidden"
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

        {{-- Step 1: Payment Method Selection --}}
        <div x-show="step === 'method'" class="p-6 space-y-5">
            <div class="rounded-xl bg-gray-50 dark:bg-gray-800/50 p-4">
                <p class="text-sm font-medium text-gray-800 dark:text-white/90" x-text="planName"></p>
                <p class="mt-1 text-lg font-bold text-brand-500" x-text="'$' + (planPrice?.toFixed(2) || '0') + ' / ' + (planInterval || '')"></p>
            </div>

            <div>
                <p class="mb-3 text-sm font-medium text-gray-700 dark:text-gray-300">Choose payment method</p>
                <div class="space-y-2">
                    <button type="button"
                        @click="selectMethod('mtn')"
                        :class="paymentMethod === 'mtn' ? 'border-brand-500 bg-brand-50 dark:bg-brand-500/10' : 'border-gray-200 dark:border-gray-700 hover:border-gray-300'"
                        class="flex w-full items-center gap-3 rounded-xl border-2 p-4 text-left transition">
                        <div class="flex h-10 w-10 items-center justify-center rounded-lg bg-yellow-100 dark:bg-yellow-900/30">
                            <span class="text-lg font-bold text-yellow-600 dark:text-yellow-400">M</span>
                        </div>
                        <div>
                            <p class="font-medium text-gray-800 dark:text-white/90">MTN Mobile Money</p>
                            <p class="text-xs text-gray-500 dark:text-gray-400">Pay with your MTN number</p>
                        </div>
                        <svg x-show="paymentMethod === 'mtn'" class="ml-auto h-5 w-5 text-brand-500" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/></svg>
                    </button>

                    <button type="button"
                        @click="selectMethod('airtel')"
                        :class="paymentMethod === 'airtel' ? 'border-brand-500 bg-brand-50 dark:bg-brand-500/10' : 'border-gray-200 dark:border-gray-700 hover:border-gray-300'"
                        class="flex w-full items-center gap-3 rounded-xl border-2 p-4 text-left transition">
                        <div class="flex h-10 w-10 items-center justify-center rounded-lg bg-red-100 dark:bg-red-900/30">
                            <span class="text-lg font-bold text-red-600 dark:text-red-400">A</span>
                        </div>
                        <div>
                            <p class="font-medium text-gray-800 dark:text-white/90">Airtel Money</p>
                            <p class="text-xs text-gray-500 dark:text-gray-400">Pay with your Airtel number</p>
                        </div>
                        <svg x-show="paymentMethod === 'airtel'" class="ml-auto h-5 w-5 text-brand-500" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/></svg>
                    </button>

                    <button type="button"
                        @click="selectMethod('card')"
                        :class="paymentMethod === 'card' ? 'border-brand-500 bg-brand-50 dark:bg-brand-500/10' : 'border-gray-200 dark:border-gray-700 hover:border-gray-300'"
                        class="flex w-full items-center gap-3 rounded-xl border-2 p-4 text-left transition">
                        <div class="flex h-10 w-10 items-center justify-center rounded-lg bg-blue-100 dark:bg-blue-900/30">
                            <svg class="h-5 w-5 text-blue-600 dark:text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"/></svg>
                        </div>
                        <div>
                            <p class="font-medium text-gray-800 dark:text-white/90">Card Payment</p>
                            <p class="text-xs text-gray-500 dark:text-gray-400">Visa, Mastercard</p>
                        </div>
                        <svg x-show="paymentMethod === 'card'" class="ml-auto h-5 w-5 text-brand-500" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/></svg>
                    </button>
                </div>
            </div>

            <p class="text-xs text-gray-500 dark:text-gray-400">You will be redirected to complete payment securely. No billing address required for mobile money.</p>

            <div x-show="error" class="rounded-lg bg-red-50 dark:bg-red-900/20 px-4 py-3 text-sm text-red-700 dark:text-red-400" x-text="error"></div>

            <button type="button"
                @click="proceedToPay()"
                :disabled="!paymentMethod || loading"
                class="w-full rounded-lg bg-brand-500 px-4 py-3 text-sm font-medium text-white hover:bg-brand-600 disabled:opacity-50 disabled:cursor-not-allowed transition">
                <span x-show="!loading">Continue to Payment</span>
                <span x-show="loading" class="inline-flex items-center gap-2">
                    <svg class="animate-spin h-4 w-4" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
                    Processing...
                </span>
            </button>
        </div>

        {{-- Step 2: Pesapal iframe --}}
        <div x-show="step === 'processing'" class="p-6">
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
