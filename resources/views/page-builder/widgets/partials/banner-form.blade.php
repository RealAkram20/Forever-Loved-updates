{{--
    The three-field enquiry form a banner can carry.

    Posts to the site's existing contact endpoint rather than inventing one, so the throttle,
    the validation and where the mail lands are the same as the contact page's — a second
    submission path would be a second place for enquiries to go missing.

    Shared by the plain banner and every theme's, because the *markup* is behavioural: the
    field names, the CSRF token and the honeypot have to match what the controller expects.
    The honeypot itself is `partials.honeypot`, included below -- until 2026-09-02 this
    sentence described a field that existed in no form on the site.
    Themes restyle it by passing classes, not by rewriting it.
--}}
@php
    $action = \App\Support\SiteUrl::to('contact');
    $inputCls = $inputCls ?? ($onImage
        ? 'h-11 w-full border border-white/25 bg-white/10 px-4 text-sm text-white placeholder:text-white/55 focus:border-white/60 focus:outline-none'
        : 'h-11 w-full rounded-lg border border-gray-300 bg-white px-4 text-sm text-gray-800 placeholder:text-gray-400 focus:border-brand-400 focus:outline-none');
    $buttonCls = $buttonCls ?? 'btn btn-primary btn-md h-11 px-8';
@endphp

<form action="{{ $action }}" method="POST" class="t-banner-form mt-7 grid gap-2.5 sm:grid-cols-[1fr_1fr_1.4fr_auto]">
    @csrf
    @include('partials.honeypot')

    <input type="text" name="name" required maxlength="100" value="{{ old('name') }}" placeholder="Your Name" aria-label="Your name" class="{{ $inputCls }}" />
    <input type="email" name="email" required maxlength="255" value="{{ old('email') }}" placeholder="Your Email" aria-label="Your email" class="{{ $inputCls }}" />
    <input type="text" name="message" required maxlength="5000" value="{{ old('message') }}" placeholder="Your Message" aria-label="Your message" class="{{ $inputCls }}" />

    {{-- The contact controller requires a subject; a banner form has no room to ask for one,
         so it says where the enquiry came from instead of leaving the field empty. --}}
    <input type="hidden" name="subject" value="Website enquiry" />

    <button type="submit" class="{{ $buttonCls }}">{{ $props['form_button_label'] ?? 'Submit' }}</button>
</form>

@if ($errors->any())
    <p class="mt-2 text-sm {{ $onImage ? 'text-white/90' : 'text-red-600' }}">{{ $errors->first() }}</p>
@endif
