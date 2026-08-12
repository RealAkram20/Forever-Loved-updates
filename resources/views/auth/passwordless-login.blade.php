@extends('layouts.fullscreen-layout')

@section('content')
<div class="flex min-h-screen items-center justify-center bg-gray-50 dark:bg-gray-900 px-4 py-12">
    <div class="w-full max-w-md">
        <div class="rounded-xl border border-gray-200 dark:border-gray-800 bg-white dark:bg-white/[0.03] p-8 shadow-theme-sm">
            <h1 class="text-xl font-semibold text-gray-900 dark:text-white/90">Sign in</h1>
            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                @if ($step === 'email')Choose the quickest way back in.@else Enter your email to receive a one-time login code.@endif
            </p>

            @if ($step === 'email')
                {{-- One click beats six digits. The code flow below stays for anyone
                     without Google, but a returning user should never have to open
                     their inbox just to say something. --}}
                @if (\App\Helpers\SocialLoginHelper::googleLoginEnabled())
                    <a href="{{ route('google.redirect') }}" class="btn btn-secondary btn-md btn-block mt-6 w-full">
                        <svg width="20" height="20" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
                            <path d="M18.7511 10.1944C18.7511 9.47495 18.6915 8.94995 18.5626 8.40552H10.1797V11.6527H15.1003C15.0011 12.4597 14.4654 13.675 13.2749 14.4916L13.2582 14.6003L15.9087 16.6126L16.0924 16.6305C17.7788 15.1041 18.7511 12.8583 18.7511 10.1944Z" fill="#4285F4" />
                            <path d="M10.1788 18.75C12.5895 18.75 14.6133 17.9722 16.0915 16.6305L13.274 14.4916C12.5201 15.0068 11.5081 15.3666 10.1788 15.3666C7.81773 15.3666 5.81379 13.8402 5.09944 11.7305L4.99473 11.7392L2.23868 13.8295L2.20264 13.9277C3.67087 16.786 6.68674 18.75 10.1788 18.75Z" fill="#34A853" />
                            <path d="M5.10014 11.7305C4.91165 11.186 4.80257 10.6027 4.80257 9.99992C4.80257 9.3971 4.91165 8.81379 5.09022 8.26935L5.08523 8.1534L2.29464 6.02954L2.20333 6.0721C1.5982 7.25823 1.25098 8.5902 1.25098 9.99992C1.25098 11.4096 1.5982 12.7415 2.20333 13.9277L5.10014 11.7305Z" fill="#FBBC05" />
                            <path d="M10.1789 4.63331C11.8554 4.63331 12.9864 5.34303 13.6312 5.93612L16.1511 3.525C14.6035 2.11528 12.5895 1.25 10.1789 1.25C6.68676 1.25 3.67088 3.21387 2.20264 6.07218L5.08953 8.26943C5.81381 6.15972 7.81776 4.63331 10.1789 4.63331Z" fill="#EB4335" />
                        </svg>
                        Continue with Google
                    </a>
                    <div class="relative py-3">
                        <div class="absolute inset-0 flex items-center">
                            <div class="w-full border-t border-gray-200 dark:border-gray-800"></div>
                        </div>
                        <div class="relative flex justify-center text-sm">
                            <span class="bg-white px-3 text-gray-400 dark:bg-gray-900">or get a code by email</span>
                        </div>
                    </div>
                @endif
                <form method="POST" action="{{ route('login.code.send') }}" class="{{ \App\Helpers\SocialLoginHelper::googleLoginEnabled() ? 'space-y-4' : 'mt-6 space-y-4' }}">
                    @csrf
                    <div>
                        <label for="email" class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">Email</label>
                        <input type="email" name="email" id="email" value="{{ old('email') }}" required autofocus
                            class="h-11 w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-900 px-4 py-2.5 text-sm" placeholder="your@email.com" />
                        @error('email')
                            <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                        @enderror
                    </div>
                    <button type="submit" class="btn btn-primary btn-md btn-block w-full">
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
                    <button type="submit" class="btn btn-primary btn-md btn-block w-full">
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
