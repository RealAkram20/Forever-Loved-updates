@extends('layouts.visitor')

@section('page')
    @include('sections.prose-page', [
        'page' => $page ?? null,
        'title' => 'Terms of Use',
        'eyebrow' => 'Legal',
        'showUpdated' => true,
    ])
@endsection
