@extends('layouts.app')

@section('content')
    <x-common.page-breadcrumb pageTitle="{{ $memorial->title }}" />
    <div class="space-y-6">
        <x-common.component-card title="{{ $memorial->full_name }}">
            <div class="space-y-4">
                <div class="flex justify-between">
                    <div>
                        <p class="text-sm text-gray-500 dark:text-gray-400">Title</p>
                        <p class="font-medium text-gray-800 dark:text-white/90">{{ $memorial->title }}</p>
                    </div>
                    <div>
                        <a href="{{ route('memorials.edit', $memorial) }}" class="text-brand-500 hover:text-brand-600 text-sm">Edit</a>
                    </div>
                </div>
                @if ($memorial->date_of_birth || $memorial->date_of_passing)
                    <div>
                        <p class="text-sm text-gray-500 dark:text-gray-400">Dates</p>
                        <p class="font-medium text-gray-800 dark:text-white/90">
                            @if ($memorial->date_of_birth)
                                {{ $memorial->date_of_birth->format('M j, Y') }}
                            @endif
                            @if ($memorial->date_of_birth && $memorial->date_of_passing)
                                -
                            @endif
                            @if ($memorial->date_of_passing)
                                {{ $memorial->date_of_passing->format('M j, Y') }}
                            @endif
                        </p>
                    </div>
                @endif
                @if ($memorial->biography)
                    <div>
                        <p class="text-sm text-gray-500 dark:text-gray-400">Biography</p>
                        <p class="whitespace-pre-wrap text-gray-800 dark:text-white/90">{{ $memorial->biography }}</p>
                    </div>
                @endif
                <div>
                    <p class="text-sm text-gray-500 dark:text-gray-400">Theme</p>
                    <p class="font-medium text-gray-800 dark:text-white/90">{{ ucfirst($memorial->theme) }}</p>
                </div>
                <div>
                    <p class="text-sm text-gray-500 dark:text-gray-400">Visibility</p>
                    <p class="font-medium text-gray-800 dark:text-white/90">{{ $memorial->is_public ? 'Public' : 'Private' }}</p>
                </div>
                <div>
                    <p class="text-sm text-gray-500 dark:text-gray-400">Public URL</p>
                    <p class="font-mono text-sm text-gray-800 dark:text-white/90">/{{ $memorial->slug }}</p>
                    @if ($memorial->is_public)
                        <a href="{{ $memorial->publicUrl() }}" target="_blank" class="btn btn-primary btn-md mt-2">
                            View Public Page
                        </a>
                    @endif
                </div>
            </div>
        </x-common.component-card>
    </div>
@endsection
