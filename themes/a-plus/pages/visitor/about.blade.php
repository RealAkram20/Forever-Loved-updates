@extends('layouts.visitor')

@section('page')
    @include('sections.prose-page', [
        'page' => $page ?? null,
        'title' => 'About Us',
        'eyebrow' => 'Who we are',
    ])
@endsection
