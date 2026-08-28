@extends('layouts.visitor')

@section('page')
    @include('sections.prose-page', [
        'page' => $page ?? null,
        'title' => 'Privacy Policy',
        'eyebrow' => 'Legal',
        'showUpdated' => true,
    ])
@endsection
