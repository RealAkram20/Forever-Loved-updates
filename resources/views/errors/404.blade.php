@extends('errors.layout')

@section('code', '404')
@section('title', 'Page not found')
@section('message', "We couldn't find the page you were looking for. It may have been moved, or the link may be out of date.")

@section('actions')
    {{-- SiteUrl, not url(): on a reseller's site "homepage" means theirs. url('/') is pinned
         to APP_URL, so a mistyped address on a white-labeled domain handed the visitor to us. --}}
    <a href="{{ \App\Support\SiteUrl::to('') }}" class="btn btn-primary">Go to homepage</a>
@endsection
