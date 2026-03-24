@extends('layouts.install')

@section('content')
    <h2 class="mb-1 text-lg font-semibold text-gray-900 dark:text-white">Admin Account</h2>
    <p class="mb-6 text-sm text-gray-500 dark:text-gray-400">Create the super administrator account for managing the application.</p>

    <form method="POST" action="{{ route('install.admin.store') }}">
        @csrf
        <div class="space-y-4">
            <div>
                <label for="name" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Full Name</label>
                <input type="text" name="name" id="name" value="{{ old('name', $admin['name']) }}" required
                    class="h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 shadow-sm focus:border-brand-300 focus:ring-3 focus:ring-brand-500/10 dark:border-gray-600 dark:bg-gray-900 dark:text-white dark:focus:border-brand-800" />
                @error('name') <span class="mt-1 text-xs text-red-500">{{ $message }}</span> @enderror
            </div>
            <div>
                <label for="email" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Email Address</label>
                <input type="email" name="email" id="email" value="{{ old('email', $admin['email']) }}" required
                    class="h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 shadow-sm focus:border-brand-300 focus:ring-3 focus:ring-brand-500/10 dark:border-gray-600 dark:bg-gray-900 dark:text-white dark:focus:border-brand-800" />
                @error('email') <span class="mt-1 text-xs text-red-500">{{ $message }}</span> @enderror
            </div>
            <div>
                <label for="password" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Password</label>
                <div x-data="{ showPassword: false }" class="relative">
                    <input :type="showPassword ? 'text' : 'password'" name="password" id="password" required minlength="8" autocomplete="new-password"
                        class="h-11 w-full rounded-lg border border-gray-300 bg-transparent py-2.5 pr-11 pl-4 text-sm text-gray-800 shadow-sm focus:border-brand-300 focus:ring-3 focus:ring-brand-500/10 dark:border-gray-600 dark:bg-gray-900 dark:text-white dark:focus:border-brand-800" />
                    <button type="button" @click="showPassword = !showPassword"
                        class="absolute top-1/2 right-3 z-10 -translate-y-1/2 cursor-pointer text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200"
                        :aria-pressed="showPassword"
                        :aria-label="showPassword ? 'Hide password' : 'Show password'">
                        <svg x-show="!showPassword" class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                        <svg x-show="showPassword" x-cloak class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21"/></svg>
                    </button>
                </div>
                <p class="mt-1 text-xs text-gray-400 dark:text-gray-500">Minimum 8 characters.</p>
                @error('password') <span class="mt-1 text-xs text-red-500">{{ $message }}</span> @enderror
            </div>
            <div>
                <label for="password_confirmation" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Confirm Password</label>
                <div x-data="{ showPassword: false }" class="relative">
                    <input :type="showPassword ? 'text' : 'password'" name="password_confirmation" id="password_confirmation" required autocomplete="new-password"
                        class="h-11 w-full rounded-lg border border-gray-300 bg-transparent py-2.5 pr-11 pl-4 text-sm text-gray-800 shadow-sm focus:border-brand-300 focus:ring-3 focus:ring-brand-500/10 dark:border-gray-600 dark:bg-gray-900 dark:text-white dark:focus:border-brand-800" />
                    <button type="button" @click="showPassword = !showPassword"
                        class="absolute top-1/2 right-3 z-10 -translate-y-1/2 cursor-pointer text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200"
                        :aria-pressed="showPassword"
                        :aria-label="showPassword ? 'Hide password confirmation' : 'Show password confirmation'">
                        <svg x-show="!showPassword" class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                        <svg x-show="showPassword" x-cloak class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21"/></svg>
                    </button>
                </div>
            </div>
        </div>

        {{-- Actions --}}
        <div class="mt-6 flex items-center justify-between border-t border-gray-200 pt-5 dark:border-gray-700">
            <a href="{{ route('install.settings') }}"
               class="inline-flex items-center gap-1.5 rounded-lg border border-gray-300 px-4 py-2.5 text-sm font-medium text-gray-700 transition hover:bg-gray-50 dark:border-gray-600 dark:text-gray-300 dark:hover:bg-gray-700">
                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M10.5 19.5L3 12m0 0l7.5-7.5M3 12h18" /></svg>
                Back
            </a>
            <button type="submit"
                class="inline-flex items-center gap-1.5 rounded-lg bg-brand-500 px-5 py-2.5 text-sm font-medium text-white shadow-sm transition hover:bg-brand-600">
                Install Now
                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5L21 12m0 0l-7.5 7.5M21 12H3" /></svg>
            </button>
        </div>
    </form>
@endsection
