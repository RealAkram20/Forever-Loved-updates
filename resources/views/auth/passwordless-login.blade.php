@extends('layouts.fullscreen-layout')

@section('content')
<div class="flex min-h-screen items-center justify-center bg-gray-50 dark:bg-gray-900 px-4 py-12">
    <div class="w-full max-w-md">
        <div class="rounded-xl border border-gray-200 dark:border-gray-800 bg-white dark:bg-white/[0.03] p-8 shadow-theme-sm">
            <h1 class="text-xl font-semibold text-gray-900 dark:text-white/90">Sign in</h1>
            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Enter your email to receive a one-time login code.</p>

            @if ($step === 'email')
                <form method="POST" action="{{ route('login.code.send') }}" class="mt-6 space-y-4">
                    @csrf
                    <div>
                        <label for="email" class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">Email</label>
                        <input type="email" name="email" id="email" value="{{ old('email') }}" required autofocus
                            class="h-11 w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-900 px-4 py-2.5 text-sm" placeholder="your@email.com" />
                        @error('email')
                            <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                        @enderror
                    </div>
                    <button type="submit" class="w-full rounded-lg bg-brand-500 px-4 py-2.5 text-sm font-medium text-white hover:bg-brand-600">
                        Send login code
                    </button>
                </form>
            @else
                <form method="POST" action="{{ route('login.code.verify') }}" class="mt-6 space-y-4">
                    @csrf
                    <input type="hidden" name="email" value="{{ $email }}" />
                    <p class="text-sm text-gray-600 dark:text-gray-400">We sent a 6-digit code to <strong>{{ $email }}</strong></p>
                    <div>
                        <label for="code" class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">Enter code</label>
                        <input type="text" name="code" id="code" required autofocus maxlength="6" pattern="[0-9]*" inputmode="numeric"
                            class="h-11 w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-900 px-4 py-2.5 text-center text-lg tracking-widest" placeholder="000000" />
                        @error('code')
                            <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                        @enderror
                    </div>
                    <button type="submit" class="w-full rounded-lg bg-brand-500 px-4 py-2.5 text-sm font-medium text-white hover:bg-brand-600">
                        Sign in
                    </button>
                    <a href="{{ route('login.passwordless') }}" class="block text-center text-sm text-brand-500 hover:text-brand-600">Use a different email</a>
                </form>
            @endif

            <p class="mt-6 text-center text-sm text-gray-500 dark:text-gray-400">
                <a href="{{ route('login') }}" class="text-brand-500 hover:text-brand-600">Use password instead</a>
            </p>
        </div>
    </div>
</div>
@endsection
