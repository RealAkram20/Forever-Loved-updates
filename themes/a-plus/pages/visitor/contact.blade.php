@extends('layouts.visitor')

{{--
    Contact, in A-Plus.

    Every functional part of the base view is kept exactly as it was — the same POST to
    `contact.send`, the same field names, the same `@error` bags, the same session flashes and
    the same Alpine `sending` guard on the submit button. A themed contact form that quietly
    drops a validation message is worse than an unthemed one, so nothing here touches behaviour.

    What changes is the surfaces. The base draws `rounded-2xl` grey cards with a white form
    panel on top; this design has no cards, so the aside becomes two hairline-ruled blocks and
    the form sits directly on the page. The button is the template's own rather than the
    platform's `.btn-primary`, which carries the platform's brand colour, not the reseller's.
--}}

@php
    $contactEmail = \App\Helpers\BrandingHelper::contactEmail();
    $phone = \App\Helpers\ThemeSetting::get('branding.contact_phone');
    $address = \App\Helpers\ThemeSetting::get('branding.contact_address');

    $addressLines = collect(preg_split('/\r\n|\r|\n/', (string) $address))
        ->map(fn ($l) => trim($l))->filter()->values();

    $field = 'h-12 w-full rounded-[var(--t-btn-radius)] border border-[var(--ap-line)] bg-white px-4 text-[14px] text-[var(--ap-ink)] placeholder:text-[var(--ap-ink-soft)]/70 focus:border-[var(--ap-blue)] focus:outline-none focus:ring-2 focus:ring-[var(--ap-blue)]/20';
    $label = 'mb-2 block text-[13px] font-semibold text-[var(--ap-ink)]';
@endphp

