@extends('layouts.app')

@section('content')
    <x-common.page-breadcrumb pageTitle="Homepage layout" />

    @if (session('success'))
        <script>document.addEventListener('DOMContentLoaded', () => window.$toast?.('success', @json(session('success'))));</script>
    @endif

    @if ($errors->any())
        <div class="mb-4 rounded-lg bg-red-50 dark:bg-red-900/20 px-4 py-3">
            <p class="text-sm font-medium text-red-700 dark:text-red-400 mb-1">Please fix the following errors:</p>
            <ul class="list-disc list-inside text-sm text-red-600 dark:text-red-400">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <p class="mb-4 text-sm text-gray-600 dark:text-gray-400">
        Edit the homepage as JSON. The server validates block types and props on save. Block <code class="rounded bg-gray-100 px-1 py-0.5 text-xs dark:bg-gray-800">type</code> values must match registered site blocks (see <code class="rounded bg-gray-100 px-1 py-0.5 text-xs dark:bg-gray-800">docs/SITE_BLOCKS.md</code>).
        <a href="{{ route('home') }}" target="_blank" rel="noopener" class="font-medium text-brand-500 hover:underline">View site</a>
    </p>

    <form action="{{ route('settings.site-layout.update', $layoutKey) }}" method="POST" class="space-y-4">
        @csrf
        @method('PUT')

        <div>
            <label for="site-layout-json" class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">Layout JSON</label>
            <textarea id="site-layout-json" name="json" rows="28" spellcheck="false"
                class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2.5 font-mono text-xs leading-relaxed text-gray-900 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-100 focus:border-brand-300 focus:ring-3 focus:ring-brand-500/10 focus:outline-hidden">{{ old('json', $layoutJson) }}</textarea>
        </div>

        <div class="flex flex-wrap items-center gap-3">
            <button type="submit"
                class="inline-flex items-center gap-2 rounded-lg bg-brand-500 px-4 py-2 text-sm font-medium text-white hover:bg-brand-600 transition">
                Save &amp; publish
            </button>
        </div>
    </form>
@endsection
