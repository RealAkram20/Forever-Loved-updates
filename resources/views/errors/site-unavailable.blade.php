{{--
    Shown on every page of a suspended reseller's site except the memorials themselves.

    No "go to homepage" action, unlike the other error pages: the homepage is the thing that is
    closed, and offering it would send the visitor in a circle. No link to the platform either
    — this is a white-labeled address, and handing its visitors to us is the exact bug
    ThemeConformanceTest exists to catch.

    What it does say is the one thing a visitor here actually needs: if they were following a
    link to somebody's memorial, that link still works. Most people who land on this page were
    on their way to one.
--}}
@extends('errors.layout')

@section('code', '503')
@section('title', 'This site is unavailable')
@section('message', 'This website is temporarily unavailable. If you were following a link to a memorial, that link still works — open it again and the page will be there.')
