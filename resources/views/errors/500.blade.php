@extends('errors.layout')

@section('code', '500')
@section('title', 'Something went wrong')
@section('message', "We hit an unexpected problem on our side. It has been recorded and we'll look into it — please try again in a moment.")

@section('actions')
    <button type="button" class="btn btn-primary" onclick="location.reload()">Try again</button>
@endsection
