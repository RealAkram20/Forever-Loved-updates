@extends('errors.layout')

@section('code', '404')
@section('title', 'Page not found')
@section('message', "We couldn't find the page you were looking for. It may have been moved, or the link may be out of date.")

@section('actions')
    <a href="{{ url('/') }}" class="btn btn-primary">Go to homepage</a>
@endsection
