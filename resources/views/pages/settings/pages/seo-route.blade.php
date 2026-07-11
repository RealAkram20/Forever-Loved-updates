@extends('layouts.app')

@section('content')
    <x-common.page-breadcrumb pageTitle="SEO &amp; social" />

    @if ($errors->any())
        <div class="mb-4 rounded-lg bg-red-50 dark:bg-red-900/20 px-4 py-3">
            <ul class="list-inside list-disc text-sm text-red-600 dark:text-red-400">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form action="{{ route('settings.pages.seo.update', $entry->route_key) }}" method="POST" enctype="multipart/form-data" class="space-y-6">
        @csrf
        @method('PUT')

        <x-common.component-card title="{{ $entry->label ?? $entry->route_key }}" desc="Route: {{ $entry->route_key }}. Meta title, description, and image apply to search and social previews.">
            <div class="space-y-4">
                <div>
                    <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">Meta title (browser &amp; social)</label>
                    <input type="text" name="meta_title" value="{{ old('meta_title', $entry->meta_title) }}"
                           maxlength="120"
                           placeholder="Leave empty to use the default title pattern"
                           class="h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 dark:border-gray-700 dark:text-white/90 focus:border-brand-300 focus:outline-hidden focus:ring-3 focus:ring-brand-500/10" />
                </div>
                <div>
                    <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">Meta description</label>
                    <textarea name="meta_description" rows="3" maxlength="500"
                              class="w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 dark:border-gray-700 dark:text-white/90 focus:border-brand-300 focus:outline-hidden focus:ring-3 focus:ring-brand-500/10">{{ old('meta_description', $entry->meta_description) }}</textarea>
                </div>
                <div>
                    <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">Featured / OG image</label>
                    @if ($entry->og_image)
                        <div class="mb-2 flex items-center gap-3">
                            <img src="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($entry->og_image) }}" alt="" class="h-16 w-auto rounded border border-gray-200 dark:border-gray-700" />
                            <label class="flex items-center gap-2 text-sm text-gray-600 dark:text-gray-400">
                                <input type="hidden" name="remove_og_image" value="0">
                                <input type="checkbox" name="remove_og_image" value="1" class="rounded border-gray-300 text-brand-500" />
                                Remove image
                            </label>
                        </div>
                    @endif
                    <input type="file" name="og_image" accept="image/*"
                           class="block w-full text-sm text-gray-600 file:mr-4 file:rounded-lg file:border-0 file:bg-brand-50 file:px-4 file:py-2 file:text-sm file:font-medium file:text-brand-700 dark:file:bg-brand-900/30 dark:file:text-brand-300" />
                </div>
            </div>
        </x-common.component-card>

        <div class="flex items-center gap-3">
            <button type="submit" class="btn btn-primary btn-md">
                Save
            </button>
            <a href="{{ route('settings.pages.index') }}" class="btn btn-secondary btn-md">
                Back to pages
            </a>
        </div>
    </form>
@endsection
