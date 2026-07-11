@extends('layouts.app')

@section('content')
    <x-common.page-breadcrumb pageTitle="SMTP Configuration" />

    @if (session('success'))
        <div class="mb-4 rounded-lg bg-green-50 dark:bg-green-900/20 px-4 py-3 text-sm text-green-700 dark:text-green-400">
            {{ session('success') }}
        </div>
    @endif

    @if ($errors->any())
        <div class="mb-4 rounded-lg bg-red-50 dark:bg-red-900/20 px-4 py-3">
            <p class="text-sm font-medium text-red-700 dark:text-red-400 mb-1">Please fix the following errors:</p>
            <ul class="list-disc list-inside text-sm text-red-600 dark:text-red-400">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form action="{{ route('settings.smtp.update') }}" method="POST" class="space-y-6">
        @csrf
        @method('PUT')

        <x-common.component-card title="Email / SMTP Settings" desc="Configure SMTP to enable email notifications. Emails will be sent via this SMTP server for user notifications, welcome messages, and alerts.">
            <div class="space-y-6">
                {{-- Enable toggle --}}
                <div class="flex items-center justify-between"
                    x-data="{ enabled: @json((bool) old('smtp.enabled', $settings['smtp.enabled'] ?? false)) }">
                    <div>
                        <p class="text-sm font-medium text-gray-800 dark:text-white/90">Enable SMTP</p>
                        <p class="text-sm text-gray-500 dark:text-gray-400">Turn on to send emails via your SMTP server.</p>
                    </div>
                    <input type="hidden" name="smtp[enabled]" :value="enabled ? '1' : '0'">
                    <label class="flex cursor-pointer select-none items-center">
                        <div class="relative">
                            <input type="checkbox" class="sr-only" @change="enabled = !enabled" :checked="enabled">
                            <div class="block h-6 w-11 rounded-full transition-colors duration-200"
                                :class="enabled ? 'bg-brand-500' : 'bg-gray-200 dark:bg-gray-700'"></div>
                            <div class="shadow-theme-sm absolute top-0.5 left-0.5 h-5 w-5 rounded-full bg-white duration-300 ease-linear"
                                :class="enabled ? 'translate-x-full' : 'translate-x-0'"></div>
                        </div>
                    </label>
                </div>

                {{-- Server settings --}}
                <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
                    <div class="lg:col-span-2">
                        <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">SMTP Host</label>
                        <input type="text" name="smtp[host]"
                            value="{{ old('smtp.host', $settings['smtp.host'] ?? '') }}"
                            placeholder="smtp.gmail.com"
                            class="h-11 w-full rounded-lg border border-gray-300 dark:border-gray-700 bg-transparent px-4 py-2.5 text-sm text-gray-800 dark:text-white/90 placeholder:text-gray-400 focus:border-brand-300 focus:ring-3 focus:ring-brand-500/10 focus:outline-hidden" />
                    </div>
                    <div>
                        <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">Port</label>
                        <input type="number" name="smtp[port]"
                            value="{{ old('smtp.port', $settings['smtp.port'] ?? 587) }}"
                            placeholder="587"
                            class="h-11 w-full rounded-lg border border-gray-300 dark:border-gray-700 bg-transparent px-4 py-2.5 text-sm text-gray-800 dark:text-white/90 placeholder:text-gray-400 focus:border-brand-300 focus:ring-3 focus:ring-brand-500/10 focus:outline-hidden" />
                    </div>
                </div>

                {{-- Authentication --}}
                <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
                    <div>
                        <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">Username</label>
                        <input type="text" name="smtp[username]"
                            value="{{ old('smtp.username', $settings['smtp.username'] ?? '') }}"
                            placeholder="your@email.com"
                            class="h-11 w-full rounded-lg border border-gray-300 dark:border-gray-700 bg-transparent px-4 py-2.5 text-sm text-gray-800 dark:text-white/90 placeholder:text-gray-400 focus:border-brand-300 focus:ring-3 focus:ring-brand-500/10 focus:outline-hidden" />
                    </div>
                    <div>
                        <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">Password</label>
                        <input type="password" name="smtp[password]"
                            value="{{ !empty($settings['smtp.password']) ? '••••••••' : '' }}"
                            placeholder="App password or SMTP password"
                            class="h-11 w-full rounded-lg border border-gray-300 dark:border-gray-700 bg-transparent px-4 py-2.5 text-sm text-gray-800 dark:text-white/90 placeholder:text-gray-400 focus:border-brand-300 focus:ring-3 focus:ring-brand-500/10 focus:outline-hidden" />
                        <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Leave as dots to keep existing password.</p>
                    </div>
                </div>

                {{-- Encryption --}}
                <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
                    <div>
                        <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">Encryption</label>
                        <select name="smtp[encryption]"
                            class="h-11 w-full rounded-lg border border-gray-300 dark:border-gray-700 bg-transparent px-4 py-2.5 text-sm text-gray-800 dark:text-white/90 focus:border-brand-300 focus:ring-3 focus:ring-brand-500/10 focus:outline-hidden">
                            <option value="tls" {{ old('smtp.encryption', $settings['smtp.encryption'] ?? 'tls') === 'tls' ? 'selected' : '' }}>TLS</option>
                            <option value="ssl" {{ old('smtp.encryption', $settings['smtp.encryption'] ?? 'tls') === 'ssl' ? 'selected' : '' }}>SSL</option>
                            <option value="none" {{ old('smtp.encryption', $settings['smtp.encryption'] ?? 'tls') === 'none' ? 'selected' : '' }}>None</option>
                        </select>
                    </div>
                    <div>
                        <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">From Address</label>
                        <input type="email" name="smtp[from_address]"
                            value="{{ old('smtp.from_address', $settings['smtp.from_address'] ?? '') }}"
                            placeholder="noreply@example.com"
                            class="h-11 w-full rounded-lg border border-gray-300 dark:border-gray-700 bg-transparent px-4 py-2.5 text-sm text-gray-800 dark:text-white/90 placeholder:text-gray-400 focus:border-brand-300 focus:ring-3 focus:ring-brand-500/10 focus:outline-hidden" />
                    </div>
                    <div>
                        <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">From Name</label>
                        <input type="text" name="smtp[from_name]"
                            value="{{ old('smtp.from_name', $settings['smtp.from_name'] ?? '') }}"
                            placeholder="Forever Loved"
                            class="h-11 w-full rounded-lg border border-gray-300 dark:border-gray-700 bg-transparent px-4 py-2.5 text-sm text-gray-800 dark:text-white/90 placeholder:text-gray-400 focus:border-brand-300 focus:ring-3 focus:ring-brand-500/10 focus:outline-hidden" />
                    </div>
                </div>
            </div>
        </x-common.component-card>

        {{-- Help card --}}
        <x-common.component-card title="Common SMTP Providers" desc="Quick reference for popular email provider settings.">
            <div class="grid grid-cols-1 gap-4 lg:grid-cols-3">
                <div class="rounded-lg border border-gray-200 dark:border-gray-700 p-4">
                    <h4 class="text-sm font-semibold text-gray-800 dark:text-white/90 mb-2">Gmail</h4>
                    <p class="text-xs text-gray-500 dark:text-gray-400">Host: <code class="text-gray-700 dark:text-gray-300">smtp.gmail.com</code></p>
                    <p class="text-xs text-gray-500 dark:text-gray-400">Port: <code class="text-gray-700 dark:text-gray-300">587</code> (TLS)</p>
                    <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">Use an App Password from your Google Account security settings.</p>
                </div>
                <div class="rounded-lg border border-gray-200 dark:border-gray-700 p-4">
                    <h4 class="text-sm font-semibold text-gray-800 dark:text-white/90 mb-2">Outlook / Office 365</h4>
                    <p class="text-xs text-gray-500 dark:text-gray-400">Host: <code class="text-gray-700 dark:text-gray-300">smtp.office365.com</code></p>
                    <p class="text-xs text-gray-500 dark:text-gray-400">Port: <code class="text-gray-700 dark:text-gray-300">587</code> (TLS)</p>
                </div>
                <div class="rounded-lg border border-gray-200 dark:border-gray-700 p-4">
                    <h4 class="text-sm font-semibold text-gray-800 dark:text-white/90 mb-2">Mailtrap (Testing)</h4>
                    <p class="text-xs text-gray-500 dark:text-gray-400">Host: <code class="text-gray-700 dark:text-gray-300">sandbox.smtp.mailtrap.io</code></p>
                    <p class="text-xs text-gray-500 dark:text-gray-400">Port: <code class="text-gray-700 dark:text-gray-300">2525</code></p>
                    <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">Great for testing without sending real emails.</p>
                </div>
            </div>
        </x-common.component-card>

        <div class="flex justify-end">
            <button type="submit"
                class="btn btn-primary btn-md">
                Save Changes
            </button>
        </div>
    </form>
@endsection
