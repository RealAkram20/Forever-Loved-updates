<section class="bg-white dark:bg-gray-900 py-16 sm:py-20">
    <div class="mx-auto max-w-5xl px-4 sm:px-6 lg:px-8">
        <div class="text-center max-w-2xl mx-auto mb-12">
            @if (!empty($props['eyebrow']))
                <p class="ap-eyebrow text-sm font-semibold uppercase tracking-wider text-brand-600 dark:text-brand-400">{{ $props['eyebrow'] }}</p>
            @endif
            <h1 class="ap-title mt-2 text-3xl font-bold text-gray-900 dark:text-white sm:text-4xl">{{ $props['title'] ?? 'Contact Us' }}</h1>
            @if (!empty($props['intro']))
                <p class="ap-lead mt-4 text-gray-600 dark:text-gray-400">{{ $props['intro'] }}</p>
            @endif
        </div>

        <div class="grid gap-10 lg:grid-cols-5">
            <div class="lg:col-span-2 space-y-6">
                <div class="rounded-2xl border border-gray-100 dark:border-gray-800 bg-gray-50 dark:bg-gray-800/50 p-6">
                    <h3 class="text-base font-semibold text-gray-900 dark:text-white mb-4">{{ $props['sidebar_title'] ?? 'Reach Out' }}</h3>
                    <div class="space-y-4">
                        @php $contactEmail = \App\Models\SystemSetting::get('smtp.from_address'); @endphp
                        @if ($contactEmail)
                        <div class="flex items-start gap-3">
                            <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-brand-50 dark:bg-brand-500/10 text-brand-600 dark:text-brand-400">
                                <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
                            </div>
                            <div>
                                <p class="text-sm font-medium text-gray-900 dark:text-white">Email</p>
                                <a href="mailto:{{ $contactEmail }}" class="text-sm text-brand-600 dark:text-brand-400 hover:underline">{{ $contactEmail }}</a>
                            </div>
                        </div>
                        @endif
                        <div class="flex items-start gap-3">
                            <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-brand-50 dark:bg-brand-500/10 text-brand-600 dark:text-brand-400">
                                <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                            </div>
                            <div>
                                <p class="text-sm font-medium text-gray-900 dark:text-white">Response Time</p>
                                <p class="text-sm text-gray-500 dark:text-gray-400">{{ $props['response_blurb'] ?? 'We typically respond within 24 hours.' }}</p>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="rounded-2xl border border-gray-100 dark:border-gray-800 bg-gray-50 dark:bg-gray-800/50 p-6">
                    <h3 class="text-base font-semibold text-gray-900 dark:text-white mb-2">Quick Links</h3>
                    <ul class="space-y-2">
                        <li><a href="{{ route('pricing') }}" class="text-sm text-brand-600 dark:text-brand-400 hover:underline">View Pricing Plans</a></li>
                        <li><a href="{{ route('memorial.create.step1') }}" class="text-sm text-brand-600 dark:text-brand-400 hover:underline">Create a Memorial</a></li>
                        <li><a href="{{ \App\Support\StandardPages::urlFor(\App\Models\Page::SLUG_FIND_MEMORIAL) }}" class="text-sm text-brand-600 dark:text-brand-400 hover:underline">Find a Memorial</a></li>
                    </ul>
                </div>
            </div>

            <div class="lg:col-span-3">
                @if (session('success'))
                    <div class="mb-6 rounded-xl bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-800 px-5 py-4">
                        <div class="flex items-center gap-2">
                            <svg class="h-5 w-5 text-green-500" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/></svg>
                            <p class="text-sm font-medium text-green-700 dark:text-green-400">{{ session('success') }}</p>
                        </div>
                    </div>
                @endif

                @if (session('error'))
                    <div class="mb-6 rounded-xl bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 px-5 py-4">
                        <div class="flex items-center gap-2">
                            <svg class="h-5 w-5 text-red-500" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"/></svg>
                            <p class="text-sm font-medium text-red-700 dark:text-red-400">{{ session('error') }}</p>
                        </div>
                    </div>
                @endif

                <form action="{{ route('contact.send') }}" method="POST"
                      class="rounded-2xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 p-6 sm:p-8 space-y-5"
                      x-data="{ sending: false }"
                      @submit="sending = true">
                    @csrf

                    <div class="grid gap-5 sm:grid-cols-2">
                        <div>
                            <label for="pb-contact-name" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Your Name</label>
                            <input type="text" id="pb-contact-name" name="name" value="{{ old('name') }}" required autocomplete="name"
                                   placeholder="Your full name"
                                   class="h-11 w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-transparent px-4 text-sm text-gray-800 dark:text-white/90 placeholder:text-gray-400 focus:border-brand-300 focus:ring-3 focus:ring-brand-500/10 focus:outline-hidden" />
                            @error('name')
                                <p class="mt-1 text-xs text-red-500">{{ $message }}</p>
                            @enderror
                        </div>
                        <div>
                            <label for="pb-contact-email" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Email Address</label>
                            <input type="email" id="pb-contact-email" name="email" value="{{ old('email') }}" required
                                   placeholder="Your email address"
                                   class="h-11 w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-transparent px-4 text-sm text-gray-800 dark:text-white/90 placeholder:text-gray-400 focus:border-brand-300 focus:ring-3 focus:ring-brand-500/10 focus:outline-hidden" />
                            @error('email')
                                <p class="mt-1 text-xs text-red-500">{{ $message }}</p>
                            @enderror
                        </div>
                    </div>

                    <div>
                        <label for="pb-contact-subject" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Subject</label>
                        <input type="text" id="pb-contact-subject" name="subject" value="{{ old('subject') }}" required
                               placeholder="How can we help?"
                               class="h-11 w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-transparent px-4 text-sm text-gray-800 dark:text-white/90 placeholder:text-gray-400 focus:border-brand-300 focus:ring-3 focus:ring-brand-500/10 focus:outline-hidden" />
                        @error('subject')
                            <p class="mt-1 text-xs text-red-500">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label for="pb-contact-message" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Message</label>
                        <textarea id="pb-contact-message" name="message" rows="5" required
                                  placeholder="Tell us more about your question or request..."
                                  class="w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-transparent px-4 py-3 text-sm text-gray-800 dark:text-white/90 placeholder:text-gray-400 focus:border-brand-300 focus:ring-3 focus:ring-brand-500/10 focus:outline-hidden resize-none">{{ old('message') }}</textarea>
                        @error('message')
                            <p class="mt-1 text-xs text-red-500">{{ $message }}</p>
                        @enderror
                    </div>

                    <button type="submit"
                            :disabled="sending"
                            class="btn btn-primary btn-lg disabled:opacity-50">
                        <svg x-show="!sending" class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
                        <svg x-show="sending" x-cloak class="h-4 w-4 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
                        <span x-show="!sending">{{ $props['button_label'] ?? 'Send Message' }}</span>
                        <span x-show="sending" x-cloak>Sending...</span>
                    </button>
                </form>
            </div>
        </div>
    </div>
</section>
