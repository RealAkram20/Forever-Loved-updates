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

    <div class="space-y-6" x-data="subscriptionPage({{ $checkoutPlan ? json_encode(['id' => $checkoutPlan->id, 'name' => $checkoutPlan->name, 'price' => (float) $checkoutPlan->price, 'interval' => $checkoutPlan->interval]) : 'null' }}, {{ $fromSignup ? 'true' : 'false' }}, {{ json_encode($memorialSlug) }}, {{ json_encode($memorials->map(fn($m) => ['id' => $m->id, 'slug' => $m->slug, 'full_name' => $m->full_name])->values()) }}, {{ $pesapalEnabled ? 'true' : 'false' }})"
        x-init="init(); initCheckoutFromSignup()">
        {{-- Create Payment --}}
        @if ($paymentsEnabled && $plans->where('price', '>', 0)->isNotEmpty() && $memorials->isNotEmpty())
            <x-common.component-card title="Create Payment" desc="Select a memorial, choose a plan, and pay. Pesapal: payment popup opens. Manual: admin will process your request.">
                <div x-data="createPaymentForm({{ $pesapalEnabled ? 'true' : 'false' }})" x-init="init()">
                    <div class="space-y-4">
                        <div class="grid grid-cols-1 gap-4 md:grid-cols-3">
                            <div>
                                <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">Memorial</label>
                                <select x-model="memorialId"
                                    class="h-11 w-full rounded-lg border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-800 px-4 text-sm text-gray-800 dark:text-white/90 focus:border-brand-300 focus:outline-hidden">
                                    <option value="">Select memorial...</option>
                                    @foreach ($memorials as $m)
                                        <option value="{{ $m->id }}">{{ $m->full_name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div>
                                <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">Plan</label>
                                <select x-model="planId" id="create-plan-select"
                                    class="h-11 w-full rounded-lg border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-800 px-4 text-sm text-gray-800 dark:text-white/90 focus:border-brand-300 focus:outline-hidden">
                                    <option value="">Select plan...</option>
                                    @foreach ($plans->where('price', '>', 0) as $p)
                                        <option value="{{ $p->id }}" data-name="{{ addslashes($p->name) }}" data-price="{{ $p->price }}" data-interval="{{ $p->interval }}">{{ $p->name }} - {{ $currency ?? 'USD' }} {{ number_format($p->price, 2) }}/{{ $p->interval }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div>
                                <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">Payment Gateway</label>
                                <select x-model="gateway"
                                    class="h-11 w-full rounded-lg border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-800 px-4 text-sm text-gray-800 dark:text-white/90 focus:border-brand-300 focus:outline-hidden">
                                    @if ($pesapalEnabled)
                                        <option value="pesapal">Pesapal (Mobile Money, Card)</option>
                                    @endif
                                    <option value="manual">Manual (Admin will process)</option>
                                </select>
                            </div>
                        </div>
                        <div x-show="createError" class="rounded-lg bg-red-50 dark:bg-red-900/20 px-4 py-3 text-sm text-red-700 dark:text-red-400" x-text="createError" x-cloak></div>
                        <button type="button" @click="proceedToPayment()" :disabled="submitting"
                            class="inline-flex items-center gap-2 rounded-lg bg-brand-500 px-5 py-2.5 text-sm font-medium text-white hover:bg-brand-600 disabled:opacity-50 disabled:cursor-not-allowed transition">
                            <svg x-show="submitting" class="w-5 h-5 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
                            <svg x-show="!submitting" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"/></svg>
                            <span x-text="submitting ? 'Processing...' : 'Proceed to Payment'"></span>
                        </button>
                    </div>

                    {{-- Pesapal modal: opened only after API returns redirect_url (same as admin) --}}
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
                                    <iframe id="subscription-pesapal-iframe" class="hidden w-full border-0" style="height: 500px;" title="Pesapal Payment"></iframe>
                                    <div id="subscription-pesapal-loading" class="flex flex-col items-center justify-center py-20 text-gray-500 dark:text-gray-400">
                                        <svg class="animate-spin h-10 w-10 text-brand-500 mb-4" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
                                        <p class="text-sm">Redirecting to payment...</p>
                                    </div>
                                </div>
                                <p class="mt-3 text-xs text-gray-500 dark:text-gray-400">Pay by card or mobile money. You will be redirected when complete.</p>
                            </div>
                        </div>
                    </div>
                </div>
            </x-common.component-card>
        @elseif ($memorials->isEmpty() && $paymentsEnabled)
            <x-common.component-card title="Create Payment" desc="Create a memorial first to subscribe.">
                <p class="text-sm text-gray-600 dark:text-gray-400">You need at least one memorial to make a payment.</p>
                <a href="{{ route('memorials.create') }}" class="mt-3 inline-flex items-center gap-2 rounded-lg bg-brand-500 px-4 py-2 text-sm font-medium text-white hover:bg-brand-600 transition">
                    Create Memorial
                </a>
            </x-common.component-card>
        @endif

        {{-- Current Plans (per memorial) - only show when user has active subscriptions --}}
        @if ($currentSubscriptions->filter(fn($s) => $s->isActive())->isNotEmpty())
            <x-common.component-card title="Your Memorials & Plans" desc="Billing is per memorial. Each memorial has its own plan.">
                <div class="space-y-4">
                    @foreach ($currentSubscriptions as $sub)
                        @if ($sub->isActive())
                            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 rounded-lg border border-gray-200 dark:border-gray-700 p-4">
                                <div>
                                    <h4 class="text-lg font-semibold text-gray-800 dark:text-white/90">{{ $sub->plan->name }}</h4>
                                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                                        @if ($sub->memorial)
                                            <a href="{{ route('memorials.show', $sub->memorial) }}" class="text-brand-500 hover:underline">{{ $sub->memorial->full_name }}</a>
                                        @else
                                            Memorial
                                        @endif
                                    </p>
                                    <div class="mt-2 flex flex-wrap gap-3 text-sm">
                                        <span class="inline-flex items-center rounded-full bg-green-100 dark:bg-green-900/30 px-2.5 py-0.5 font-medium text-green-800 dark:text-green-400">Active</span>
                                        <span class="text-gray-600 dark:text-gray-400">{{ $sub->plan->storage_limit_mb }} MB storage</span>
                                        @if ($sub->ends_at)
                                            <span class="text-gray-500 dark:text-gray-400">Renews {{ $sub->ends_at->format('M j, Y') }}</span>
                                        @endif
                                    </div>
                                </div>
                            </div>
                        @endif
                    @endforeach
                </div>
            </x-common.component-card>
        @endif

        {{-- Payment History --}}
        <x-common.component-card title="Payment History" desc="Recent payment transactions.">
            @if (auth()->user()?->hasRole(['admin', 'super-admin']))
                <div class="mb-4">
                    <a href="{{ route('settings.payment-orders') }}"
                        class="inline-flex items-center gap-2 rounded-lg border border-gray-200 dark:border-gray-700 px-4 py-2 text-sm font-medium text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-white/[0.03] transition">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"/></svg>
                        View all orders (Admin)
                    </a>
                </div>
            @endif
            @if ($paymentHistory->isEmpty())
                <p class="text-sm text-gray-500 dark:text-gray-400">No payment history yet.</p>
            @else
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="border-b border-gray-200 dark:border-gray-700">
                                <th class="pb-3 text-left font-medium text-gray-700 dark:text-gray-300 hidden sm:table-cell">Date</th>
                                <th class="pb-3 text-left font-medium text-gray-700 dark:text-gray-300">Memorial</th>
                                <th class="pb-3 text-left font-medium text-gray-700 dark:text-gray-300 hidden md:table-cell">Plan</th>
                                <th class="pb-3 text-left font-medium text-gray-700 dark:text-gray-300">Amount</th>
                                <th class="pb-3 text-left font-medium text-gray-700 dark:text-gray-300">Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($paymentHistory as $payment)
                                <tr class="border-b border-gray-100 dark:border-gray-800">
                                    <td class="py-3 text-gray-600 dark:text-gray-400 hidden sm:table-cell">{{ $payment->created_at->format('M j, Y H:i') }}</td>
                                    <td class="py-3 text-gray-800 dark:text-white/90">
                                        @if ($payment->memorial)
                                            <a href="{{ route('memorials.show', $payment->memorial) }}" class="text-brand-500 hover:underline">{{ $payment->memorial->full_name }}</a>
                                        @else
                                            -
                                        @endif
                                    </td>
                                    <td class="py-3 text-gray-800 dark:text-white/90 hidden md:table-cell">{{ $payment->plan->name ?? 'N/A' }}</td>
                                    <td class="py-3 text-gray-800 dark:text-white/90">{{ number_format($payment->amount, 2) }} {{ $payment->currency }}</td>
                                    <td class="py-3">
                                        <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium
                                            {{ $payment->status === 'completed' ? 'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-400' : '' }}
                                            {{ $payment->status === 'pending' ? 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900/30 dark:text-yellow-400' : '' }}
                                            {{ in_array($payment->status, ['failed', 'cancelled']) ? 'bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-400' : '' }}">
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
    @include('pages.subscription.partials.checkout-modal', ['memorials' => $memorials])
@endsection

@push('scripts')
<script>
function createPaymentForm(pesapalEnabled = true) {
    return {
        memorialId: '',
        planId: '',
        gateway: pesapalEnabled ? 'pesapal' : 'manual',
        submitting: false,
        pesapalOpen: false,
        pesapalError: null,
        createError: null,
        init() {
            window.addEventListener('message', (e) => {
                if (e.data?.type === 'pesapal_payment_complete') {
                    if (e.data?.redirect_url) {
                        window.location.href = e.data.redirect_url;
                    } else {
                        this.closePesapal();
                        window.location.reload();
                    }
                }
            });
        },
        async proceedToPayment() {
            if (!this.memorialId || !this.planId) {
                this.createError = 'Please select a memorial and plan.';
                return;
            }
            this.createError = null;
            this.pesapalError = null;
            this.submitting = true;

            try {
                const formData = new FormData();
                formData.append('plan_id', this.planId);
                formData.append('payment_gateway', this.gateway || 'pesapal');
                formData.append('memorial_id', this.memorialId);
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
                    this.createError = 'Request failed. Please try again.';
                    return;
                }

                if (data.success && data.reload) {
                    if (window.$toast) window.$toast('success', data.message || 'Payment request submitted.');
                    window.location.reload();
                } else if (data.success && data.redirect_url) {
                    this.pesapalOpen = true;
                    this.$nextTick(() => {
                        const iframe = document.getElementById('subscription-pesapal-iframe');
                        const loading = document.getElementById('subscription-pesapal-loading');
                        if (iframe) { iframe.src = data.redirect_url; iframe.classList.remove('hidden'); }
                        if (loading) loading.classList.add('hidden');
                        if (window.$toast) window.$toast('success', data.message || 'Payment popup opened.');
                    });
                } else {
                    this.createError = data.error || data.message || (data.errors && Object.values(data.errors).flat()[0]) || 'Payment failed. Please try again.';
                }
            } catch (e) {
                this.createError = 'Network error. Please try again.';
            } finally {
                this.submitting = false;
            }
        },
        closePesapal() {
            this.pesapalOpen = false;
            const iframe = document.getElementById('subscription-pesapal-iframe');
            const loading = document.getElementById('subscription-pesapal-loading');
            if (iframe) { iframe.src = ''; iframe.classList.add('hidden'); }
            if (loading) loading.classList.remove('hidden');
        }
    };
}

function subscriptionPage(checkoutPlan = null, fromSignup = false, memorialSlug = null, memorials = [], pesapalEnabled = true) {
    return {
        checkoutOpen: false,
        selectedPlan: null,
        planName: '',
        planPrice: 0,
        planInterval: '',
        loading: false,
        error: null,
        checkoutPlan,
        fromSignup,
        memorialSlug,
        memorials: memorials || [],
        needsMemorialSelection: false,
        selectedMemorialId: '',
        createGateway: pesapalEnabled ? 'pesapal' : 'manual',

        initCheckoutFromSignup() {
            if (this.checkoutPlan && this.fromSignup) {
                this.$nextTick(() => {
                    this.openCheckout(
                        this.checkoutPlan.id,
                        this.checkoutPlan.name,
                        this.checkoutPlan.price,
                        this.checkoutPlan.interval
                    );
                });
            }
        },

        openCheckout(planId, planName, planPrice, planInterval) {
            this.selectedPlan = planId;
            this.planName = planName;
            this.planPrice = planPrice;
            this.planInterval = planInterval;
            this.error = null;
            this.selectedMemorialId = '';
            if (!this.fromSignup && !this.memorialSlug && this.memorials.length > 0) {
                this.needsMemorialSelection = true;
                this.loading = false;
            } else if (!this.fromSignup && !this.memorialSlug && this.memorials.length === 0) {
                this.needsMemorialSelection = false;
                this.error = 'Please create a memorial first before subscribing.';
                this.loading = false;
            } else {
                this.needsMemorialSelection = false;
                this.loading = true;
            }
            this.checkoutOpen = true;
            this.$nextTick(() => {
                const iframe = document.getElementById('pesapal-checkout-iframe');
                const placeholder = document.getElementById('pesapal-loading-placeholder');
                if (iframe) { iframe.src = ''; iframe.classList.add('hidden'); }
                if (placeholder) placeholder.classList.remove('hidden');
                if (!this.needsMemorialSelection && (this.memorialSlug || this.fromSignup)) {
                    this.proceedToPay();
                }
            });
        },

        confirmMemorialAndPay() {
            if (!this.selectedMemorialId) return;
            const m = this.memorials.find(x => String(x.id) === String(this.selectedMemorialId));
            if (m) {
                this.memorialSlug = m.slug;
                this.needsMemorialSelection = false;
                this.loading = true;
                this.$nextTick(() => {
                    const placeholder = document.getElementById('pesapal-loading-placeholder');
                    if (placeholder) placeholder.classList.remove('hidden');
                    this.proceedToPay();
                });
            }
        },

        init() {
            window.addEventListener('message', (e) => {
                if (e.data?.type === 'pesapal_payment_complete') {
                    this.checkoutOpen = false;
                    this.loading = false;
                    this.error = null;
                    if (e.data?.redirect_url) {
                        window.location.href = e.data.redirect_url;
                    } else if (e.data?.result === 'success') {
                        window.location.reload();
                    }
                }
            });
        },
        closeCheckout() {
            if (!this.loading) {
                this.checkoutOpen = false;
                this.error = null;
            }
        },

        async proceedToPay() {
            if (!this.selectedPlan) return;
            if (!this.memorialSlug && !this.selectedMemorialId) {
                this.error = 'Please select a memorial.';
                this.loading = false;
                return;
            }
            this.loading = true;
            this.error = null;

            try {
                const formData = new FormData();
                formData.append('plan_id', this.selectedPlan);
                formData.append('payment_gateway', this.createGateway || 'pesapal');
                if (this.fromSignup && this.memorialSlug) {
                    formData.append('from_signup', '1');
                    formData.append('memorial_slug', this.memorialSlug);
                } else if (this.memorialSlug) {
                    formData.append('memorial_slug', this.memorialSlug);
                } else if (this.selectedMemorialId) {
                    formData.append('memorial_id', this.selectedMemorialId);
                }
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

                if (data.success && data.reload) {
                    if (window.$toast) window.$toast('success', data.message || 'Payment request submitted.');
                    window.location.reload();
                } else if (data.success && data.redirect_url) {
                    const iframe = document.getElementById('pesapal-checkout-iframe');
                    const placeholder = document.getElementById('pesapal-loading-placeholder');
                    if (iframe) {
                        iframe.src = data.redirect_url;
                        iframe.classList.remove('hidden');
                    }
                    if (placeholder) placeholder?.classList.add('hidden');
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
