@extends('layouts.app')

@section('content')
    @if (session('success'))
        <script>
        (function() {
            if (window.self !== window.top) {
                try { window.parent.postMessage({ type: 'pesapal_payment_complete' }, '*'); } catch (e) {}
            }
        })();
        </script>
    @endif
    <x-common.page-breadcrumb pageTitle="Payment Orders" />

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

    <div class="space-y-6">
        <x-common.component-card title="Payment Orders" desc="Billing is per memorial. View and manage all payment transactions. Admin cannot assign payments to themselves.">
            {{-- Status filter --}}
            <div class="mb-4 flex flex-wrap items-center gap-2">
                <span class="text-sm font-medium text-gray-600 dark:text-gray-400">Filter:</span>
                <a href="{{ route('settings.payment-orders') }}"
                    class="rounded-full px-3 py-1 text-xs font-medium transition {{ !request('status') ? 'bg-brand-500 text-white' : 'bg-gray-100 text-gray-700 dark:bg-gray-700 dark:text-gray-300 hover:bg-gray-200 dark:hover:bg-gray-600' }}">
                    All
                </a>
                @foreach (['pending', 'completed', 'failed', 'cancelled'] as $s)
                    <a href="{{ route('settings.payment-orders', ['status' => $s]) }}"
                        class="rounded-full px-3 py-1 text-xs font-medium transition {{ request('status') === $s ? 'bg-brand-500 text-white' : 'bg-gray-100 text-gray-700 dark:bg-gray-700 dark:text-gray-300 hover:bg-gray-200 dark:hover:bg-gray-600' }}">
                        {{ ucfirst($s) }}
                    </a>
                @endforeach
            </div>

            @if ($orders->isEmpty())
                <div class="py-12 text-center">
                    <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"/>
                    </svg>
                    <p class="mt-3 text-sm text-gray-500 dark:text-gray-400">No payment orders found.</p>
                    @if (request('status'))
                        <a href="{{ route('settings.payment-orders') }}" class="mt-2 inline-block text-sm text-brand-500 hover:underline">Clear filter</a>
                    @endif
                </div>
            @else
                <input type="hidden" id="bulk-csrf" value="{{ csrf_token() }}">
                <div id="bulk-actions" class="mb-4 flex flex-wrap items-center gap-2">
                    <span class="text-sm text-gray-500 dark:text-gray-400">With selected:</span>
                    <button type="button" onclick="submitBulk('approve')"
                        class="h-8 rounded-lg bg-green-100 dark:bg-green-900/30 px-3 text-xs font-medium text-green-800 dark:text-green-400 hover:bg-green-200 dark:hover:bg-green-900/50 transition">
                        Approve
                    </button>
                    <button type="button" onclick="submitBulk('mark_failed')"
                        class="h-8 rounded-lg bg-red-100 dark:bg-red-900/30 px-3 text-xs font-medium text-red-800 dark:text-red-400 hover:bg-red-200 dark:hover:bg-red-900/50 transition">
                        Mark Failed
                    </button>
                    <button type="button" onclick="submitBulk('delete')"
                        class="h-8 rounded-lg bg-gray-100 dark:bg-gray-700 px-3 text-xs font-medium text-gray-700 dark:text-gray-300 hover:bg-gray-200 dark:hover:bg-gray-600 transition">
                        Delete
                    </button>
                </div>
                <div class="overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead>
                                <tr class="border-b border-gray-200 dark:border-gray-700">
                                    <th class="pb-3 pr-2 text-left">
                                        <input type="checkbox" id="select-all" class="rounded border-gray-300 dark:border-gray-600 text-brand-500 focus:ring-brand-500"
                                            onchange="document.querySelectorAll('.order-checkbox').forEach(c => c.checked = this.checked)">
                                    </th>
                                    <th class="pb-3 text-left font-medium text-gray-500 dark:text-gray-400">Date</th>
                                    <th class="pb-3 text-left font-medium text-gray-500 dark:text-gray-400">User</th>
                                    <th class="pb-3 text-left font-medium text-gray-500 dark:text-gray-400">Memorial</th>
                                    <th class="pb-3 text-left font-medium text-gray-500 dark:text-gray-400">Plan</th>
                                    <th class="pb-3 text-left font-medium text-gray-500 dark:text-gray-400">Amount</th>
                                    <th class="pb-3 text-left font-medium text-gray-500 dark:text-gray-400">Status</th>
                                    <th class="pb-3 text-left font-medium text-gray-500 dark:text-gray-400">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                                @foreach ($orders as $order)
                                    <tr x-data="{ editing: false }">
                                        <td class="py-3 pr-2" x-show="!editing">
                                            <input type="checkbox" name="ids[]" value="{{ $order->id }}" class="order-checkbox rounded border-gray-300 dark:border-gray-600 text-brand-500 focus:ring-brand-500">
                                        </td>
                                        <td class="py-3 text-gray-600 dark:text-gray-400" x-show="!editing">{{ $order->created_at->format('M j, Y H:i') }}</td>
                                        <td class="py-3" x-show="!editing">
                                            <div class="text-gray-800 dark:text-white/90">{{ $order->user->name ?? 'Deleted' }}</div>
                                            <div class="text-xs text-gray-500">{{ $order->user->email ?? '' }}</div>
                                        </td>
                                        <td class="py-3" x-show="!editing">
                                            @if ($order->memorial)
                                                <a href="{{ route('memorials.show', $order->memorial) }}" class="text-brand-500 hover:underline">{{ $order->memorial->full_name }}</a>
                                            @else
                                                <span class="text-amber-600 dark:text-amber-400">No memorial</span>
                                            @endif
                                        </td>
                                        <td class="py-3 text-gray-700 dark:text-gray-300" x-show="!editing">{{ $order->plan->name ?? 'N/A' }}</td>
                                        <td class="py-3 text-gray-800 dark:text-white/90" x-show="!editing">{{ number_format($order->amount, 2) }} {{ $order->currency }}</td>
                                        <td class="py-3" x-show="!editing">
                                            <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium
                                                {{ $order->status === 'completed' ? 'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-400' : '' }}
                                                {{ $order->status === 'pending' ? 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900/30 dark:text-yellow-400' : '' }}
                                                {{ in_array($order->status, ['failed', 'cancelled']) ? 'bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-400' : '' }}">
                                                {{ ucfirst($order->status) }}
                                            </span>
                                        </td>
                                        <td class="py-3" x-show="!editing">
                                            <div class="flex items-center gap-1.5">
                                                <button type="button" @click="editing = true"
                                                    class="h-8 rounded-md bg-gray-100 dark:bg-gray-800 px-2.5 text-xs font-medium text-gray-700 dark:text-gray-300 hover:bg-gray-200 dark:hover:bg-gray-700 transition">
                                                    Edit
                                                </button>
                                                <button type="button" onclick="deleteSingle({{ $order->id }}, this)"
                                                    class="h-8 rounded-md p-2 text-red-600 dark:text-red-400 hover:bg-red-50 dark:hover:bg-red-900/20 transition"
                                                    title="Delete">
                                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                                </button>
                                            </div>
                                        </td>
                                        <td colspan="8" x-show="editing" x-cloak class="bg-gray-50 dark:bg-gray-800/50 p-4">
                                            <form action="{{ route('settings.payment-orders.update', $order) }}" method="POST" class="space-y-3">
                                                @csrf @method('PUT')
                                                <div class="grid grid-cols-2 gap-4 md:grid-cols-4">
                                                    <div>
                                                        <label class="mb-1 block text-xs font-medium text-gray-600 dark:text-gray-400">User</label>
                                                        <select name="user_id" required
                                                            class="h-9 w-full rounded-lg border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-800 px-3 text-sm text-gray-800 dark:text-white/90 focus:border-brand-300 focus:outline-hidden">
                                                            @foreach ($users as $u)
                                                                <option value="{{ $u->id }}" {{ $order->user_id == $u->id ? 'selected' : '' }}>{{ $u->name }} ({{ $u->email }})</option>
                                                            @endforeach
                                                        </select>
                                                    </div>
                                                    <div>
                                                        <label class="mb-1 block text-xs font-medium text-gray-600 dark:text-gray-400">Memorial</label>
                                                        <select name="memorial_id" required
                                                            class="h-9 w-full rounded-lg border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-800 px-3 text-sm text-gray-800 dark:text-white/90 focus:border-brand-300 focus:outline-hidden">
                                                            @foreach ($memorials as $m)
                                                                <option value="{{ $m->id }}" {{ $order->memorial_id == $m->id ? 'selected' : '' }} data-user="{{ $m->user_id }}">{{ $m->full_name }} ({{ $m->owner->name ?? '' }})</option>
                                                            @endforeach
                                                        </select>
                                                    </div>
                                                    <div>
                                                        <label class="mb-1 block text-xs font-medium text-gray-600 dark:text-gray-400">Plan</label>
                                                        <select name="subscription_plan_id" required
                                                            class="h-9 w-full rounded-lg border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-800 px-3 text-sm text-gray-800 dark:text-white/90 focus:border-brand-300 focus:outline-hidden">
                                                            @foreach ($plans as $p)
                                                                <option value="{{ $p->id }}" {{ $order->subscription_plan_id == $p->id ? 'selected' : '' }}>{{ $p->name }}</option>
                                                            @endforeach
                                                        </select>
                                                    </div>
                                                    <div>
                                                        <label class="mb-1 block text-xs font-medium text-gray-600 dark:text-gray-400">Status</label>
                                                        <select name="status" required
                                                            class="h-9 w-full rounded-lg border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-800 px-3 text-sm text-gray-800 dark:text-white/90 focus:border-brand-300 focus:outline-hidden">
                                                            @foreach (['pending', 'completed', 'failed', 'cancelled'] as $s)
                                                                <option value="{{ $s }}" {{ $order->status === $s ? 'selected' : '' }}>{{ ucfirst($s) }}</option>
                                                            @endforeach
                                                        </select>
                                                    </div>
                                                </div>
                                                <div class="flex gap-2">
                                                    <button type="submit"
                                                        class="h-9 rounded-lg bg-brand-500 px-4 text-sm font-medium text-white hover:bg-brand-600 transition">
                                                        Save
                                                    </button>
                                                    <button type="button" @click="editing = false"
                                                        class="h-9 rounded-lg bg-gray-100 dark:bg-gray-700 px-4 text-sm font-medium text-gray-700 dark:text-gray-300 hover:bg-gray-200 dark:hover:bg-gray-600 transition">
                                                        Cancel
                                                    </button>
                                                </div>
                                            </form>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                <div class="mt-4">
                    {{ $orders->links() }}
                </div>
            @endif
        </x-common.component-card>

        {{-- Create Payment Order --}}
        <x-common.component-card title="Create Payment Order" desc="Create a payment order and assign to a user and memorial. Manual: set status. Pesapal: user pays in popup (card or mobile money).">
            <div x-data="createOrderForm()" x-init="init()">
                <form id="create-order-form" action="{{ route('settings.payment-orders.store') }}" method="POST" class="space-y-4" @submit="handleSubmit($event)">
                    @csrf
                    <div class="grid grid-cols-1 gap-4 md:grid-cols-2 lg:grid-cols-4">
                        <div>
                            <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">User</label>
                            <select name="user_id" required
                                class="h-11 w-full rounded-lg border border-gray-300 dark:border-gray-700 bg-transparent px-4 py-2.5 text-sm text-gray-800 dark:text-white/90 focus:border-brand-300 focus:ring-3 focus:ring-brand-500/10 focus:outline-hidden">
                                <option value="">Select user...</option>
                                @foreach ($users ?? [] as $u)
                                    <option value="{{ $u->id }}" {{ old('user_id') == $u->id ? 'selected' : '' }}>{{ $u->name }} ({{ $u->email }})</option>
                                @endforeach
                            </select>
                            @error('user_id') <p class="mt-1 text-sm text-red-500">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">Memorial</label>
                            <select name="memorial_id" required
                                class="h-11 w-full rounded-lg border border-gray-300 dark:border-gray-700 bg-transparent px-4 py-2.5 text-sm text-gray-800 dark:text-white/90 focus:border-brand-300 focus:ring-3 focus:ring-brand-500/10 focus:outline-hidden">
                                <option value="">Select memorial...</option>
                                @foreach ($memorials ?? [] as $m)
                                    <option value="{{ $m->id }}" {{ old('memorial_id') == $m->id ? 'selected' : '' }} data-user="{{ $m->user_id }}">{{ $m->full_name }} ({{ $m->owner->name ?? '' }})</option>
                                @endforeach
                            </select>
                            @error('memorial_id') <p class="mt-1 text-sm text-red-500">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">Plan</label>
                            <select name="subscription_plan_id" required
                                class="h-11 w-full rounded-lg border border-gray-300 dark:border-gray-700 bg-transparent px-4 py-2.5 text-sm text-gray-800 dark:text-white/90 focus:border-brand-300 focus:ring-3 focus:ring-brand-500/10 focus:outline-hidden">
                                <option value="">Select plan...</option>
                                @foreach ($plans ?? [] as $p)
                                    <option value="{{ $p->id }}" {{ old('subscription_plan_id') == $p->id ? 'selected' : '' }}>{{ $p->name }} ({{ $currency ?? 'USD' }} {{ number_format($p->price, 2) }})</option>
                                @endforeach
                            </select>
                            @error('subscription_plan_id') <p class="mt-1 text-sm text-red-500">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">Payment Gateway</label>
                            <select name="payment_gateway" required x-model="gateway"
                                class="h-11 w-full rounded-lg border border-gray-300 dark:border-gray-700 bg-transparent px-4 py-2.5 text-sm text-gray-800 dark:text-white/90 focus:border-brand-300 focus:ring-3 focus:ring-brand-500/10 focus:outline-hidden">
                                <option value="manual" {{ old('payment_gateway', 'manual') === 'manual' ? 'selected' : '' }}>Manual</option>
                                <option value="pesapal" {{ old('payment_gateway') === 'pesapal' ? 'selected' : '' }}>Pesapal</option>
                            </select>
                            @error('payment_gateway') <p class="mt-1 text-sm text-red-500">{{ $message }}</p> @enderror
                        </div>
                        <div x-show="gateway === 'manual'" x-cloak>
                            <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">Status</label>
                            <select name="status" :required="gateway === 'manual'"
                                class="h-11 w-full rounded-lg border border-gray-300 dark:border-gray-700 bg-transparent px-4 py-2.5 text-sm text-gray-800 dark:text-white/90 focus:border-brand-300 focus:ring-3 focus:ring-brand-500/10 focus:outline-hidden">
                                @foreach (['pending', 'completed', 'failed', 'cancelled'] as $s)
                                    <option value="{{ $s }}" {{ old('status', 'pending') === $s ? 'selected' : '' }}>{{ ucfirst($s) }}</option>
                                @endforeach
                            </select>
                            @error('status') <p class="mt-1 text-sm text-red-500">{{ $message }}</p> @enderror
                        </div>
                    </div>
                    <div class="flex justify-end">
                        <button type="submit" :disabled="submitting"
                            class="inline-flex items-center gap-2 rounded-lg bg-brand-500 px-5 py-2.5 text-sm font-medium text-white shadow-theme-xs hover:bg-brand-600 disabled:opacity-50 transition">
                            <span x-show="!submitting">Create Payment Order</span>
                            <span x-show="submitting">Creating...</span>
                        </button>
                    </div>
                </form>

                {{-- Pesapal payment popup --}}
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
                                <iframe id="admin-pesapal-iframe" class="hidden w-full border-0" style="height: 500px;" title="Pesapal Payment"></iframe>
                                <div id="admin-pesapal-loading" class="flex flex-col items-center justify-center py-20 text-gray-500 dark:text-gray-400">
                                    <svg class="animate-spin h-10 w-10 text-brand-500 mb-4" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
                                    <p class="text-sm">Redirecting to payment...</p>
                                </div>
                            </div>
                            <p class="mt-3 text-xs text-gray-500 dark:text-gray-400">User can pay by card or mobile money. Status updates when payment completes.</p>
                        </div>
                    </div>
                </div>
            </div>
        </x-common.component-card>
    </div>

    <script>
    function createOrderForm() {
        return {
            gateway: '{{ old('payment_gateway', 'manual') }}',
            submitting: false,
            pesapalOpen: false,
            pesapalError: null,
            init() {
                window.addEventListener('message', (e) => {
                    if (e.data?.type === 'pesapal_payment_complete') {
                        if (e.data?.redirect_url) {
                            window.location.href = e.data.redirect_url;
                        } else {
                            this.closePesapal();
                        }
                    }
                });
            },
            handleSubmit(e) {
                if (this.gateway !== 'pesapal') return;
                e.preventDefault();
                this.submitting = true;
                this.pesapalError = null;
                const form = document.getElementById('create-order-form');
                const formData = new FormData(form);
                fetch('{{ route('settings.payment-orders.store') }}', {
                    method: 'POST',
                    body: formData,
                    headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' },
                    credentials: 'same-origin',
                }).then(r => r.json()).then(data => {
                    this.submitting = false;
                    if (data.success && data.redirect_url) {
                        this.pesapalOpen = true;
                        const iframe = document.getElementById('admin-pesapal-iframe');
                        const loading = document.getElementById('admin-pesapal-loading');
                        if (iframe) { iframe.src = data.redirect_url; iframe.classList.remove('hidden'); }
                        if (loading) loading.classList.add('hidden');
                        if (window.$toast) window.$toast('success', data.message || 'Payment popup opened.');
                    } else if (data.reload) {
                        if (window.$toast) window.$toast('success', data.message || 'Payment order created.');
                        window.location.reload();
                    } else if (data.error) {
                        this.pesapalError = data.error;
                        if (window.$toast) window.$toast('error', data.error);
                    }
                }).catch(() => {
                    this.submitting = false;
                    if (window.$toast) window.$toast('error', 'Request failed.');
                });
            },
            closePesapal() {
                this.pesapalOpen = false;
                const iframe = document.getElementById('admin-pesapal-iframe');
                const loading = document.getElementById('admin-pesapal-loading');
                if (iframe) { iframe.src = ''; iframe.classList.add('hidden'); }
                if (loading) loading.classList.remove('hidden');
                window.location.reload();
            },
        };
    }
    </script>
    @if (!$orders->isEmpty())
    <script>
    (function() {
        const bulkUrl = '{{ url("/settings/payment-orders/bulk") }}';
        const destroyUrl = (id) => '{{ url("/settings/payment-orders") }}/' + id;
        const csrf = document.querySelector('#bulk-csrf')?.value || document.querySelector('meta[name="csrf-token"]')?.content || '';

        let deleteConfirmCount = 0;
        let deleteConfirmTimer = null;
        let singleDeleteConfirm = {};

        window.submitBulk = function(action) {
            const checked = document.querySelectorAll('.order-checkbox:checked');
            if (checked.length === 0) {
                if (window.$toast) window.$toast('warning', 'Please select at least one payment.');
                return;
            }
            if (action === 'delete') {
                deleteConfirmCount++;
                if (deleteConfirmCount === 1) {
                    if (window.$toast) window.$toast('warning', 'Click Delete again to confirm.');
                    deleteConfirmTimer = setTimeout(function() { deleteConfirmCount = 0; }, 5000);
                    return;
                }
                deleteConfirmCount = 0;
                if (deleteConfirmTimer) clearTimeout(deleteConfirmTimer);
            }
            const formData = new FormData();
            formData.append('_token', csrf);
            formData.append('action', action);
            checked.forEach(function(cb) { formData.append('ids[]', cb.value); });
            fetch(bulkUrl, {
                method: 'POST',
                body: formData,
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'application/json',
                },
                credentials: 'same-origin',
            }).then(function(r) {
                if (r.status === 404) {
                    if (window.$toast) window.$toast('error', 'Bulk action endpoint not found. Try: php artisan route:clear');
                    return;
                }
                if (r.status === 422) {
                    return r.json().then(function(d) {
                        var msg = (d.errors && Object.values(d.errors).flat()[0]) || d.message || 'Validation failed.';
                        if (window.$toast) window.$toast('error', msg);
                    });
                }
                if (!r.ok) {
                    if (window.$toast) window.$toast('error', 'Request failed.');
                    return;
                }
                return r.json().then(function(d) {
                    if (d.message && window.$toast) window.$toast('success', d.message);
                    window.location.reload();
                });
            }).catch(function() {
                if (window.$toast) window.$toast('error', 'Request failed. Check your connection.');
            });
        };

        window.deleteSingle = function(id, btn) {
            if (!singleDeleteConfirm[id]) {
                singleDeleteConfirm[id] = true;
                if (window.$toast) window.$toast('warning', 'Click Delete again to confirm.');
                setTimeout(function() { singleDeleteConfirm[id] = false; }, 5000);
                return;
            }
            singleDeleteConfirm[id] = false;
            btn.disabled = true;
            fetch(destroyUrl(id), {
                method: 'DELETE',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': csrf,
                    'Content-Type': 'application/json',
                },
                credentials: 'same-origin',
            }).then(function(r) {
                if (r.ok) return r.json();
                throw new Error('Failed');
            }).then(function(d) {
                if (d.message && window.$toast) window.$toast('success', d.message);
                window.location.reload();
            }).catch(function() {
                btn.disabled = false;
                if (window.$toast) window.$toast('error', 'Delete failed.');
            });
        };
    })();
    </script>
    @endif
@endsection
