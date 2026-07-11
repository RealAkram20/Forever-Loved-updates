@extends('errors.layout')

@section('code', '503')
@section('title', 'Back in a moment')
@section('message', "We're doing a little maintenance right now. This usually only takes a few minutes — thank you for your patience.")

@section('actions')
    <button type="button" class="btn btn-primary" onclick="location.reload()">Refresh</button>
@endsection
