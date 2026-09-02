@extends('layouts.visitor')

{{--
    Contact.

    The details column comes first on mobile and sits left on desktop, ahead of the form,
    because a family arranging a funeral this week wants a phone number, not a web form. The
    form is for everyone else.

    Ends with the Contact & Location widget — the same one the home page uses and the same one
    a reseller can drop on any page they build. Its content comes from Settings, so there is
    one address on the site no matter how many pages show it.
--}}

@section('page')
    @include('sections.page-banner', [
        'title' => 'Contact Us',
        'eyebrow' => 'Get in touch',
        'sub' => 'However you would rather reach us. If it is urgent, please call.',
    ])

    <section class="bg-[var(--dg-paper)] py-12 sm:py-14">
        <div class="mx-auto max-w-3xl px-4 sm:px-6 lg:px-8">
            @if (session('success'))
                <div class="mb-8 border border-[var(--dg-gold)] bg-white px-5 py-4 text-[15px] text-[var(--dg-ink)]">
                    {{ session('success') }}
                </div>
            @endif

            <h2 class="dg-caps text-[24px] leading-none text-[var(--dg-ink)]">Send us a message</h2>
            <span aria-hidden="true" class="mt-3 block h-[3px] w-10 bg-[var(--dg-red)]"></span>

            <form action="{{ route('contact.send') }}" method="POST" class="mt-8 space-y-5">
                @csrf
                @include('partials.honeypot')

                @php
                    $field = 'h-11 w-full border border-[#d4d4d4] bg-white px-4 text-[14px] text-[var(--dg-ink)] placeholder:text-[#9a9a9a] focus:border-[var(--dg-gold)] focus:outline-none';
                    $label = 'mb-2 block text-[11px] font-bold uppercase tracking-[0.14em] text-[#5c5c5c]';
                @endphp

                <div class="grid gap-5 sm:grid-cols-2">
                    <div>
                        <label for="c-name" class="{{ $label }}">Your name</label>
                        <input id="c-name" type="text" name="name" required maxlength="100" value="{{ old('name') }}" class="{{ $field }}" />
                        @error('name')<p class="mt-1.5 text-[13px] text-[var(--dg-red)]">{{ $message }}</p>@enderror
                    </div>

                    <div>
                        <label for="c-email" class="{{ $label }}">Email address</label>
                        <input id="c-email" type="email" name="email" required maxlength="255" value="{{ old('email') }}" class="{{ $field }}" />
                        @error('email')<p class="mt-1.5 text-[13px] text-[var(--dg-red)]">{{ $message }}</p>@enderror
                    </div>
                </div>

                <div>
                    <label for="c-subject" class="{{ $label }}">Subject</label>
                    <input id="c-subject" type="text" name="subject" required maxlength="255" value="{{ old('subject') }}" class="{{ $field }}" />
                    @error('subject')<p class="mt-1.5 text-[13px] text-[var(--dg-red)]">{{ $message }}</p>@enderror
                </div>

                <div>
                    <label for="c-message" class="{{ $label }}">Message</label>
                    <textarea id="c-message" name="message" rows="6" required maxlength="5000"
                        class="w-full border border-[#d4d4d4] bg-white px-4 py-3 text-[14px] leading-relaxed text-[var(--dg-ink)] placeholder:text-[#9a9a9a] focus:border-[var(--dg-gold)] focus:outline-none">{{ old('message') }}</textarea>
                    @error('message')<p class="mt-1.5 text-[13px] text-[var(--dg-red)]">{{ $message }}</p>@enderror
                </div>

                <button type="submit"
                    class="inline-flex items-center bg-[var(--dg-red)] px-8 py-3.5 text-[11px] font-bold uppercase tracking-[0.16em] text-white transition-[filter,transform] duration-200 ease-out hover:brightness-110 active:scale-[0.98]">
                    Send message
                </button>
            </form>
        </div>
    </section>

    @include('page-builder.widgets.section-contact', [
        'props' => \App\PageBuilder\Widgets\SectionContactWidget::defaultProps(),
    ])
@endsection
