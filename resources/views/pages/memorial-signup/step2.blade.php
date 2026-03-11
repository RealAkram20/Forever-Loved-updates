@extends('layouts.fullscreen-layout')

@section('content')
<div class="relative z-1 bg-white p-6 sm:p-0" x-data="step2Form()">
    <div class="relative flex min-h-screen w-full flex-col justify-center py-12 sm:p-0">
        <div class="flex w-full flex-1 flex-col">
            <div class="mx-auto w-full max-w-2xl px-6 pt-10 lg:px-12">
                <x-memorial-signup.step-tabs :currentStep="2" />
                <a href="{{ route('memorial.create.step1') }}" class="inline-flex items-center text-sm text-gray-500 hover:text-gray-700">
                    <svg class="stroke-current" xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 20 20" fill="none">
                        <path d="M12.7083 5L7.5 10.2083L12.7083 15.4167" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" />
                    </svg>
                    Back
                </a>
                <div class="mt-8">
                    <div class="mb-6 flex items-center gap-2">
                        <span class="rounded-full bg-brand-500 px-3 py-1 text-xs font-medium text-white">Step 2 of 3</span>
                        <span class="text-sm text-gray-500">Create account</span>
                    </div>
                    <h1 class="text-title-sm sm:text-title-md mb-2 font-semibold text-gray-800">Create your account</h1>
                    <p class="mb-6 text-sm text-gray-500">Your memorial data from Step 1 is saved. Sign up or sign in to continue.</p>

                    @if ($errors->any())
                        <div class="mb-4 rounded-lg bg-red-50 p-4 text-sm text-red-600">{{ $errors->first() }}</div>
                    @endif

                    <div x-show="emailExists" class="mb-4 rounded-lg bg-amber-50 p-4 text-sm text-amber-800">
                        <p>An account with this email already exists. <a href="#" @click.prevent="showLoginForm()" class="font-medium underline">Sign in instead</a></p>
                    </div>

                    {{-- Register form (signup) - shown by default when showLogin is false --}}
                    <div x-show="!showLogin">
                        <form method="POST" action="{{ route('memorial.create.storeStep2Register') }}" class="space-y-5">
                        @csrf
                        <div>
                            <label class="mb-1.5 block text-sm font-medium text-gray-700" for="name">Full name</label>
                            <input type="text" id="name" name="name" value="{{ old('name') }}" required
                                class="dark:bg-dark-900 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden" />
                        </div>
                        <div>
                            <label class="mb-1.5 block text-sm font-medium text-gray-700" for="reg_email">Email</label>
                            <input type="email" id="reg_email" name="email" value="{{ old('email') }}" required
                                class="dark:bg-dark-900 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden"
                                @blur="checkEmail($event.target.value)" />
                        </div>
                        <div>
                            <label class="mb-1.5 block text-sm font-medium text-gray-700" for="password">Password</label>
                            <input type="password" id="password" name="password" required
                                class="dark:bg-dark-900 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden" />
                        </div>
                        <div>
                            <label class="mb-1.5 block text-sm font-medium text-gray-700" for="password_confirmation">Confirm password</label>
                            <input type="password" id="password_confirmation" name="password_confirmation" required
                                class="dark:bg-dark-900 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden" />
                        </div>
                        <button type="submit" class="w-full rounded-lg bg-brand-500 px-4 py-3 text-sm font-medium text-white hover:bg-brand-600">
                            Create account & continue
                        </button>
                        </form>
                    </div>

                    {{-- Login form --}}
                    <form x-show="showLogin" method="POST" action="{{ route('memorial.create.storeStep2Login') }}" class="space-y-5" x-cloak style="display: none;">
                        @csrf
                        <div>
                            <label class="mb-1.5 block text-sm font-medium text-gray-700" for="login_email">Email</label>
                            <input type="email" id="login_email" name="email" x-model="emailValue" required
                                class="dark:bg-dark-900 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden" />
                        </div>
                        <div>
                            <label class="mb-1.5 block text-sm font-medium text-gray-700" for="login_password">Password</label>
                            <input type="password" id="login_password" name="password" required
                                class="dark:bg-dark-900 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden" />
                        </div>
                        <div>
                            <label class="flex cursor-pointer items-center gap-2">
                                <input type="checkbox" name="remember" class="rounded border-gray-300 text-brand-600 focus:ring-brand-500" />
                                <span class="text-sm text-gray-700">Remember me</span>
                            </label>
                        </div>
                        <button type="submit" class="w-full rounded-lg bg-brand-500 px-4 py-3 text-sm font-medium text-white hover:bg-brand-600">
                            Sign in & continue
                        </button>
                        <p class="text-center text-sm text-gray-500">
                            <a href="#" @click.prevent="showLogin = false" class="text-brand-500 hover:text-brand-600">Create new account instead</a>
                        </p>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

@push('scripts')
<script>
function step2Form() {
    return {
        showLogin: false,
        emailExists: false,
        emailValue: '{{ old("email") }}',
        checkEmail(email) {
            if (!email) return;
            fetch('{{ route("memorial.create.checkEmail") }}', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    'Accept': 'application/json',
                },
                body: JSON.stringify({ email: email })
            })
            .then(r => r.json())
            .then(data => {
                this.emailExists = data.exists;
                if (data.exists) {
                    this.emailValue = email;
                }
            });
        },
        showLoginForm() {
            this.emailValue = document.getElementById('reg_email')?.value || this.emailValue;
            this.showLogin = true;
            this.emailExists = false;
        }
    };
}
</script>
@endpush
@endsection
