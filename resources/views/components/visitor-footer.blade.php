@php
    $appName = \App\Models\SystemSetting::get('branding.app_name', 'Forever Loved');
    $tagline = \App\Models\SystemSetting::get('branding.tagline', 'Celebrate lives that matter');
    $footerDescription = \App\Models\SystemSetting::get(
        'branding.footer_description',
        'Creating the digital home for every life story—with honor, love, and care. Here to help you share the lives that matter, forever and all.'
    );
    $supportHours = \App\Models\SystemSetting::get('branding.support_hours', 'M – S 9:00am – 5pm');
    $footerQuickItems = $footerQuickItems ?? collect();
    $footerCompanyItems = $footerCompanyItems ?? collect();
@endphp

<footer class="border-t border-gray-900/[0.06] dark:border-gray-800">
    <div class="mx-auto max-w-7xl px-4 py-12 sm:px-6 lg:px-8 lg:py-16">
        <div class="grid grid-cols-1 gap-10 sm:grid-cols-2 lg:grid-cols-[1.4fr_1fr_1fr_1.2fr]">

            {{-- Brand --}}
            <div>
                <a href="{{ route('home') }}" class="inline-flex items-center gap-2">
                    <img class="dark:hidden h-14 lg:h-16 w-auto object-contain" src="{{ \App\Helpers\BrandingHelper::logoUrl() }}" alt="{{ $appName }}" />
                    <img class="hidden dark:block h-14 lg:h-16 w-auto object-contain" src="{{ \App\Helpers\BrandingHelper::logoDarkUrl() }}" alt="{{ $appName }}" />
                </a>
                <p class="mt-4 text-sm leading-relaxed text-gray-600 dark:text-gray-400 max-w-xs">
                    {{ $footerDescription }}
                </p>
            </div>

            {{-- Quick Links --}}
            <div>
                <h4 class="text-xs font-semibold uppercase tracking-[0.14em] text-gray-900 dark:text-white/90">{{ ($footerQuickMenu ?? null)?->label ?? 'Quick Links' }}</h4>
                <ul class="mt-5 space-y-3">
                    @forelse ($footerQuickItems as $item)
                        <li>
                            <a href="{{ $item->resolvedUrl() }}"
                               @if($item->open_in_new_tab) target="_blank" rel="noopener noreferrer" @endif
                               class="footer-link">{{ $item->label }}</a>
                        </li>
                    @empty
                        <li><a href="{{ route('home') }}" class="footer-link">Home</a></li>
                        <li><a href="{{ route('memorial.directory') }}" class="footer-link">Find Memorial</a></li>
                        <li><a href="{{ route('memorial.create.step1') }}" class="footer-link">Create Memorial</a></li>
                        <li><a href="{{ route('pricing') }}" class="footer-link">Pricing</a></li>
                    @endforelse
                </ul>
            </div>

            {{-- Company --}}
            <div>
                <h4 class="text-xs font-semibold uppercase tracking-[0.14em] text-gray-900 dark:text-white/90">{{ ($footerCompanyMenu ?? null)?->label ?? 'Company' }}</h4>
                <ul class="mt-5 space-y-3">
                    @forelse ($footerCompanyItems as $item)
                        <li>
                            <a href="{{ $item->resolvedUrl() }}"
                               @if($item->open_in_new_tab) target="_blank" rel="noopener noreferrer" @endif
                               class="footer-link">{{ $item->label }}</a>
                        </li>
                    @empty
                        <li><a href="{{ route('about') }}" class="footer-link">About Us</a></li>
                        <li><a href="{{ route('contact') }}" class="footer-link">Contact Us</a></li>
                        <li><a href="{{ route('privacy-policy') }}" class="footer-link">Privacy Policy</a></li>
                        <li><a href="{{ route('terms-of-use') }}" class="footer-link">Terms of Use</a></li>
                    @endforelse
                </ul>
            </div>

            {{-- Support --}}
            <div>
                <h4 class="text-xs font-semibold uppercase tracking-[0.14em] text-gray-900 dark:text-white/90">Support</h4>
                <ul class="mt-5 space-y-3.5">
                    @php $contactEmail = \App\Models\SystemSetting::get('smtp.from_address'); @endphp
                    @if ($contactEmail)
                        <li class="flex items-center gap-2.5">
                            <svg class="lucide h-4 w-4 shrink-0 text-gray-400 dark:text-gray-500" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24" aria-hidden="true"><rect width="20" height="16" x="2" y="4" rx="2"/><path d="m22 7-8.97 5.7a1.94 1.94 0 0 1-2.06 0L2 7"/></svg>
                            <a href="mailto:{{ $contactEmail }}" class="text-sm text-gray-600 hover:text-brand-500 dark:text-gray-400 dark:hover:text-brand-400 transition">{{ $contactEmail }}</a>
                        </li>
                    @endif
                    @if ($supportHours !== '')
                        <li class="flex items-center gap-2.5">
                            <svg class="lucide h-4 w-4 shrink-0 text-gray-400 dark:text-gray-500" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                            <span class="text-sm text-gray-600 dark:text-gray-400">{{ $supportHours }}</span>
                        </li>
                    @endif
                    <li class="flex items-center gap-2.5">
                        <svg class="lucide h-4 w-4 shrink-0 text-gray-400 dark:text-gray-500" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24" aria-hidden="true"><path d="M7.9 20A9 9 0 1 0 4 16.1L2 22Z"/></svg>
                        <a href="{{ route('contact') }}" class="text-sm text-gray-600 hover:text-brand-500 dark:text-gray-400 dark:hover:text-brand-400 transition">Send a Message</a>
                    </li>
                </ul>
            </div>
        </div>

        {{-- Bottom bar --}}
        <div class="mt-12 border-t border-gray-900/[0.06] dark:border-gray-800 pt-6">
            <p class="flex flex-wrap items-center justify-center gap-1.5 text-center text-sm text-gray-500 dark:text-gray-500">
                &copy; {{ date('Y') }} {{ $appName }}. Built with
                <svg class="lucide inline h-4 w-4 text-[var(--color-accent-light)]" fill="currentColor" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24" aria-hidden="true"><path d="M19 14c1.49-1.46 3-3.21 3-5.5A5.5 5.5 0 0 0 16.5 3c-1.76 0-3 .5-4.5 2-1.5-1.5-2.74-2-4.5-2A5.5 5.5 0 0 0 2 8.5c0 2.3 1.5 4.05 3 5.5l7 7Z"/></svg>
                <span class="sr-only">love</span>. For all.
            </p>
        </div>
    </div>
</footer>
