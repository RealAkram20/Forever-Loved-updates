@extends('layouts.fullscreen-layout')

@section('content')
<div class="relative z-1 bg-white dark:bg-gray-900 px-6 pt-6 pb-[max(8rem,env(safe-area-inset-bottom,0px)+5rem)] sm:px-0 sm:pt-10 sm:pb-[max(8rem,env(safe-area-inset-bottom,0px)+3rem)] lg:pb-40" x-data="step2Form({{ json_encode(['name' => old('name', ''), 'email' => old('email', '')]) }})">
    <div class="relative flex min-h-screen w-full flex-col justify-start py-8 sm:py-12">
        <div class="flex w-full flex-1 flex-col">
            <div class="mx-auto w-full max-w-2xl px-0 pt-4 pb-12 sm:px-6 sm:pt-10 sm:pb-16 lg:px-12 lg:pb-20">
                <x-memorial-signup.step-tabs :currentStep="2" />
                <a href="{{ route('memorial.create.step1') }}" class="inline-flex items-center text-sm text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200">
                    <svg class="stroke-current" xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 20 20" fill="none">
                        <path d="M12.7083 5L7.5 10.2083L12.7083 15.4167" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" />
                    </svg>
                    Back
                </a>
                <div class="mt-8">
                    <div class="mb-6 flex items-center gap-2">
                        <span class="rounded-full bg-brand-500 px-3 py-1 text-xs font-medium text-white">Step 2 of 3</span>
                        <span class="text-sm text-gray-500 dark:text-gray-400">Create account</span>
                    </div>
                    <h1 class="text-title-sm sm:text-title-md mb-2 font-semibold text-gray-800 dark:text-white">Create your account</h1>
                    <p class="mb-6 text-sm text-gray-500 dark:text-gray-400">Your memorial data from Step 1 is saved. Sign up or sign in to continue.</p>

                    {{-- Field errors render inline below their inputs; the banner covers everything else --}}
                    @php $inlineErrorFields = ['name', 'email', 'password', 'password_confirmation']; @endphp
                    @if ($errors->any() && collect($errors->keys())->diff($inlineErrorFields)->isNotEmpty())
                        <div class="mb-4 rounded-lg bg-red-50 p-4 text-sm text-red-600 dark:bg-red-950/40 dark:text-red-400">{{ $errors->first(collect($errors->keys())->diff($inlineErrorFields)->first()) }}</div>
                    @endif

                    <div x-show="emailExists" class="mb-4 rounded-lg bg-amber-50 p-4 text-sm text-amber-800 dark:bg-amber-950/40 dark:text-amber-200">
                        <p>An account with this email already exists. <a href="#" @click.prevent="showLoginForm()" class="font-medium text-brand-600 underline hover:text-brand-500 dark:text-brand-400">Sign in instead</a></p>
                    </div>

                    {{-- Google covers both signing up and signing in, so it sits above
                         either form. `flow` sends the user to step 3 once Google returns;
                         the Step 1 memorial data is in the session and survives the trip. --}}
                    @if (\App\Helpers\SocialLoginHelper::googleLoginEnabled())
                        <a href="{{ route('google.redirect', ['flow' => 'memorial-signup']) }}"
                            class="btn btn-secondary btn-md btn-block w-full">
                            <svg width="20" height="20" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
                                <path d="M18.7511 10.1944C18.7511 9.47495 18.6915 8.94995 18.5626 8.40552H10.1797V11.6527H15.1003C15.0011 12.4597 14.4654 13.675 13.2749 14.4916L13.2582 14.6003L15.9087 16.6126L16.0924 16.6305C17.7788 15.1041 18.7511 12.8583 18.7511 10.1944Z" fill="#4285F4" />
                                <path d="M10.1788 18.75C12.5895 18.75 14.6133 17.9722 16.0915 16.6305L13.274 14.4916C12.5201 15.0068 11.5081 15.3666 10.1788 15.3666C7.81773 15.3666 5.81379 13.8402 5.09944 11.7305L4.99473 11.7392L2.23868 13.8295L2.20264 13.9277C3.67087 16.786 6.68674 18.75 10.1788 18.75Z" fill="#34A853" />
                                <path d="M5.10014 11.7305C4.91165 11.186 4.80257 10.6027 4.80257 9.99992C4.80257 9.3971 4.91165 8.81379 5.09022 8.26935L5.08523 8.1534L2.29464 6.02954L2.20333 6.0721C1.5982 7.25823 1.25098 8.5902 1.25098 9.99992C1.25098 11.4096 1.5982 12.7415 2.20333 13.9277L5.10014 11.7305Z" fill="#FBBC05" />
                                <path d="M10.1789 4.63331C11.8554 4.63331 12.9864 5.34303 13.6312 5.93612L16.1511 3.525C14.6035 2.11528 12.5895 1.25 10.1789 1.25C6.68676 1.25 3.67088 3.21387 2.20264 6.07218L5.08953 8.26943C5.81381 6.15972 7.81776 4.63331 10.1789 4.63331Z" fill="#EB4335" />
                            </svg>
                            Continue with Google
                        </a>
                        <div class="relative py-3 sm:py-5">
                            <div class="absolute inset-0 flex items-center">
                                <div class="w-full border-t border-gray-200 dark:border-gray-800"></div>
                            </div>
                            <div class="relative flex justify-center text-sm">
                                <span class="bg-white p-2 text-gray-400 sm:px-5 sm:py-2 dark:bg-gray-900">Or use your email</span>
                            </div>
                        </div>
                    @endif

                    {{-- Register form (signup) - shown by default when showLogin is false --}}
                    <div x-show="!showLogin">
                        <form id="step2-register-form" method="POST" action="{{ route('memorial.create.storeStep2Register') }}" class="space-y-5" @input="saveStep2ToStorage()" @change="saveStep2ToStorage()">
                        @csrf
                        <div>
                            <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300" for="name">Full name</label>
                            <input type="text" id="name" name="name" x-model="nameValue" required autocomplete="name"
                                @error('name') aria-invalid="true" aria-describedby="name-error" @enderror
                                class="dark:bg-gray-900/80 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 h-11 w-full rounded-lg border @error('name') border-red-500 dark:border-red-500 @else border-gray-300 dark:border-gray-600 @enderror bg-transparent px-4 py-2.5 text-sm text-gray-800 dark:text-gray-100 placeholder:text-gray-400 dark:placeholder:text-gray-500 focus:ring-3 focus:outline-hidden" />
                            @error('name')
                                <p id="name-error" class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                            @enderror
                        </div>
                        <div>
                            <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300" for="reg_email">Email</label>
                            <input type="email" id="reg_email" name="email" x-model="emailValue" required
                                @error('email') aria-invalid="true" aria-describedby="reg_email-error" @enderror
                                class="dark:bg-gray-900/80 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 h-11 w-full rounded-lg border @error('email') border-red-500 dark:border-red-500 @else border-gray-300 dark:border-gray-600 @enderror bg-transparent px-4 py-2.5 text-sm text-gray-800 dark:text-gray-100 placeholder:text-gray-400 dark:placeholder:text-gray-500 focus:ring-3 focus:outline-hidden"
                                @blur="checkEmail($event.target.value)" />
                            @error('email')
                                <p id="reg_email-error" class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                            @enderror
                        </div>
                        <div>
                            <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300" for="password">Password</label>
                            <div x-data="{ showPassword: false }" class="relative">
                                <input :type="showPassword ? 'text' : 'password'" id="password" name="password" required autocomplete="new-password"
                                    @error('password') aria-invalid="true" aria-describedby="password-error" @enderror
                                    class="dark:bg-gray-900/80 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 h-11 w-full rounded-lg border @error('password') border-red-500 dark:border-red-500 @else border-gray-300 dark:border-gray-600 @enderror bg-transparent py-2.5 pr-11 pl-4 text-sm text-gray-800 dark:text-gray-100 placeholder:text-gray-400 dark:placeholder:text-gray-500 focus:ring-3 focus:outline-hidden" />
                                <button type="button" @click="showPassword = !showPassword" class="absolute right-3 top-1/2 z-10 -translate-y-1/2 cursor-pointer text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200">
                                    <svg x-show="!showPassword" class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                                    <svg x-show="showPassword" x-cloak class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21"/></svg>
                                </button>
                            </div>
                            @error('password')
                                <p id="password-error" class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                            @enderror
                        </div>
                        <div>
                            <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300" for="password_confirmation">Confirm password</label>
                            <div x-data="{ showConfirmPassword: false }" class="relative">
                                <input :type="showConfirmPassword ? 'text' : 'password'" id="password_confirmation" name="password_confirmation" required autocomplete="new-password"
                                    class="dark:bg-gray-900/80 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 h-11 w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-transparent py-2.5 pr-11 pl-4 text-sm text-gray-800 dark:text-gray-100 placeholder:text-gray-400 dark:placeholder:text-gray-500 focus:ring-3 focus:outline-hidden" />
                                <button type="button" @click="showConfirmPassword = !showConfirmPassword" :aria-label="showConfirmPassword ? 'Hide password' : 'Show password'" class="absolute right-3 top-1/2 z-10 -translate-y-1/2 cursor-pointer text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200">
                                    <svg x-show="!showConfirmPassword" class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                                    <svg x-show="showConfirmPassword" x-cloak class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21"/></svg>
                                </button>
                            </div>
                            @error('password_confirmation')
                                <p id="password_confirmation-error" class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                            @enderror
                        </div>
                        <button type="submit" class="btn btn-primary btn-md btn-block w-full mt-2">
                            Create account & continue
                        </button>
                        </form>
                    </div>

                    {{-- Login form --}}
                    <form id="step2-login-form" x-show="showLogin" method="POST" action="{{ route('memorial.create.storeStep2Login') }}" class="space-y-5" x-cloak style="display: none;" @input="saveStep2ToStorage()" @change="saveStep2ToStorage()">
                        @csrf
                        <div>
                            <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300" for="login_email">Email</label>
                            <input type="email" id="login_email" name="email" x-model="emailValue" required
                                @error('email') aria-invalid="true" aria-describedby="login_email-error" @enderror
                                class="dark:bg-gray-900/80 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 h-11 w-full rounded-lg border @error('email') border-red-500 dark:border-red-500 @else border-gray-300 dark:border-gray-600 @enderror bg-transparent px-4 py-2.5 text-sm text-gray-800 dark:text-gray-100 placeholder:text-gray-400 dark:placeholder:text-gray-500 focus:ring-3 focus:outline-hidden" />
                            @error('email')
                                <p id="login_email-error" class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                            @enderror
                        </div>
                        <div>
                            <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300" for="login_password">Password</label>
                            <div x-data="{ showLoginPassword: false }" class="relative">
                                <input :type="showLoginPassword ? 'text' : 'password'" id="login_password" name="password" required
                                    @error('password') aria-invalid="true" aria-describedby="login_password-error" @enderror
                                    class="dark:bg-gray-900/80 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 h-11 w-full rounded-lg border @error('password') border-red-500 dark:border-red-500 @else border-gray-300 dark:border-gray-600 @enderror bg-transparent py-2.5 pr-11 pl-4 text-sm text-gray-800 dark:text-gray-100 placeholder:text-gray-400 dark:placeholder:text-gray-500 focus:ring-3 focus:outline-hidden" />
                                <button type="button" @click="showLoginPassword = !showLoginPassword" class="absolute right-3 top-1/2 z-10 -translate-y-1/2 cursor-pointer text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200">
                                    <svg x-show="!showLoginPassword" class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                                    <svg x-show="showLoginPassword" x-cloak class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21"/></svg>
                                </button>
                            </div>
                            @error('password')
                                <p id="login_password-error" class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                            @enderror
                        </div>
                        <div>
                            <label class="flex cursor-pointer items-center gap-2">
                                <input type="checkbox" name="remember" class="rounded border-gray-300 dark:border-gray-600 text-brand-600 focus:ring-brand-500" />
                                <span class="text-sm text-gray-700 dark:text-gray-300">Remember me</span>
                            </label>
                        </div>
                        <button type="submit" class="btn btn-primary btn-md btn-block w-full mt-2">
                            Sign in & continue
                        </button>
                        <p class="text-center text-sm text-gray-500 dark:text-gray-400">
                            <a href="#" @click.prevent="showLogin = false" class="text-brand-500 hover:text-brand-600 dark:text-brand-400 dark:hover:text-brand-300">Create new account instead</a>
                        </p>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

