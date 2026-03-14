@extends('layouts.visitor')

@section('page')
<section class="bg-white dark:bg-gray-900 py-16 sm:py-20">
    <div class="mx-auto max-w-3xl px-4 sm:px-6 lg:px-8">
        <div class="mb-10">
            <p class="text-sm font-semibold uppercase tracking-wider text-brand-600 dark:text-brand-400">Legal</p>
            <h1 class="mt-2 text-3xl font-bold text-gray-900 dark:text-white sm:text-4xl">{{ $page?->title ?? 'Privacy Policy' }}</h1>
            @if ($page?->updated_at)
                <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">Last updated: {{ $page->updated_at->format('F j, Y') }}</p>
            @endif
        </div>

        @if ($page && $page->is_published && $page->content)
            <div class="prose prose-gray dark:prose-invert max-w-none text-gray-700 dark:text-gray-300 leading-relaxed
                [&_h2]:text-xl [&_h2]:font-bold [&_h2]:text-gray-900 [&_h2]:dark:text-white [&_h2]:mt-8 [&_h2]:mb-4
                [&_h3]:text-lg [&_h3]:font-semibold [&_h3]:text-gray-900 [&_h3]:dark:text-white [&_h3]:mt-6 [&_h3]:mb-3
                [&_p]:mb-4 [&_p]:leading-relaxed
                [&_ul]:mb-4 [&_ul]:space-y-2
                [&_ol]:mb-4 [&_ol]:space-y-2
                [&_a]:text-brand-600 [&_a]:dark:text-brand-400 [&_a]:underline">
                {!! $page->content !!}
            </div>
        @else
            <div class="rounded-xl border border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800 p-8 text-center">
                <p class="text-gray-500 dark:text-gray-400">This page content is being prepared. Please check back soon.</p>
            </div>
        @endif
    </div>
</section>
@endsection
