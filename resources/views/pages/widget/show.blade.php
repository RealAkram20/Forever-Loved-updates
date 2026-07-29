@extends('layouts.embed')

@section('title', $memorial->full_name)

@section('content')
    <div style="max-width:480px;margin:0 auto;padding:24px 20px;text-align:center;">
        {{-- The reseller's own mark, so an embed on their website reads as theirs. Only shown
             when a logo actually exists — falling back to the platform's would put our brand
             on their site, which is the opposite of what this feature is sold as. --}}
        @if ($memorial->reseller?->logo_path)
            <img src="{{ \App\Helpers\StorageHelper::publicUrl($memorial->reseller->logo_path) }}"
                 alt="{{ $memorial->reseller->name }}" class="embed-logo">
        @endif

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

        {{-- .embed-cta is themed from the tenant's button colours; this was a hardcoded
             #465fff, so every reseller's embed shipped the platform's blue. --}}
        <a href="{{ $fullUrl }}" target="_top" class="embed-cta">
            View Full Memorial
        </a>
    </div>
@endsection
