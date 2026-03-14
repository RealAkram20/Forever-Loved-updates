@php
    $appName = \App\Models\SystemSetting::get('branding.app_name', 'Forever Loved');
    $tagline = \App\Models\SystemSetting::get('branding.tagline', 'Celebrate lives that matter');
@endphp

<footer class="border-t border-gray-200 bg-gray-50 dark:border-gray-800 dark:bg-gray-950">
    <div class="mx-auto max-w-7xl px-4 py-12 sm:px-6 lg:px-8 lg:py-16">
        <div class="grid grid-cols-1 gap-8 sm:grid-cols-2 lg:grid-cols-4">

            {{-- Brand --}}
            <div class="sm:col-span-2 lg:col-span-1">
                <a href="{{ route('home') }}" class="inline-flex items-center gap-2">
                    <img class="dark:hidden h-8 w-auto" src="{{ \App\Helpers\BrandingHelper::logoUrl() }}" alt="{{ $appName }}" />
                    <img class="hidden dark:block h-8 w-auto" src="{{ \App\Helpers\BrandingHelper::logoDarkUrl() }}" alt="{{ $appName }}" />
                </a>
                <p class="mt-3 text-sm leading-relaxed text-gray-600 dark:text-gray-400 max-w-xs">
                    {{ $tagline }}. Create beautiful, lasting memorials to honor and celebrate the lives of those who matter most.
                </p>
            </div>

            {{-- Quick Links --}}
            <div>
                <h4 class="text-sm font-semibold uppercase tracking-wider text-gray-900 dark:text-white/90">Quick Links</h4>
                <ul class="mt-4 space-y-2.5">
                    <li><a href="{{ route('home') }}" class="text-sm text-gray-600 hover:text-brand-500 dark:text-gray-400 dark:hover:text-brand-400 transition">Home</a></li>
                    <li><a href="{{ route('memorial.directory') }}" class="text-sm text-gray-600 hover:text-brand-500 dark:text-gray-400 dark:hover:text-brand-400 transition">Find Memorial</a></li>
                    <li><a href="{{ route('memorial.create.step1') }}" class="text-sm text-gray-600 hover:text-brand-500 dark:text-gray-400 dark:hover:text-brand-400 transition">Create Memorial</a></li>
                    <li><a href="{{ route('pricing') }}" class="text-sm text-gray-600 hover:text-brand-500 dark:text-gray-400 dark:hover:text-brand-400 transition">Pricing</a></li>
                </ul>
            </div>

            {{-- Company --}}
            <div>
                <h4 class="text-sm font-semibold uppercase tracking-wider text-gray-900 dark:text-white/90">Company</h4>
                <ul class="mt-4 space-y-2.5">
                    <li><a href="{{ route('about') }}" class="text-sm text-gray-600 hover:text-brand-500 dark:text-gray-400 dark:hover:text-brand-400 transition">About Us</a></li>
                    <li><a href="{{ route('contact') }}" class="text-sm text-gray-600 hover:text-brand-500 dark:text-gray-400 dark:hover:text-brand-400 transition">Contact Us</a></li>
                    <li><a href="{{ route('privacy-policy') }}" class="text-sm text-gray-600 hover:text-brand-500 dark:text-gray-400 dark:hover:text-brand-400 transition">Privacy Policy</a></li>
                    <li><a href="{{ route('terms-of-use') }}" class="text-sm text-gray-600 hover:text-brand-500 dark:text-gray-400 dark:hover:text-brand-400 transition">Terms of Use</a></li>
                </ul>
            </div>

            {{-- Connect --}}
            <div>
                <h4 class="text-sm font-semibold uppercase tracking-wider text-gray-900 dark:text-white/90">Connect</h4>
                <ul class="mt-4 space-y-2.5">
                    @php $contactEmail = \App\Models\SystemSetting::get('smtp.from_address'); @endphp
                    @if ($contactEmail)
                        <li class="flex items-center gap-2">
                            <svg class="h-4 w-4 shrink-0 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
                            <a href="mailto:{{ $contactEmail }}" class="text-sm text-gray-600 hover:text-brand-500 dark:text-gray-400 dark:hover:text-brand-400 transition">{{ $contactEmail }}</a>
                        </li>
                    @endif
                    <li>
                        <a href="{{ route('contact') }}" class="inline-flex items-center gap-1.5 text-sm text-gray-600 hover:text-brand-500 dark:text-gray-400 dark:hover:text-brand-400 transition">
                            <svg class="h-4 w-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"/></svg>
                            Send a Message
                        </a>
                    </li>
                </ul>
            </div>
        </div>

        {{-- Bottom bar --}}
        <div class="mt-10 border-t border-gray-200 dark:border-gray-800 pt-6 flex flex-col items-center gap-2 sm:flex-row sm:justify-between">
            <p class="text-xs text-gray-500 dark:text-gray-500">&copy; {{ date('Y') }} {{ $appName }}. All rights reserved.</p>
            <div class="flex items-center gap-4">
                <a href="{{ route('privacy-policy') }}" class="text-xs text-gray-500 hover:text-gray-700 dark:hover:text-gray-300 transition">Privacy</a>
                <a href="{{ route('terms-of-use') }}" class="text-xs text-gray-500 hover:text-gray-700 dark:hover:text-gray-300 transition">Terms</a>
            </div>
        </div>
    </div>
</footer>