@push('scripts')
<script>
const STEP2_STORAGE_KEY = 'memorial_signup_step2';

function step2Form(serverData) {
    const sd = serverData || {};
    const saved = (() => {
        try {
            const s = localStorage.getItem(STEP2_STORAGE_KEY);
            return s ? JSON.parse(s) : null;
        } catch (e) { return null; }
    })();
    const nameVal = sd.name || (saved && saved.name) || '';
    const emailVal = sd.email || (saved && saved.email) || '';

    return {
        showLogin: saved?.showLogin || false,
        emailExists: false,
        nameValue: nameVal,
        emailValue: emailVal,

        init() {
            try { localStorage.removeItem('memorial_signup_step1'); } catch (e) {}
            const form = document.getElementById('step2-register-form') || document.getElementById('step2-login-form');
            if (form) {
                form.addEventListener('submit', () => {
                    try { localStorage.removeItem(STEP2_STORAGE_KEY); } catch (e) {}
                });
            }
        },

        saveStep2ToStorage() {
            const obj = {
                name: document.getElementById('name')?.value || this.nameValue,
                email: document.getElementById('reg_email')?.value || document.getElementById('login_email')?.value || this.emailValue,
                showLogin: this.showLogin
            };
            try {
                localStorage.setItem(STEP2_STORAGE_KEY, JSON.stringify(obj));
            } catch (e) {}
        },

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
                this.saveStep2ToStorage();
            });
        },
        showLoginForm() {
            this.emailValue = document.getElementById('reg_email')?.value || this.emailValue;
            this.showLogin = true;
            this.emailExists = false;
            this.saveStep2ToStorage();
        }
    };
}
</script>
@endpush
@endsection
