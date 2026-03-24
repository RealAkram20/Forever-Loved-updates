@extends('layouts.fullscreen-layout')

@section('content')
@php
    $paidPlans = $plans->filter(fn($p) => !$p->isFree());
    $hasPaidPlans = $paidPlans->isNotEmpty();
@endphp
<div class="relative z-1 bg-white p-6 sm:p-0" x-data="step3Checkout({{ json_encode($plans->keyBy('id')->map(fn($p) => ['id' => $p->id, 'name' => $p->name, 'price' => (float) $p->price, 'interval' => $p->interval, 'is_free' => $p->isFree()])->toArray()) }}, {{ $pesapalEnabled ? 'true' : 'false' }}, {{ $paymentsEnabled ? 'true' : 'false' }})">
    <div class="relative flex min-h-screen w-full flex-col justify-center py-12 sm:p-0">
        <div class="flex w-full flex-1 flex-col">
            <div class="mx-auto w-full max-w-2xl px-6 pt-10 lg:px-12">
                <x-memorial-signup.step-tabs :currentStep="3" />
                <a href="{{ auth()->user() ? route('memorial.create.step1') : route('memorial.create.step2') }}" class="inline-flex items-center text-sm text-gray-500 hover:text-gray-700">
                    <svg class="stroke-current" xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 20 20" fill="none">
                        <path d="M12.7083 5L7.5 10.2083L12.7083 15.4167" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" />
                    </svg>
                    Back
                </a>
                <div class="mt-8">
                    <div class="mb-6 flex items-center gap-2">
                        <span class="rounded-full bg-brand-500 px-3 py-1 text-xs font-medium text-white">Step 3 of 3</span>
                        <span class="text-sm text-gray-500">Choose plan</span>
                    </div>
                    <h1 class="text-title-sm sm:text-title-md mb-2 font-semibold text-gray-800">Choose your plan</h1>
                    <p class="mb-6 text-sm text-gray-500">Select a plan that fits your needs. You can change this later.</p>

                    <form id="step3-plan-form" method="POST" action="{{ route('memorial.create.storeStep3') }}" class="space-y-4" @submit="handleSubmit($event)" @change="if ($event.target.name === 'plan_id') planSelectionChanged = $event.target.value">
                        @csrf
                        @foreach ($plans as $plan)
                            <label class="block cursor-pointer">
                                <input type="radio" name="plan_id" value="{{ $plan->id }}" {{ old('plan_id', $data['plan_id'] ?? '') == $plan->id ? 'checked' : '' }}
                                    class="peer sr-only" />
                                <div class="rounded-lg border-2 border-gray-200 p-4 transition peer-checked:border-brand-500 peer-checked:bg-brand-50/50 hover:border-gray-300">
                                    <div class="flex items-center justify-between">
                                        <div>
                                            <p class="font-medium text-gray-900">{{ $plan->name }}</p>
                                            <p class="text-sm text-gray-600">{{ $plan->description }}</p>
                                            <p class="mt-1 text-sm text-gray-500">
                                                {{ $plan->memorial_limit }} memorial(s) · {{ $plan->storage_limit_mb }} MB storage
                                            </p>
                                        </div>
                                        <div class="text-right">
                                            @if ($plan->isFree())
                                                <p class="text-lg font-semibold text-gray-900">Free</p>
                                            @else
                                                <p class="text-lg font-semibold text-gray-900">{{ $currency ?? 'USD' }} {{ number_format($plan->price, 2) }}</p>
                                                <p class="text-xs text-gray-500">/{{ $plan->interval }}</p>
                                            @endif
                                        </div>
                                    </div>
                                </div>
                            </label>
                        @endforeach
                        @error('plan_id')
                            <p class="text-sm text-red-600">{{ $message }}</p>
                        @enderror

                        {{-- Payment gateway selector (only when paid plan selected and payments enabled) --}}
                        @if ($hasPaidPlans && $paymentsEnabled)
                            <div x-show="selectedPlanIsPaid()" x-cloak class="rounded-lg border border-gray-100 bg-gray-50/50 p-4">
                                <label class="mb-2 block text-sm font-medium text-gray-700">Payment method</label>
                                <select x-model="gateway" class="h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 focus:border-brand-300 focus:ring-3 focus:ring-brand-500/10 focus:outline-hidden">
                                    @if ($pesapalEnabled)
                                        <option value="pesapal">Pesapal (Mobile Money, Card)</option>
                                    @endif
                                    <option value="manual">Manual (Admin will process)</option>
                                </select>
                                <p class="mt-3 text-xs text-gray-500">Pesapal: pay now. Manual: admin will process your request shortly.</p>
                            </div>
                        @endif

                        <div x-show="error" class="rounded-lg bg-red-50 dark:bg-red-900/20 px-4 py-3 text-sm text-red-700 dark:text-red-400" x-text="error" x-cloak></div>
                        <button type="submit" class="w-full rounded-lg bg-brand-500 px-4 py-3 text-sm font-medium text-white hover:bg-brand-600" :disabled="submitting">
                            <span x-show="!submitting">Continue</span>
                            <span x-show="submitting" class="inline-flex items-center gap-2">
                                <svg class="animate-spin h-4 w-4" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
                                Creating memorial...
                            </span>
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    {{-- Pesapal modal: opened only after API returns redirect_url (same as subscription) --}}
    <div x-show="pesapalOpen" x-cloak
        class="fixed inset-0 z-[99999] flex items-center justify-center p-4"
        x-transition:enter="transition ease-out duration-200"
        x-transition:enter-start="opacity-0"
        x-transition:enter-end="opacity-100">
        <div class="fixed inset-0 bg-gray-900/50 backdrop-blur-sm" @click="closePesapal()"></div>
        <div class="relative w-full max-w-lg rounded-2xl bg-white dark:bg-gray-900 shadow-xl border border-gray-200 dark:border-gray-800 overflow-hidden"
            @click.stop>
            <div class="flex items-center justify-between px-6 py-4 border-b border-gray-200 dark:border-gray-800">
                <h3 class="text-lg font-semibold text-gray-800 dark:text-white/90">Complete Payment (Pesapal)</h3>
                <button type="button" @click="closePesapal()"
                    class="rounded-lg p-2 text-gray-500 hover:bg-gray-100 dark:hover:bg-gray-800 transition">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
            </div>
            <div class="p-6">
                <div x-show="pesapalError" class="mb-4 rounded-lg bg-red-50 dark:bg-red-900/20 px-4 py-3 text-sm text-red-700 dark:text-red-400" x-text="pesapalError"></div>
                <div class="rounded-xl border border-gray-200 dark:border-gray-700 overflow-hidden bg-gray-50 dark:bg-gray-800/50" style="min-height: 450px;">
                    <iframe id="step3-pesapal-iframe" class="hidden w-full border-0" style="height: 500px;" title="Pesapal Payment"></iframe>
                    <div id="step3-pesapal-loading" class="flex flex-col items-center justify-center py-20 text-gray-500 dark:text-gray-400">
                        <svg class="animate-spin h-10 w-10 text-brand-500 mb-4" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
                        <p class="text-sm">Redirecting to payment...</p>
                    </div>
                </div>
                <p class="mt-3 text-xs text-gray-500 dark:text-gray-400">Pay by card or mobile money. You will be redirected when complete.</p>
            </div>
        </div>
    </div>
