@extends('layouts.embed')

@section('title', $memorial->full_name)

@section('content')
    <div style="max-width:480px;margin:0 auto;padding:24px 20px;text-align:center;">
        @if($memorial->profile_photo_url)
            <img src="{{ $memorial->profile_photo_url }}" alt="{{ $memorial->full_name }}"
                 style="width:96px;height:96px;border-radius:9999px;object-fit:cover;margin:0 auto 16px;display:block;">
        @endif

        <h1 style="font-size:20px;font-weight:600;margin:0 0 4px;">{{ $memorial->full_name }}</h1>

        @if($memorial->date_of_birth || $memorial->date_of_passing)
            <p style="font-size:13px;color:#6b7280;margin:0 0 12px;">
                {{ optional($memorial->date_of_birth)->format('Y') }} &ndash; {{ optional($memorial->date_of_passing)->format('Y') }}
            </p>
        @endif

        @if($memorial->short_description)
            <p style="font-size:14px;line-height:1.6;margin:0 0 20px;">{{ $memorial->short_description }}</p>
        @endif

        <a href="{{ $fullUrl }}" target="_top"
           style="display:inline-block;padding:10px 20px;border-radius:8px;background:#465fff;color:#fff;text-decoration:none;font-size:14px;font-weight:500;">
            View Full Memorial
        </a>
    </div>
@endsection
