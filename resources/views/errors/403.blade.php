@extends('errors.layout')

@section('code', '403')
@section('title', 'Access denied')
@section('message', "You don't have permission to view this page. If you believe this is a mistake, try signing in with a different account.")

@section('actions')
    {{-- A reseller's client signs in on their site, not ours. Under the /r/{slug} fallback
         that is a real reseller-scoped route; on their own host it is the shared one. --}}
    <a href="{{ rescue(fn () => \App\Support\SiteUrl::to('login'), url('/login'), false) }}" class="btn btn-primary">Sign in</a>
@endsection
