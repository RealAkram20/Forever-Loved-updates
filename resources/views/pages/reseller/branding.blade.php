@extends('layouts.app')

@section('content')
    <x-common.page-breadcrumb pageTitle="Branding" />

    @if (session('success'))
        <div class="mb-4 rounded-lg bg-green-50 dark:bg-green-900/20 px-4 py-3 text-sm text-green-700 dark:text-green-400">{{ session('success') }}</div>
    @endif

    <div class="space-y-6">
        <x-common.component-card title="Your Subdomain" desc="Where your clients' visitors see your branded memorial pages.">
            <p class="text-sm text-gray-700 dark:text-gray-300">
                <code class="rounded bg-gray-100 dark:bg-gray-800 px-2 py-1">{{ $reseller->slug }}.{{ config('reseller.domain') }}</code>
            </p>

            @if ($reseller->tier?->feature_embedding)
                <div class="mt-4">
                    <p class="mb-2 text-sm font-medium text-gray-700 dark:text-gray-300">Embed snippet — paste this into your own site to show a memorial there:</p>
                    <pre class="overflow-x-auto rounded-lg bg-gray-100 dark:bg-gray-800 p-3 text-xs text-gray-700 dark:text-gray-300">&lt;script src="{{ url('/embed.js') }}" data-memorial="MEMORIAL-SLUG"&gt;&lt;/script&gt;</pre>
                </div>
            @else
                <p class="mt-4 text-sm text-gray-500 dark:text-gray-400">Embedding isn't included in your current plan.</p>
            @endif
        </x-common.component-card>

        <x-common.component-card title="Logo, Favicon &amp; Color" desc="Applies here on your dashboard and on your public memorial pages.">
            <form action="{{ route('reseller.branding.update') }}" method="POST" enctype="multipart/form-data" class="space-y-5">
                @csrf @method('PUT')
                <div>
                    <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">Logo</label>
                    @if ($reseller->logo_path)
                        <img src="{{ \App\Helpers\StorageHelper::publicUrl($reseller->logo_path) }}" alt="Current logo" class="mb-2 h-12">
                    @endif
                    <input type="file" name="logo" accept="image/*"
                        class="block w-full text-sm text-gray-600 dark:text-gray-400 file:mr-3 file:rounded-lg file:border-0 file:bg-brand-50 file:px-3 file:py-2 file:text-sm file:font-medium file:text-brand-700 dark:file:bg-brand-900/30 dark:file:text-brand-400" />
                    @error('logo') <p class="mt-1 text-sm text-red-500">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">Favicon</label>
                    @if ($reseller->favicon_path)
                        <img src="{{ \App\Helpers\StorageHelper::publicUrl($reseller->favicon_path) }}" alt="Current favicon" class="mb-2 h-8 w-8">
                    @else
                        <p class="mb-2 text-xs text-gray-400 dark:text-gray-500">No favicon set — your logo is used as a fallback.</p>
                    @endif
                    <input type="file" name="favicon" accept="image/*"
                        class="block w-full text-sm text-gray-600 dark:text-gray-400 file:mr-3 file:rounded-lg file:border-0 file:bg-brand-50 file:px-3 file:py-2 file:text-sm file:font-medium file:text-brand-700 dark:file:bg-brand-900/30 dark:file:text-brand-400" />
                    @error('favicon') <p class="mt-1 text-sm text-red-500">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">Primary Color</label>
                    <input type="color" name="primary_color" value="{{ $reseller->primary_color ?? '#465fff' }}" class="h-10 w-20 rounded border border-gray-300 dark:border-gray-700 bg-transparent" />
                </div>
                <div class="flex justify-end">
                    <button type="submit" class="btn btn-primary btn-md">Save Branding</button>
                </div>
            </form>
        </x-common.component-card>
    </div>
@endsection