@section('page')
    @include('sections.page-banner', [
        'title' => 'Contact Us',
        'eyebrow' => 'Get in touch',
        'sub' => "Have a question or need assistance? We'd love to hear from you.",
    ])

    <section class="bg-[var(--ap-paper)] py-[var(--t-pad-md)]">
        <div class="mx-auto max-w-6xl px-4 sm:px-6 lg:px-8">
            <div class="grid gap-12 lg:grid-cols-5 lg:gap-16">
                {{-- Reach out --}}
                <div class="lg:col-span-2">
                    <h2 class="t-heading text-[22px] text-[var(--ap-ink)]">Reach Out</h2>
                    <span class="ap-rule mt-4" aria-hidden="true"></span>

                    <div class="mt-7 space-y-5 text-[14px] text-[var(--ap-ink-soft)]">
                        @if ($addressLines->isNotEmpty())
                            <p class="flex items-start gap-3.5">
                                <x-icon name="map-pin" class="mt-0.5 h-5 w-5 shrink-0 text-[var(--ap-blue)]" />
                                <span class="t-body">
                                    @foreach ($addressLines as $line)
                                        {{ $line }}@if (! $loop->last)<br>@endif
                                    @endforeach
                                </span>
                            </p>
                        @endif

                        @if ($phone)
                            <p class="flex items-center gap-3.5">
                                <x-icon name="phone" class="h-5 w-5 shrink-0 text-[var(--ap-blue)]" />
                                <a href="tel:{{ preg_replace('/[^0-9+]/', '', $phone) }}"
                                    class="transition-colors duration-200 ease-out hover:text-[var(--ap-blue)]">{{ $phone }}</a>
                            </p>
                        @endif

                        @if ($contactEmail)
                            <p class="flex items-center gap-3.5">
                                <x-icon name="mail" class="h-5 w-5 shrink-0 text-[var(--ap-blue)]" />
                                <a href="mailto:{{ $contactEmail }}"
                                    class="break-all transition-colors duration-200 ease-out hover:text-[var(--ap-blue)]">{{ $contactEmail }}</a>
                            </p>
                        @endif

                        <p class="flex items-start gap-3.5">
                            <x-icon name="clock" class="mt-0.5 h-5 w-5 shrink-0 text-[var(--ap-blue)]" />
                            <span class="t-body">We typically respond within 24 hours.</span>
                        </p>
                    </div>

                    <div class="mt-9 border-t border-[var(--ap-line)] pt-7">
                        <h2 class="t-heading text-[18px] text-[var(--ap-ink)]">Quick Links</h2>
                        <ul class="mt-4 space-y-2.5 text-[14px]">
                            {{-- route(), not SiteUrl: these three are application screens that
                                 exist on every host, and the base view links them the same way. --}}
                            <li><a href="{{ route('pricing') }}" class="text-[var(--ap-ink-soft)] transition-colors duration-200 ease-out hover:text-[var(--ap-blue)]">View Pricing Plans</a></li>
                            <li><a href="{{ route('memorial.create.step1') }}" class="text-[var(--ap-ink-soft)] transition-colors duration-200 ease-out hover:text-[var(--ap-blue)]">Create a Memorial</a></li>
                            <li><a href="{{ route('memorial.directory') }}" class="text-[var(--ap-ink-soft)] transition-colors duration-200 ease-out hover:text-[var(--ap-blue)]">Find a Memorial</a></li>
                        </ul>
                    </div>
                </div>

                {{-- The form --}}
                <div class="lg:col-span-3">
                    @if (session('success'))
                        <div class="mb-7 rounded-[var(--t-radius)] border border-green-200 bg-green-50 px-5 py-4">
                            <p class="text-[14px] font-medium text-green-800">{{ session('success') }}</p>
                        </div>
                    @endif

                    @if (session('error'))
                        <div class="mb-7 rounded-[var(--t-radius)] border border-red-200 bg-red-50 px-5 py-4">
                            <p class="text-[14px] font-medium text-red-800">{{ session('error') }}</p>
                        </div>
                    @endif

                    <form action="{{ route('contact.send') }}" method="POST" class="space-y-6"
                        x-data="{ sending: false }" @submit="sending = true">
                        @csrf
                        @include('partials.honeypot')

                        <div class="grid gap-6 sm:grid-cols-2">
                            <div>
                                <label for="name" class="{{ $label }}">Your Name</label>
                                <input type="text" id="name" name="name" value="{{ old('name') }}" required autocomplete="name"
                                    placeholder="Your full name" class="{{ $field }}" />
                                @error('name')
                                    <p class="mt-1.5 text-[12px] text-red-600">{{ $message }}</p>
                                @enderror
                            </div>

                            <div>
                                <label for="email" class="{{ $label }}">Email Address</label>
                                <input type="email" id="email" name="email" value="{{ old('email') }}" required
                                    placeholder="Your email address" class="{{ $field }}" />
                                @error('email')
                                    <p class="mt-1.5 text-[12px] text-red-600">{{ $message }}</p>
                                @enderror
                            </div>
                        </div>

                        <div>
                            <label for="subject" class="{{ $label }}">Subject</label>
                            <input type="text" id="subject" name="subject" value="{{ old('subject') }}" required
                                placeholder="How can we help?" class="{{ $field }}" />
                            @error('subject')
                                <p class="mt-1.5 text-[12px] text-red-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <div>
                            <label for="message" class="{{ $label }}">Message</label>
                            <textarea id="message" name="message" rows="6" required
                                placeholder="Tell us more about your question or request..."
                                class="w-full resize-none rounded-[var(--t-radius)] border border-[var(--ap-line)] bg-white px-4 py-3.5 text-[14px] text-[var(--ap-ink)] placeholder:text-[var(--ap-ink-soft)]/70 focus:border-[var(--ap-blue)] focus:outline-none focus:ring-2 focus:ring-[var(--ap-blue)]/20">{{ old('message') }}</textarea>
                            @error('message')
                                <p class="mt-1.5 text-[12px] text-red-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <button type="submit" :disabled="sending"
                            class="t-btn gap-2 bg-[var(--ap-blue)] text-white transition-[filter] duration-200 ease-out hover:brightness-110 disabled:opacity-50">
                            <svg x-show="sending" x-cloak class="h-4 w-4 animate-spin" fill="none" viewBox="0 0 24 24" aria-hidden="true">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" />
                            </svg>
                            <span x-text="sending ? 'Sending...' : 'Send Message'">Send Message</span>
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </section>
@endsection
