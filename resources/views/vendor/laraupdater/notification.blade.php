{{-- LaraUpdater: Update notification for admin users (Alpine.js, no jQuery) --}}
@auth
@if(auth()->user()?->hasRole(['admin','super-admin']))
<div id="laraupdater-notification" x-data="laraUpdaterNotification()" x-show="visible" x-cloak
    x-transition:enter="transition ease-out duration-300"
    x-transition:enter-start="opacity-0 translate-y-4"
    x-transition:enter-end="opacity-100 translate-y-0"
    class="fixed bottom-6 right-6 z-[99998] w-full max-w-sm rounded-xl border border-amber-200 dark:border-amber-800 bg-amber-50 dark:bg-amber-950/90 shadow-xl backdrop-blur-sm overflow-hidden">
    <div class="p-4">
        <div class="flex items-start gap-3">
            <div class="shrink-0 mt-0.5 rounded-full bg-amber-100 dark:bg-amber-900/50 p-2">
                <svg class="w-5 h-5 text-amber-600 dark:text-amber-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
                </svg>
            </div>
            <div class="flex-1 min-w-0">
                <h4 class="font-semibold text-amber-900 dark:text-amber-100">Update Available</h4>
                <p class="mt-1 text-sm text-amber-800 dark:text-amber-200" x-text="description"></p>
                <p class="mt-1 text-xs text-amber-600 dark:text-amber-400">Version <span x-text="version" class="font-mono"></span></p>
                <div class="mt-3 flex gap-2">
                    <button type="button" @click="runUpdate()" :disabled="updating"
                        class="inline-flex items-center rounded-lg bg-amber-600 px-3 py-1.5 text-sm font-medium text-white hover:bg-amber-700 disabled:opacity-50 transition">
                        <span x-show="!updating">Update Now</span>
                        <span x-show="updating">Updating...</span>
                    </button>
                    <button type="button" @click="visible = false"
                        class="inline-flex items-center rounded-lg border border-amber-300 dark:border-amber-700 px-3 py-1.5 text-sm font-medium text-amber-800 dark:text-amber-200 hover:bg-amber-100 dark:hover:bg-amber-900/50 transition">
                        Later
                    </button>
                </div>
                <div x-show="updating || resultHtml" class="mt-3 text-sm text-amber-800 dark:text-amber-200 prose prose-sm dark:prose-invert max-w-none" x-html="resultHtml"></div>
            </div>
        </div>
    </div>
</div>
<script>
document.addEventListener('alpine:init', () => {
    Alpine.data('laraUpdaterNotification', () => ({
        visible: false,
        version: '',
        description: '',
        updating: false,
        resultHtml: '',
        async init() {
            try {
                const r = await fetch('{{ url("/updater.check") }}');
                const data = await r.json().catch(() => null);
                if (data && data.version) {
                    this.version = data.version;
                    this.description = data.description || 'A new version is available.';
                    this.visible = true;
                }
            } catch (e) { /* ignore */ }
        },
        async runUpdate() {
            this.updating = true;
            this.resultHtml = '';
            try {
                const r = await fetch('{{ url("/updater.update") }}', {
                    method: 'POST',
                    headers: {
                        'Accept': 'text/html',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content ?? '',
                    },
                });
                const html = await r.text();
                this.resultHtml = html.replace(/<br\s*\/?>/gi, '<br>');
                this.updating = false;
                if (r.ok) {
                    setTimeout(() => location.reload(), 2000);
                }
            } catch (e) {
                this.resultHtml = '<p class="text-red-600">Update failed. Please try again or update manually.</p>';
                this.updating = false;
            }
        }
    }));
});
</script>
@endif
@endauth