</div>

<style>[x-cloak]{display:none!important}</style>
@endsection

@push('scripts')
<script>
function step3Checkout(plansData, pesapalEnabled, paymentsEnabled) {
    const plans = plansData || {};
    return {
        gateway: pesapalEnabled ? 'pesapal' : 'manual',
        checkoutOpen: false,
        pesapalOpen: false,
        pesapalError: null,
        selectedPlan: null,
        memorialSlug: null,
        loading: false,
        submitting: false,
        error: null,
        plans,
        pesapalEnabled: !!pesapalEnabled,
        paymentsEnabled: !!paymentsEnabled,
        planSelectionChanged: null,

        init() {
            this.$nextTick(() => {
                const checked = document.querySelector('input[name="plan_id"]:checked');
                if (checked) this.planSelectionChanged = checked.value;
            });
            window.addEventListener('message', (e) => {
                if (e.data?.type === 'pesapal_payment_complete') {
                    this.pesapalOpen = false;
                    this.loading = false;
                    this.error = null;
                    if (e.data?.redirect_url) {
                        window.location.href = e.data.redirect_url;
                    } else if (e.data?.result === 'success' && this.memorialSlug) {
                        window.location.href = '{{ route("memorial.create.preparing", ["slug" => "___SLUG___"]) }}'.replace('___SLUG___', encodeURIComponent(this.memorialSlug));
                    }
                }
            });
        },

        selectedPlanIsPaid() {
            const planId = parseInt(this.planSelectionChanged || document.querySelector('input[name="plan_id"]:checked')?.value || 0, 10);
            const plan = this.plans[planId];
            return plan && !plan.is_free;
        },

        handleSubmit(e) {
            const planId = parseInt(document.querySelector('input[name="plan_id"]:checked')?.value || 0, 10);
            const plan = this.plans[planId];
            if (!plan) return;
            if (plan.is_free) {
                e.preventDefault();
                this.submitting = true;
                const form = e.target;
                requestAnimationFrame(() => form.submit());
                return;
            }
            e.preventDefault();
            this.prepareAndOpenCheckout(planId);
        },

        async prepareAndOpenCheckout(planId) {
            this.submitting = true;
            this.error = null;
            try {
                const form = document.getElementById('step3-plan-form');
                if (!form) {
                    this.error = 'Form not found. Please refresh the page.';
                    return;
                }
                const formData = new FormData(form);
                formData.set('plan_id', planId);

                const res = await fetch('{{ route("memorial.create.preparePaidCheckout") }}', {
                    method: 'POST',
                    body: formData,
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest',
                        'Accept': 'application/json',
                    },
                    credentials: 'same-origin',
                });

                let data;
                const contentType = res.headers.get('content-type');
                if (contentType && contentType.includes('application/json')) {
                    data = await res.json();
                } else {
                    const text = await res.text();
                    if (res.status === 419) {
                        this.error = 'Session expired. Please refresh the page and try again.';
                    } else if (res.status >= 500) {
                        this.error = 'Server error. Please try again later.';
                    } else {
                        this.error = 'Something went wrong. Please try again.';
                    }
                    return;
                }

                if (data.success && data.plan) {
                    this.memorialSlug = data.memorial_slug;
                    this.selectedPlan = data.plan.id;

                    if (this.gateway === 'manual') {
                        await this.submitManualPayment();
                    } else {
                        await this.submitPesapalPayment();
                    }
                } else {
                    this.error = data.error || 'Something went wrong.';
                }
            } catch (err) {
                this.error = err.message || 'Network error. Please check your connection and try again.';
            } finally {
                this.submitting = false;
            }
        },

        async submitManualPayment() {
            this.error = null;
            try {
                const formData = new FormData();
                formData.append('plan_id', this.selectedPlan);
                formData.append('payment_gateway', 'manual');
                formData.append('from_signup', '1');
                formData.append('memorial_slug', this.memorialSlug || '');
                formData.append('_token', '{{ csrf_token() }}');

                const res = await fetch('{{ route("payment.create-order") }}', {
                    method: 'POST',
                    body: formData,
                    credentials: 'same-origin',
                    headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' },
                });

                const contentType = res.headers.get('content-type');
                let data;
                if (contentType && contentType.includes('application/json')) {
                    data = await res.json();
                } else {
                    this.error = 'Request failed. Please try again.';
                    return;
                }

                if (data.success && data.reload) {
                    if (window.$toast) window.$toast('success', data.message || 'Payment request submitted.');
                    window.location.href = '{{ route("memorial.create.preparing", ["slug" => "___SLUG___"]) }}'.replace('___SLUG___', encodeURIComponent(this.memorialSlug));
                } else {
                    this.error = data.error || data.message || 'Payment failed. Please try again.';
                }
            } catch (e) {
                this.error = 'Network error. Please try again.';
            }
        },

        async submitPesapalPayment() {
            this.error = null;
            this.pesapalError = null;
            try {
                const formData = new FormData();
                formData.append('plan_id', this.selectedPlan);
                formData.append('payment_gateway', 'pesapal');
                formData.append('from_signup', '1');
                formData.append('memorial_slug', this.memorialSlug || '');
                formData.append('_token', '{{ csrf_token() }}');

                const res = await fetch('{{ route("payment.create-order") }}', {
                    method: 'POST',
                    body: formData,
                    credentials: 'same-origin',
                    headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' },
                });

                const contentType = res.headers.get('content-type');
                let data;
                if (contentType && contentType.includes('application/json')) {
                    data = await res.json();
                } else {
                    this.pesapalError = 'Request failed. Please try again.';
                    return;
                }

                if (data.success && data.redirect_url) {
                    this.pesapalOpen = true;
                    this.$nextTick(() => {
                        const iframe = document.getElementById('step3-pesapal-iframe');
                        const loading = document.getElementById('step3-pesapal-loading');
                        if (iframe) { iframe.src = data.redirect_url; iframe.classList.remove('hidden'); }
                        if (loading) loading.classList.add('hidden');
                        if (window.$toast) window.$toast('success', data.message || 'Payment popup opened.');
                    });
                } else {
                    this.pesapalError = data.error || data.message || 'Payment failed. Please try again.';
                }
            } catch (e) {
                this.pesapalError = 'Network error. Please try again.';
            }
        },

        closePesapal() {
            this.pesapalOpen = false;
            const iframe = document.getElementById('step3-pesapal-iframe');
            const loading = document.getElementById('step3-pesapal-loading');
            if (iframe) { iframe.src = ''; iframe.classList.add('hidden'); }
            if (loading) loading.classList.remove('hidden');
            if (this.memorialSlug && this.selectedPlan) {
                window.location.href = '{{ route("subscription.index") }}?from_signup=1&plan_id=' + this.selectedPlan + '&memorial_slug=' + encodeURIComponent(this.memorialSlug);
            }
        },
    };
}
</script>
@endpush
