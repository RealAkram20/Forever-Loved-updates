@extends('layouts.app')

@section('content')
    {{--
        Every field on this page lives in pages/memorials/partials/form-*, shared with the
        reseller's "create for a client" screen. The two used to be separate forms — one
        with forty fields, the other with seven — so a memorial's completeness depended on
        which door it came through. Edit the partials, not this file.
    --}}
    <x-common.page-breadcrumb pageTitle="Create Memorial" />

    {{-- Covers session('error') as well as validation: a reseller client turned away at
         their provider's memorial allowance is bounced back here, and without this the
         page would simply reappear with no explanation at all. --}}
    <x-common.flash />

    <form method="POST" action="{{ route('memorials.store') }}" x-data="memorialCreateForm()">
        @csrf

        <div class="space-y-6">
            @include('pages.memorials.partials.form-identity', ['step' => 1])
            @include('pages.memorials.partials.form-biography-summary', ['step' => 2])
            @include('pages.memorials.partials.form-birth', ['step' => 3])
            @include('pages.memorials.partials.form-passed-away', ['step' => 4])
            @include('pages.memorials.partials.form-family', ['step' => 5])
            @include('pages.memorials.partials.form-education', ['step' => 6])
            @include('pages.memorials.partials.form-biography-editor', ['step' => null])
            @include('pages.memorials.partials.form-settings', ['step' => null, 'plans' => null])

            <div class="flex gap-3">
                <button type="submit" class="btn btn-primary btn-md">
                    Create Memorial
                </button>
                <a href="{{ route('memorials.index') }}" class="btn btn-secondary btn-md">
                    Cancel
                </a>
            </div>
        </div>
    </form>

    @include('pages.memorials.partials.form-scripts')
@endsection
