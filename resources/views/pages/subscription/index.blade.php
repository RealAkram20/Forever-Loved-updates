@extends('layouts.app')

@section('content')
    <x-common.page-breadcrumb pageTitle="My Subscription" />

    @if (session('success'))
        <div class="mb-4 rounded-lg bg-green-50 dark:bg-green-900/20 px-4 py-3 text-sm text-green-700 dark:text-green-400">
            {{ session('success') }}
        </div>
    @endif
    @if (session('error'))
        <div class="mb-4 rounded-lg bg-red-50 dark:bg-red-900/20 px-4 py-3 text-sm text-red-700 dark:text-red-400">
            {{ session('error') }}
        </div>
    @endif
    @if (session('info'))
        <div class="mb-4 rounded-lg bg-blue-50 dark:bg-blue-900/20 px-4 py-3 text-sm text-blue-700 dark:text-blue-400">
            {{ session('info') }}
        </div>
    @endif

    <div class="space-y-6" x-data="subscriptionPage({{ $checkoutPlan ? json_encode(['id' => $checkoutPlan->id, 'name' => $checkoutPlan->name, 'price' => (float) $checkoutPlan->price, 'interval' => $checkoutPlan->interval]) : 'null' }}, {{ $fromSignup ? 'true' : 'false' }}, {{ json_encode($memorialSlug) }})"
        x-init="initCheckoutFromSignup()">
        {{-- Current Plan --}}
        <x-common.component-card title="Current Plan" desc="Your active subscription details.">
            @if ($currentSubscription && $currentSubscription->isActive())
                <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                    <div>
                        <h4 class="text-lg font-semibold text-gray-800 dark:text-white/90">{{ $currentSubscription->plan->name }}</h4>
                        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">{{ $currentSubscription->plan->description }}</p>
                        <div class="mt-2 flex flex-wrap gap-3 text-sm">
                            <span class="inline-flex items-center rounded-full bg-green-100 dark:bg-green-900/30 px-2.5 py-0.5 font-medium text-green-800 dark:text-green-400">Active</span>
                            <span class="text-gray-600 dark:text-gray-400">
                                {{ $currentSubscription->plan->memorial_limit }} memorial(s) · {{ $currentSubscription->plan->storage_limit_mb }} MB storage
                            </span>
                            @if ($currentSubscription->ends_at)
                                <span class="text-gray-500 dark:text-gray-400">
                                    Renews {{ $currentSubscription->ends_at->format('M j, Y') }}
                                </span>
                            @endif
                        </div>
                    </div>
                </div>
            @else
                <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                    <div>
                        <p class="text-gray-600 dark:text-gray-400">You don't have an active subscription.</p>
                        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Choose a plan below to get started.</p>
                    </div>
                </div>
            @endif
        </x-common.component-card>

        {{-- Choose Plan --}}
        @if ($paymentsEnabled && $pesapalEnabled && $plans->isNotEmpty())
            <x-common.component-card title="Subscription Plans" desc="Choose or change your plan.">
                <div class="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-3">
                    @foreach ($plans as $plan)
                        <div class="rounded-xl border border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800/50 p-5">
                            <div class="flex items-start justify-between mb-3">
                                <h4 class="text-lg font-semibold text-gray-800 dark:text-white/90">{{ $plan->name }}</h4>
                                @if ($currentSubscription?->plan_id === $plan->id && $currentSubscription?->isActive())
                                    <span class="inline-flex items-center rounded-full bg-green-100 dark:bg-green-900/30 px-2.5 py-0.5 text-xs font-medium text-green-800 dark:text-green-400">Current</span>
                                @endif
                            </div>
                            <div class="mb-4">
                                @if ($plan->isFree())
                                    <span class="text-2xl font-bold text-gray-800 dark:text-white/90">Free</span>
                                @else
                                    <span class="text-2xl font-bold text-gray-800 dark:text-white/90">${{ number_format($plan->price, 2) }}</span>
                                    <span class="text-sm text-gray-500 dark:text-gray-400">/ {{ $plan->interval }}</span>
                                @endif
                            </div>
                            <p class="mb-4 text-sm text-gray-600 dark:text-gray-400">{{ $plan->description ?? 'No description' }}</p>
                            <div class="space-y-1.5 text-sm text-gray-600 dark:text-gray-400 mb-4">
                                <div class="flex items-center gap-2">
                                    <svg class="w-4 h-4 text-green-500" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/></svg>
                                    {{ $plan->memorial_limit }} memorial{{ $plan->memorial_limit > 1 ? 's' : '' }}
                                </div>
                                <div class="flex items-center gap-2">
                                    <svg class="w-4 h-4 text-green-500" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/></svg>
                                    {{ $plan->storage_limit_mb }} MB storage
                                </div>
                            </div>
                            @if (!$plan->isFree() && ($currentSubscription?->plan_id !== $plan->id || !$currentSubscription?->isActive()))
                                <button type="button"
                                    data-plan-id="{{ $plan->id }}"
                                    data-plan-name="{{ addslashes($plan->name) }}"
                                    data-plan-price="{{ $plan->price }}"
                                    data-plan-interval="{{ $plan->interval }}"
                                    @click="openCheckout({{ $plan->id }}, '{{ addslashes($plan->name) }}', {{ $plan->price }}, '{{ $plan->interval }}')"
                                    class="w-full rounded-lg bg-brand-500 px-4 py-2.5 text-sm font-medium text-white hover:bg-brand-600 transition">
                                    Subscribe
                                </button>
                            @elseif ($plan->isFree())
                                <span class="inline-block w-full rounded-lg bg-gray-200 dark:bg-gray-700 px-4 py-2.5 text-center text-sm font-medium text-gray-600 dark:text-gray-400">Free Plan</span>
                            @endif
                        </div>
                    @endforeach
                </div>
            </x-common.component-card>
        @elseif ($plans->isNotEmpty())
            <x-common.component-card title="Subscription Plans" desc="Plans are managed by the administrator.">
                <div class="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-3">
                    @foreach ($plans as $plan)
                        <div class="rounded-xl border border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800/50 p-5">
                            <h4 class="text-lg font-semibold text-gray-800 dark:text-white/90">{{ $plan->name }}</h4>
                            <div class="mt-2">
                                @if ($plan->isFree())
                                    <span class="text-xl font-bold">Free</span>
                                @else
                                    <span class="text-xl font-bold">${{ number_format($plan->price, 2) }}</span>
                                    <span class="text-sm text-gray-500">/ {{ $plan->interval }}</span>
                                @endif
                            </div>
                            <p class="mt-2 text-sm text-gray-600 dark:text-gray-400">{{ $plan->description ?? 'No description' }}</p>
                            @if ($currentSubscription?->plan_id === $plan->id)
                                <span class="mt-3 inline-flex items-center rounded-full bg-green-100 dark:bg-green-900/30 px-2.5 py-0.5 text-xs font-medium text-green-800 dark:text-green-400">Current Plan</span>
                            @endif
                        </div>
                    @endforeach
                </div>
                @if (!$paymentsEnabled || !$pesapalEnabled)
                    <p class="mt-4 text-sm text-gray-500 dark:text-gray-400">Payment processing is not available at this time.</p>
                @endif
            </x-common.component-card>
        @endif

        {{-- Payment History --}}
        <x-common.component-card title="Payment History" desc="Recent payment transactions.">
            @if ($paymentHistory->isEmpty())
                <p class="text-sm text-gray-500 dark:text-gray-400">No payment history yet.</p>
            @else
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="border-b border-gray-200 dark:border-gray-700">
                                <th class="pb-3 text-left font-medium text-gray-700 dark:text-gray-300">Date</th>
                                <th class="pb-3 text-left font-medium text-gray-700 dark:text-gray-300">Plan</th>
                                <th class="pb-3 text-left font-medium text-gray-700 dark:text-gray-300">Amount</th>
                                <th class="pb-3 text-left font-medium text-gray-700 dark:text-gray-300">Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($paymentHistory as $payment)
                                <tr class="border-b border-gray-100 dark:border-gray-800">
                                    <td class="py-3 text-gray-600 dark:text-gray-400">{{ $payment->created_at->format('M j, Y H:i') }}</td>
                                    <td class="py-3 text-gray-800 dark:text-white/90">{{ $payment->plan->name }}</td>
                                    <td class="py-3 text-gray-800 dark:text-white/90">{{ number_format($payment->amount, 2) }} {{ $payment->currency }}</td>
                                    <td class="py-3">
                                        <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium
                                            {{ $payment->status === 'completed' ? 'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-400' : '' }}
                                            {{ $payment->status === 'pending' ? 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900/30 dark:text-yellow-400' : '' }}
                                            {{ $payment->status === 'failed' ? 'bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-400' : '' }}">
                                            {{ ucfirst($payment->status) }}
                                        </span>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </x-common.component-card>
    </div>

    {{-- Checkout Modal --}}
    @include('pages.subscription.partials.checkout-modal')
@endsection

@push('scripts')
<script>
function subscriptionPage(checkoutPlan = null, fromSignup = false, memorialSlug = null) {
    return {
        checkoutOpen: false,
        step: 'method', // method, processing
        selectedPlan: null,
        planName: '',
        planPrice: 0,
        planInterval: '',
        paymentMethod: null,
        loading: false,
        error: null,
        checkoutPlan,
        fromSignup,
        memorialSlug,

        initCheckoutFromSignup() {
            if (this.checkoutPlan && this.fromSignup) {
                this.openCheckout(
                    this.checkoutPlan.id,
                    this.checkoutPlan.name,
                    this.checkoutPlan.price,
                    this.checkoutPlan.interval
                );
            }
        },

        openCheckout(planId, planName, planPrice, planInterval) {
            this.selectedPlan = planId;
            this.planName = planName;
            this.planPrice = planPrice;
            this.planInterval = planInterval;
            this.step = 'method';
            this.paymentMethod = null;
            this.error = null;
            this.checkoutOpen = true;
        },

        closeCheckout() {
            if (!this.loading) {
                this.checkoutOpen = false;
                this.step = 'method';
                this.paymentMethod = null;
                this.error = null;
            }
        },

        selectMethod(method) {
            this.paymentMethod = method;
        },

        async proceedToPay() {
            if (!this.paymentMethod || !this.selectedPlan) return;
            this.loading = true;
            this.error = null;
            this.step = 'processing';

            try {
                const formData = new FormData();
                formData.append('plan_id', this.selectedPlan);
                formData.append('payment_method', this.paymentMethod);
                @if ($fromSignup && $memorialSlug)
                formData.append('from_signup', '1');
                formData.append('memorial_slug', '{{ $memorialSlug }}');
                @endif
                formData.append('_token', '{{ csrf_token() }}');

                const res = await fetch('{{ route("payment.create-order") }}', {
                    method: 'POST',
                    body: formData,
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest',
                        'Accept': 'application/json',
                    },
                });

                const data = await res.json();

                if (data.success && data.redirect_url) {
                    const iframe = document.getElementById('pesapal-checkout-iframe');
                    const placeholder = document.getElementById('pesapal-loading-placeholder');
                    if (iframe) {
                        iframe.src = data.redirect_url;
                        iframe.classList.remove('hidden');
                    }
                    if (placeholder) placeholder.classList.add('hidden');
                } else {
                    this.error = data.error || 'Something went wrong.';
                    this.step = 'method';
                }
            } catch (e) {
                this.error = 'Network error. Please try again.';
                this.step = 'method';
            } finally {
                this.loading = false;
            }
        }
    };
}
</script>
@endpush
