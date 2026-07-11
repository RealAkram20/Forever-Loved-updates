@extends('errors.layout')

@section('code', '419')
@section('title', 'Your session expired')
@section('message', "For your security, the page timed out. Go back and try again — anything you typed should still be there.")

@section('actions')
    <button type="button" class="btn btn-primary" onclick="history.back()">Go back and retry</button>
@endsection
