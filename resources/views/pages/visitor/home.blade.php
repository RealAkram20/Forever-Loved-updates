@extends('layouts.visitor')

@push('head')
<style>
    .memorial-swiper .swiper-wrapper {
        transition-timing-function: linear !important;
    }
</style>
@endpush

@section('page')

<x-site.layout-renderer
    :blocks="$homeBlocks ?? []"
    :popularMemorials="$popularMemorials"
    :tagline="$tagline"
/>

@endsection
