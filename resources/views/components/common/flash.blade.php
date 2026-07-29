{{-- Session feedback. Every page was hand-rolling these two blocks; a missed one is how
     a form silently appears to do nothing. --}}
@if (session('success'))
    <div class="mb-4 flex items-start gap-3 rounded-xl bg-green-50 px-4 py-3 dark:bg-green-900/20">
        <svg class="mt-0.5 h-4 w-4 shrink-0 text-green-600 dark:text-green-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m5 12.5 4.5 4.5L19 7"/></svg>
        <p class="text-sm text-green-700 dark:text-green-400">{{ session('success') }}</p>
    </div>
@endif

@if (session('error'))
    <div class="mb-4 flex items-start gap-3 rounded-xl bg-red-50 px-4 py-3 dark:bg-red-900/20">
        <svg class="mt-0.5 h-4 w-4 shrink-0 text-red-600 dark:text-red-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"><circle cx="12" cy="12" r="9"/><path d="M12 7.75v5M12 16h.01"/></svg>
        <p class="text-sm text-red-700 dark:text-red-400">{{ session('error') }}</p>
    </div>
@endif

@if ($errors->any())
    <div class="mb-4 rounded-xl bg-red-50 px-4 py-3 dark:bg-red-900/20">
        <p class="mb-1 text-sm font-medium text-red-700 dark:text-red-400">Please fix the following:</p>
        <ul class="list-inside list-disc text-sm text-red-600 dark:text-red-400">
            @foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach
        </ul>
    </div>
@endif
