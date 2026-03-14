@extends('layouts.app')

@push('head')
<link href="https://cdn.quilljs.com/1.3.7/quill.snow.css" rel="stylesheet">
<script src="https://cdn.quilljs.com/1.3.7/quill.min.js"></script>
@endpush

@section('content')
    <x-common.page-breadcrumb pageTitle="Edit {{ $page->title }}" />

    @if (session('success'))
        <div class="mb-4 rounded-lg bg-green-50 dark:bg-green-900/20 px-4 py-3 text-sm text-green-700 dark:text-green-400">
            {{ session('success') }}
        </div>
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

    <form id="page-form" action="{{ route('settings.pages.update', $page->slug) }}" method="POST" class="space-y-6">
        @csrf
        @method('PUT')

        <x-common.component-card title="Page Details" desc="Configure the page title, description and publish status.">
            <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
                <div>
                    <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">Page Title</label>
                    <input type="text" name="title"
                        value="{{ old('title', $page->title) }}"
                        class="h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 dark:text-white/90 dark:border-gray-700 placeholder:text-gray-400 focus:border-brand-300 focus:ring-3 focus:ring-brand-500/10 focus:outline-hidden" />
                </div>
                <div>
                    <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">Meta Description</label>
                    <input type="text" name="meta_description"
                        value="{{ old('meta_description', $page->meta_description) }}"
                        maxlength="500"
                        placeholder="Brief description for search engines"
                        class="h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 dark:text-white/90 dark:border-gray-700 placeholder:text-gray-400 focus:border-brand-300 focus:ring-3 focus:ring-brand-500/10 focus:outline-hidden" />
                </div>
            </div>
            <div class="mt-4">
                <label class="flex items-center gap-2.5 cursor-pointer">
                    <input type="hidden" name="is_published" value="0">
                    <input type="checkbox" name="is_published" value="1"
                        {{ old('is_published', $page->is_published) ? 'checked' : '' }}
                        class="h-5 w-5 rounded border-gray-300 text-brand-500 focus:ring-brand-500 dark:border-gray-600 dark:bg-gray-800" />
                    <span class="text-sm font-medium text-gray-700 dark:text-gray-300">Published</span>
                </label>
            </div>
        </x-common.component-card>

        <x-common.component-card title="Page Content" desc="Use the editor below to write and format the page content.">
            <input type="hidden" name="content" id="page-content-input" value="">
            <div id="page-content-editor" class="min-h-[400px] rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-900"></div>
        </x-common.component-card>

        <div class="flex items-center gap-3">
            <button type="submit"
                class="inline-flex items-center gap-2 rounded-lg bg-brand-500 px-5 py-2.5 text-sm font-medium text-white hover:bg-brand-600 transition">
                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                Save Changes
            </button>
            <a href="{{ route('settings.pages.index') }}"
               class="inline-flex items-center gap-2 rounded-lg border border-gray-300 dark:border-gray-600 px-5 py-2.5 text-sm font-medium text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-white/5 transition">
                Cancel
            </a>
        </div>
    </form>
@endsection

@push('scripts')
<script>
(function() {
    if (typeof Quill === 'undefined') return;

    const toolbar = [
        [{ 'header': [1, 2, 3, false] }],
        [{ 'size': ['small', false, 'large', 'huge'] }],
        ['bold', 'italic', 'underline', 'strike'],
        [{ 'color': [] }, { 'background': [] }],
        ['link', 'blockquote'],
        [{ 'list': 'ordered' }, { 'list': 'bullet' }],
        [{ 'align': [] }],
        ['clean'],
        ['code-block']
    ];

    const editor = new Quill('#page-content-editor', {
        theme: 'snow',
        placeholder: 'Write your page content here...',
        modules: { toolbar: toolbar }
    });

    const existingContent = @json($page->content ?? '');
    if (existingContent && existingContent.trim()) {
        editor.root.innerHTML = existingContent;
    }

    document.getElementById('page-form').addEventListener('submit', function() {
        const html = editor.root.innerHTML;
        document.getElementById('page-content-input').value = (html === '<p><br></p>') ? '' : html;
    });
})();
</script>
@endpush
