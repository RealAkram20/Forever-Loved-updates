@extends('layouts.visitor')

{{-- Anything the reseller adds by hand. No eyebrow: we do not know what kind of page it is,
     and inventing a category above someone's own title is worse than leaving the space. --}}
@section('page')
    @include('sections.prose-page', [
        'page' => $page ?? null,
        'title' => $page?->title ?? 'Page',
    ])
@endsection
