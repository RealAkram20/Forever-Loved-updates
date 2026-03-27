@extends('layouts.fullscreen-layout')

@section('content')
@php
    $paidPlans = $plans->filter(fn($p) => !$p->isFree());
    $hasPaidPlans = $paidPlans->isNotEmpty();
@endphp
<div class="relative z-1 bg-white dark:bg-gray-900 px-6 pt-6 pb-[max(8rem,env(safe-area-inset-bottom,0px)+5rem)] sm:px-0 sm:pt-10 sm:pb-[max(8rem,env(safe-area-inset-bottom,0px)+3rem)] lg:pb-40" x-data="step3Checkout({{ json_encode($plans->keyBy('id')->map(fn($p) => ['id' => $p->id, 'name' => $p->name, 'price' => (float) $p->price, 'interval' => $p->interval, 'is_free' => $p->isFree()])->toArray()) }}, {{ $pesapalEnabled ? 'true' : 'false' }}, {{ $paymentsEnabled ? 'true' : 'false' }})">
    <div class="relative flex min-h-screen w-full flex-col justify-start py-8 sm:py-12">
        <div class="flex w-full flex-1 flex-col">
            <div class="mx-auto w-full max-w-4xl px-0 pt-4 pb-12 sm:px-6 sm:pt-10 sm:pb-16 lg:px-12 lg:pb-20">
                <x-memorial-signup.step-tabs :currentStep="3" />
                <a href="{{ auth()->user() ? route('memorial.create.step1') : route('memorial.create.step2') }}" class="inline-flex items-center text-sm text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200">
                    <svg class="stroke-current" xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 20 20" fill="none">
                        <path d="M12.7083 5L7.5 10.2083L12.7083 15.4167" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" />
                    </svg>
                    Back
                </a>
                <div class="mt-8">
                    <div class="mb-6 flex items-center gap-2">
                        <span class="rounded-full bg-brand-500 px-3 py-1 text-xs font-medium text-white">Step 3 of 3</span>
                        <span class="text-sm text-gray-500 dark:text-gray-400">Choose plan</span>
                    </div>
                    <div class="text-center mb-8">
                        <h1 class="text-2xl sm:text-3xl font-bold text-gray-900 dark:text-white">Choose the Right Plan for You</h1>
                        <p class="mt-2 text-gray-500 dark:text-gray-400">Start free or unlock everything. You can upgrade anytime.</p>
                    </div>

                    <form id="step3-plan-form" method="POST" action="{{ route('memorial.create.storeStep3') }}" class="space-y-6" @submit="handleSubmit($event)" @change="if ($event.target.name === 'plan_id') planSelectionChanged = $event.target.value">
                        @csrf

                        <div class="grid gap-6 {{ $plans->count() >= 3 ? 'sm:grid-cols-3' : 'sm:grid-cols-2' }}">
                            @foreach ($plans as $plan)
                                @php
                                    $isFree = $plan->isFree();
                                    $isPopular = !$isFree && $plans->count() > 1;
                                @endphp
                                <label class="relative block cursor-pointer group">
                                    <input type="radio" name="plan_id" value="{{ $plan->id }}" {{ old('plan_id', $data['plan_id'] ?? '') == $plan->id ? 'checked' : '' }}
                                        class="peer sr-only" />

                                    <div class="relative rounded-2xl border-2 p-6 transition-all flex flex-col h-full bg-white dark:bg-gray-800/40
                                        {{ $isPopular ? 'border-brand-500 shadow-lg shadow-brand-500/10' : 'border-gray-200 dark:border-gray-700' }}
                                        peer-checked:border-brand-500 peer-checked:shadow-lg peer-checked:shadow-brand-500/10
                                        hover:border-gray-300 dark:hover:border-gray-600 peer-checked:hover:border-brand-500">

                                        @if ($isPopular)
                                            <div class="absolute -top-3 left-1/2 -translate-x-1/2 rounded-full bg-brand-500 px-4 py-1 text-xs font-semibold text-white shadow-sm">Most Popular</div>
                                        @endif

                                        {{-- Checkmark indicator --}}
                                        <div class="absolute top-4 right-4 hidden peer-checked:group-[]:flex h-6 w-6 items-center justify-center rounded-full bg-brand-500 text-white">
                                            <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"/></svg>
                                        </div>
                                        <div class="peer-checked:group-[]:hidden absolute top-4 right-4 h-6 w-6 rounded-full border-2 border-gray-300 dark:border-gray-600"></div>

                                        {{-- Plan name --}}
                                        <h3 class="text-lg font-semibold text-gray-900 dark:text-white">{{ $plan->name }}</h3>
                                        @if ($plan->description)
                                            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">{{ $plan->description }}</p>
                                        @endif

                                        {{-- Price --}}
                                        <div class="mt-4 mb-5">
                                            @if ($isFree)
                                                <div class="flex items-baseline gap-1">
                                                    <span class="text-4xl font-bold text-gray-900 dark:text-white">Free</span>
                                                </div>
                                                <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">No credit card required</p>
                                            @else
                                                <div class="flex items-baseline gap-1">
                                                    <span class="text-sm font-medium text-gray-500 dark:text-gray-400">{{ $currency ?? 'USD' }}</span>
                                                    <span class="text-4xl font-bold text-gray-900 dark:text-white">{{ number_format($plan->price, 2) }}</span>
                                                    <span class="text-sm text-gray-500 dark:text-gray-400">/{{ $plan->interval }}</span>
                                                </div>
                                            @endif
                                        </div>

                                        <hr class="border-gray-100 dark:border-gray-700 mb-5">

                                        {{-- Features --}}
                                        <ul class="space-y-2.5 flex-1 text-sm">
                                            <li class="flex items-center gap-2 text-gray-700 dark:text-gray-200">
                                                <svg class="h-4.5 w-4.5 shrink-0 text-green-500" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/></svg>
                                                {{ $plan->memorial_limit == 0 ? 'Unlimited' : $plan->memorial_limit }} {{ Str::plural('memorial', $plan->memorial_limit ?: 2) }}
                                            </li>
                                            <li class="flex items-center gap-2 text-gray-700 dark:text-gray-200">
                                                <svg class="h-4.5 w-4.5 shrink-0 text-green-500" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/></svg>
                                                {{ $plan->storage_limit_mb >= 1024 ? ($plan->storage_limit_mb / 1024) . ' GB' : $plan->storage_limit_mb . ' MB' }} storage
                                            </li>
                                            <li class="flex items-center gap-2 text-gray-700 dark:text-gray-200">
                                                <svg class="h-4.5 w-4.5 shrink-0 text-green-500" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/></svg>
                                                {{ $plan->max_gallery_images == 0 ? 'Unlimited' : $plan->max_gallery_images }} gallery {{ Str::plural('photo', $plan->max_gallery_images ?: 2) }}
                                            </li>
                                            <li class="flex items-center gap-2 text-gray-700 dark:text-gray-200">
                                                <svg class="h-4.5 w-4.5 shrink-0 text-green-500" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/></svg>
                                                {{ $plan->max_tributes == 0 ? 'Unlimited' : $plan->max_tributes }} {{ Str::plural('tribute', $plan->max_tributes ?: 2) }}
                                            </li>
                                            <li class="flex items-center gap-2 text-gray-700 dark:text-gray-200">
                                                <svg class="h-4.5 w-4.5 shrink-0 text-green-500" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/></svg>
                                                {{ $plan->max_chapters == 0 ? 'Unlimited' : $plan->max_chapters }} story {{ Str::plural('chapter', $plan->max_chapters ?: 2) }}
                                            </li>

                                            @if ($plan->max_ai_bio_per_day > 0)
                                                <li class="flex items-center gap-2 text-gray-700 dark:text-gray-200">
                                                    <svg class="h-4.5 w-4.5 shrink-0 text-green-500" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/></svg>
                                                    AI biography ({{ $plan->max_ai_bio_per_day }}/day)
                                                </li>
                                            @else
                                                <li class="flex items-center gap-2 text-gray-400 dark:text-gray-500">
                                                    <svg class="h-4.5 w-4.5 shrink-0" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"/></svg>
                                                    AI biography
                                                </li>
                                            @endif

                                            @foreach ([
                                                ['flag' => 'feature_background_music', 'label' => 'Background music'],
                                                ['flag' => 'feature_no_ads', 'label' => 'Ad-free experience'],
                                                ['flag' => 'feature_never_expires', 'label' => 'Never expires'],
                                                ['flag' => 'feature_share_memories', 'label' => 'Share memories'],
                                            ] as $feature)
                                                @if ($plan->{$feature['flag']})
                                                    <li class="flex items-center gap-2 text-gray-700 dark:text-gray-200">
                                                        <svg class="h-4.5 w-4.5 shrink-0 text-green-500" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/></svg>
                                                        {{ $feature['label'] }}
                                                    </li>
                                                @else
                                                    <li class="flex items-center gap-2 text-gray-400 dark:text-gray-500">
                                                        <svg class="h-4.5 w-4.5 shrink-0" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"/></svg>
                                                        {{ $feature['label'] }}
                                                    </li>
                                                @endif
                                            @endforeach
                                        </ul>

                                        {{-- Select indicator at bottom --}}
                                        <div class="mt-6 rounded-lg py-2.5 text-center text-sm font-semibold transition
                                            {{ $isPopular ? 'bg-brand-500 text-white group-has-[:checked]:bg-brand-600' : 'bg-gray-100 text-gray-700 dark:bg-gray-700 dark:text-gray-100 group-has-[:checked]:bg-brand-500 group-has-[:checked]:text-white' }}">
                                            <span class="group-has-[:checked]:hidden">{{ $isFree ? 'Start Free' : 'Select Plan' }}</span>
                                            <span class="hidden group-has-[:checked]:inline-flex items-center gap-1.5">
                                                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/></svg>
                                                Selected
                                            </span>
                                        </div>
                                    </div>
                                </label>
                            @endforeach
                        </div>

                        @error('plan_id')
                            <p class="text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                        @enderror

                        {{-- Payment gateway selector (only when paid plan selected and payments enabled) --}}
                        @if ($hasPaidPlans && $paymentsEnabled)
                            <div x-show="selectedPlanIsPaid()" x-cloak class="rounded-xl border border-gray-200 bg-gray-50/50 p-5 dark:border-gray-700 dark:bg-gray-800/50">
                                <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">Payment method</label>
                                <select x-model="gateway" class="h-11 w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-transparent px-4 py-2.5 text-sm text-gray-800 dark:bg-gray-900/80 dark:text-gray-100 focus:border-brand-300 focus:ring-3 focus:ring-brand-500/10 focus:outline-hidden">
                                    @if ($pesapalEnabled)
                                        <option value="pesapal">Pesapal (Mobile Money, Card)</option>
                                    @endif
                                    <option value="manual">Manual (Admin will process)</option>
                                </select>
                                <p class="mt-2 text-xs text-gray-500 dark:text-gray-400">Pesapal: pay now via card or mobile money. Manual: admin will process your request.</p>
                            </div>
                        @endif

                        <div x-show="error" class="rounded-lg bg-red-50 dark:bg-red-900/20 px-4 py-3 text-sm text-red-700 dark:text-red-400" x-text="error" x-cloak></div>

                        <button type="submit" class="mt-2 w-full rounded-xl bg-brand-500 px-4 py-3.5 text-sm font-semibold text-white hover:bg-brand-600 transition shadow-sm shadow-brand-500/20" :disabled="submitting">
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
