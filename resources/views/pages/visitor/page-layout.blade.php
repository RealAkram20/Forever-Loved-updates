@extends('layouts.visitor')

@push('head')
<style>
    .memorial-swiper .swiper-wrapper {
        transition-timing-function: linear !important;
    }
</style>
@endpush

@section('page')
    <x-page-layout.renderer :widgets="$widgets" :context="$layoutContext ?? []" />
@endsection
