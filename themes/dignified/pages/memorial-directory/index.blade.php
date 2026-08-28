@extends('layouts.visitor')

{{--
    Find a Memorial.

    The base version extends the fullscreen layout directly, which means it renders the header
    but no footer and sits on flat white. That is survivable on the platform's own site; on a
    reseller's it drops them out of their own design halfway through a visit — themed header,
    unthemed page, no footer at all.

    Extending layouts.visitor puts it back inside the site. The search itself is the shared
    directory partial, untouched: it carries real behaviour and does not want a second copy.
--}}

@section('page')
    @include('sections.page-banner', [
        'title' => 'Find a Memorial',
        'eyebrow' => 'Remembrance',
        'sub' => 'Search by name to find a memorial page and leave a tribute.',
    ])

    <section class="bg-[var(--dg-paper)] py-12 sm:py-14">
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            @include('pages.memorial-directory.partials.directory-app')
        </div>
    </section>
@endsection
