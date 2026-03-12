@extends('layouts.fullscreen-layout')

@section('content')
<div class="relative z-1 bg-white p-6 sm:p-0" x-data="step3Checkout({{ json_encode($plans->keyBy('id')->map(fn($p) => ['id' => $p->id, 'name' => $p->name, 'price' => (float) $p->price, 'interval' => $p->interval, 'is_free' => $p->isFree()])->toArray()) }})" x-init="try { localStorage.removeItem('memorial_signup_step1'); localStorage.removeItem('memorial_signup_step2'); } catch(e) {}">
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

                    <form id="step3-plan-form" method="POST" action="{{ route('memorial.create.storeStep3') }}" class="space-y-4" @submit="handleSubmit($event)">
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
                        <div x-show="error" class="rounded-lg bg-red-50 dark:bg-red-900/20 px-4 py-3 text-sm text-red-700 dark:text-red-400" x-text="error"></div>
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

    {{-- Checkout Modal (for paid plans) --}}
    @include('pages.subscription.partials.checkout-modal')
</div>

@push('scripts')
<script>
function step3Checkout(plansData) {
    const plans = plansData || {};
    return {
        checkoutOpen: false,
        selectedPlan: null,
        planName: '',
        planPrice: 0,
        planInterval: '',
        loading: false,
        submitting: false,
        error: null,
        memorialSlug: null,

        handleSubmit(e) {
            const planId = parseInt(document.querySelector('input[name="plan_id"]:checked')?.value || 0, 10);
            const plan = plans[planId];
            if (!plan) return;
            if (plan.is_free) return; // Let form submit normally for free plan
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
                    this.openCheckout(data.plan.id, data.plan.name, data.plan.price, data.plan.interval);
                } else {
                    this.error = data.error || 'Something went wrong.';
                }
            } catch (err) {
                this.error = err.message || 'Network error. Please check your connection and try again.';
            } finally {
                this.submitting = false;
            }
        },

        openCheckout(planId, planName, planPrice, planInterval) {
            this.selectedPlan = planId;
            this.planName = planName;
            this.planPrice = planPrice;
            this.planInterval = planInterval;
            this.error = null;
            this.loading = true;
            this.checkoutOpen = true;
            const iframe = document.getElementById('pesapal-checkout-iframe');
            const placeholder = document.getElementById('pesapal-loading-placeholder');
            if (iframe) { iframe.src = ''; iframe.classList.add('hidden'); }
            if (placeholder) placeholder.classList.remove('hidden');
            this.proceedToPay();
        },

        init() {
            window.addEventListener('message', (e) => {
                if (e.data?.type === 'pesapal_payment_complete') {
                    this.checkoutOpen = false;
                    this.loading = false;
                    this.error = null;
                    if (e.data?.redirect_url) {
                        window.location.href = e.data.redirect_url;
                    }
                }
            });
        },
        closeCheckout() {
            if (!this.loading) {
                this.checkoutOpen = false;
                this.error = null;
                if (this.memorialSlug && this.selectedPlan) {
                    window.location.href = '{{ route("subscription.index") }}?from_signup=1&plan_id=' + this.selectedPlan + '&memorial_slug=' + encodeURIComponent(this.memorialSlug);
                }
            }
        },

        async proceedToPay() {
            if (!this.selectedPlan) return;
            this.loading = true;
            this.error = null;

            try {
                const formData = new FormData();
                formData.append('plan_id', this.selectedPlan);
                formData.append('from_signup', '1');
                formData.append('memorial_slug', this.memorialSlug || '');
                formData.append('_token', '{{ csrf_token() }}');

                const res = await fetch('{{ route("payment.create-order") }}', {
                    method: 'POST',
                    body: formData,
                    credentials: 'same-origin',
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest',
                        'Accept': 'application/json',
                    },
                });

                let data;
                const contentType = res.headers.get('content-type');
                if (contentType && contentType.includes('application/json')) {
                    data = await res.json();
                } else {
                    if (res.status === 401) this.error = 'Session expired. Please refresh and log in again.';
                    else if (res.status === 403) this.error = 'Access denied.';
                    else if (res.status >= 500) this.error = 'Server error. Please try again later.';
                    else this.error = 'Request failed. Please try again.';
                    return;
                }

                if (data.success && data.redirect_url) {
                    const iframe = document.getElementById('pesapal-checkout-iframe');
                    const placeholder = document.getElementById('pesapal-loading-placeholder');
                    if (iframe) {
                        iframe.src = data.redirect_url;
                        iframe.classList.remove('hidden');
                    }
                    if (placeholder) placeholder.classList.add('hidden');
                } else {
                    const err = data.error || data.message || (data.errors && Object.values(data.errors).flat()[0]) || 'Payment failed. Please try again or contact support.';
                    this.error = typeof err === 'string' ? err : 'Payment failed.';
                }
            } catch (e) {
                this.error = 'Network error. Please try again.';
            } finally {
                this.loading = false;
            }
        }
    };
}
</script>
@endpush
@endsection
