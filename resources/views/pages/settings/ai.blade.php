@extends('layouts.app')

@section('content')
    <x-common.page-breadcrumb pageTitle="AI Configuration" />

    @if (session('success'))
        <div class="mb-4 rounded-lg bg-green-50 dark:bg-green-900/20 px-4 py-3 text-sm text-green-700 dark:text-green-400">
            {{ session('success') }}
        </div>
    @endif

    <form action="{{ route('settings.ai.update') }}" method="POST" class="space-y-6">
        @csrf
        @method('PUT')

        {{-- AI Toggle --}}
        <x-common.component-card title="AI Features" desc="Enable or disable AI-powered features across the application.">
            <div class="flex items-center justify-between" x-data="{ enabled: {{ old('ai.enabled', $settings['ai.enabled'] ?? false) ? 'true' : 'false' }} }">
                <div>
                    <p class="text-sm font-medium text-gray-800 dark:text-white/90">Enable AI</p>
                    <p class="text-sm text-gray-500 dark:text-gray-400">Turn on AI-powered biography generation and other intelligent features.</p>
                </div>
                <input type="hidden" name="ai.enabled" :value="enabled ? '1' : '0'">
                <button type="button" @click="enabled = !enabled"
                    :class="enabled ? 'bg-brand-500' : 'bg-gray-200 dark:bg-gray-700'"
                    class="relative inline-flex h-6 w-11 shrink-0 cursor-pointer rounded-full transition-colors duration-200 ease-in-out">
                    <span :class="enabled ? 'translate-x-5' : 'translate-x-0'"
                        class="pointer-events-none inline-block h-6 w-6 transform rounded-full bg-white shadow ring-0 transition duration-200 ease-in-out"></span>
                </button>
            </div>
        </x-common.component-card>

        {{-- Provider & Model --}}
        <x-common.component-card title="AI Provider" desc="Choose the AI provider and model to use.">
            <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
                <div>
                    <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">Provider</label>
                    <select name="ai.provider"
                        class="h-11 w-full rounded-lg border border-gray-300 dark:border-gray-700 bg-transparent px-4 py-2.5 text-sm text-gray-800 dark:text-white/90 focus:border-brand-300 focus:ring-3 focus:ring-brand-500/10 focus:outline-hidden">
                        @foreach (['openai' => 'OpenAI', 'anthropic' => 'Anthropic', 'gemini' => 'Google Gemini'] as $value => $label)
                            <option value="{{ $value }}" {{ old('ai.provider', $settings['ai.provider'] ?? 'openai') === $value ? 'selected' : '' }}>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">Model</label>
                    <input type="text" name="ai.model"
                        value="{{ old('ai.model', $settings['ai.model'] ?? 'gpt-4o-mini') }}"
                        placeholder="e.g. gpt-4o-mini, claude-3-haiku, gemini-pro"
                        class="h-11 w-full rounded-lg border border-gray-300 dark:border-gray-700 bg-transparent px-4 py-2.5 text-sm text-gray-800 dark:text-white/90 placeholder:text-gray-400 focus:border-brand-300 focus:ring-3 focus:ring-brand-500/10 focus:outline-hidden" />
                </div>

                <div class="lg:col-span-2">
                    <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">API Key</label>
                    <input type="password" name="ai.api_key"
                        value="{{ !empty($settings['ai.api_key']) ? '••••••••' : '' }}"
                        placeholder="Enter your API key"
                        class="h-11 w-full rounded-lg border border-gray-300 dark:border-gray-700 bg-transparent px-4 py-2.5 text-sm text-gray-800 dark:text-white/90 placeholder:text-gray-400 focus:border-brand-300 focus:ring-3 focus:ring-brand-500/10 focus:outline-hidden" />
                    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Leave as dots to keep the existing key. Enter a new value to replace it.</p>
                    @error('ai.api_key')
                        <p class="mt-1 text-sm text-red-500">{{ $message }}</p>
                    @enderror
                </div>
            </div>
        </x-common.component-card>

        {{-- Usage Limits --}}
        <x-common.component-card title="Usage Limits" desc="Control how much AI each user can consume.">
            <div class="grid grid-cols-1 gap-6 sm:grid-cols-3">
                <div>
                    <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">Requests / User / Day</label>
                    <input type="number" name="ai.max_requests_per_user_per_day" min="0" max="1000"
                        value="{{ old('ai.max_requests_per_user_per_day', $settings['ai.max_requests_per_user_per_day'] ?? 10) }}"
                        class="h-11 w-full rounded-lg border border-gray-300 dark:border-gray-700 bg-transparent px-4 py-2.5 text-sm text-gray-800 dark:text-white/90 focus:border-brand-300 focus:ring-3 focus:ring-brand-500/10 focus:outline-hidden" />
                </div>

                <div>
                    <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">Requests / User / Month</label>
                    <input type="number" name="ai.max_requests_per_user_per_month" min="0" max="10000"
                        value="{{ old('ai.max_requests_per_user_per_month', $settings['ai.max_requests_per_user_per_month'] ?? 100) }}"
                        class="h-11 w-full rounded-lg border border-gray-300 dark:border-gray-700 bg-transparent px-4 py-2.5 text-sm text-gray-800 dark:text-white/90 focus:border-brand-300 focus:ring-3 focus:ring-brand-500/10 focus:outline-hidden" />
                </div>

                <div>
                    <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">Max Tokens / Request</label>
                    <input type="number" name="ai.max_tokens_per_request" min="100" max="32000"
                        value="{{ old('ai.max_tokens_per_request', $settings['ai.max_tokens_per_request'] ?? 2000) }}"
                        class="h-11 w-full rounded-lg border border-gray-300 dark:border-gray-700 bg-transparent px-4 py-2.5 text-sm text-gray-800 dark:text-white/90 focus:border-brand-300 focus:ring-3 focus:ring-brand-500/10 focus:outline-hidden" />
                </div>
            </div>
        </x-common.component-card>

        <div class="flex justify-end">
            <button type="submit"
                class="inline-flex items-center gap-2 rounded-lg bg-brand-500 px-5 py-2.5 text-sm font-medium text-white shadow-theme-xs hover:bg-brand-600 transition">
                Save Changes
            </button>
        </div>
    </form>
@endsection
