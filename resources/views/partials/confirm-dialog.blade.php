{{--
    The confirmation dialog, and the one-attribute way to use it.

    Every destructive button in the admin used to end in `onsubmit="return confirm('…')"` — the
    browser's own grey box, in the browser's own font, saying "alwaysforeverloved.com says". It
    is the one part of the admin that was never ours, and it was the part a person sees at the
    exact moment they are about to delete something.

    This replaces it with a styled dialog, mounted once here, and an `x-confirm` directive so
    the change at each of the twenty-odd call sites is one attribute:

        onsubmit="return confirm('Delete this plan?')"      →   x-confirm="'Delete this plan?'"

    The message is an Alpine expression, so the same JS-string escaping the old attribute
    already had carries over unchanged — including the `addslashes()` the Blade templates wrap
    around names.

    Optional attributes on the same element:
        data-confirm-title="Delete user"     heading; defaults to "Please confirm"
        data-confirm-label="Delete"          the confirming button; defaults to a verb lifted
                                             from the start of the message, else "Confirm"
        data-confirm-safe                    not destructive: primary button instead of red

    How the directive behaves: it captures the element's `submit` (forms) or `click`
    (buttons, links), stops it, opens the dialog, and on OK replays the original action with a
    flag set so the second pass goes straight through. Cancel, Escape and the backdrop all
    close it and do nothing. Focus lands on Cancel when it opens, which is the safe default for
    a dialog whose other button deletes things.
--}}
<div x-data x-cloak
    x-show="$store.confirm.open"
    x-on:keydown.escape.window="$store.confirm.cancel()"
    class="fixed inset-0 z-[100] flex items-end justify-center p-4 sm:items-center"
    role="dialog" aria-modal="true" aria-labelledby="confirm-dialog-title" aria-describedby="confirm-dialog-message">

    {{-- Backdrop. Clicking it is a cancel, never a confirm. --}}
    <div class="absolute inset-0 bg-gray-900/50 backdrop-blur-[2px]"
        x-show="$store.confirm.open"
        x-transition:enter="transition ease-out duration-150" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100"
        x-transition:leave="transition ease-in duration-100" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0"
        x-on:click="$store.confirm.cancel()"></div>

    <div class="relative w-full max-w-md rounded-2xl border border-gray-200 bg-white p-6 shadow-2xl dark:border-gray-800 dark:bg-gray-900"
        x-show="$store.confirm.open"
        x-transition:enter="transition ease-out duration-150" x-transition:enter-start="translate-y-2 opacity-0 sm:translate-y-0 sm:scale-95" x-transition:enter-end="translate-y-0 opacity-100 sm:scale-100"
        x-transition:leave="transition ease-in duration-100" x-transition:leave-start="opacity-100 sm:scale-100" x-transition:leave-end="opacity-0 sm:scale-95"
        x-on:click.stop>

        <div class="flex items-start gap-4">
            <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full"
                :class="$store.confirm.danger ? 'bg-red-50 text-red-600 dark:bg-red-900/30 dark:text-red-400' : 'bg-brand-50 text-brand-600 dark:bg-brand-500/10 dark:text-brand-400'">
                <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v4m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z" />
                </svg>
            </div>
            <div class="min-w-0 flex-1">
                <h2 id="confirm-dialog-title" class="text-base font-semibold text-gray-900 dark:text-white" x-text="$store.confirm.title"></h2>
                <p id="confirm-dialog-message" class="mt-2 text-sm leading-relaxed text-gray-600 dark:text-gray-400" x-text="$store.confirm.message"></p>
            </div>
        </div>

        <div class="mt-6 flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
            <button type="button" x-ref="cancel" class="btn btn-secondary btn-md" x-on:click="$store.confirm.cancel()">Cancel</button>
            <button type="button" class="btn btn-md"
                :class="$store.confirm.danger ? 'btn-danger' : 'btn-primary'"
                x-on:click="$store.confirm.accept()"
                x-text="$store.confirm.label"></button>
        </div>
    </div>
</div>

<script>
    document.addEventListener('alpine:init', () => {
        Alpine.store('confirm', {
            open: false,
            title: 'Please confirm',
            message: '',
            label: 'Confirm',
            danger: true,
            _resolve: null,

            ask({ message, title, label, danger }) {
                this.message = message;
                this.title = title || 'Please confirm';
                this.label = label || this._verbFrom(message);
                this.danger = danger !== false;
                this.open = true;

                // Focus Cancel once the panel is in the DOM. Cancel, deliberately: the other
                // button is the one that deletes things, and Enter should not land on it.
                requestAnimationFrame(() => {
                    const el = document.querySelector('[x-ref="cancel"]');
                    if (el) el.focus();
                });

                return new Promise((resolve) => { this._resolve = resolve; });
            },

            accept() { this._settle(true); },
            cancel() { this._settle(false); },

            _settle(value) {
                if (! this.open) return;
                this.open = false;
                const r = this._resolve;
                this._resolve = null;
                if (r) r(value);
            },

            // "Delete this plan?" → "Delete". Falls back to a neutral verb rather than
            // repeating the message on the button.
            _verbFrom(message) {
                const m = /^(Delete|Remove|Suspend|Reset|Replace|Retry|Roll over|Mark)\b/i.exec(message || '');
                return m ? m[1][0].toUpperCase() + m[1].slice(1) : 'Confirm';
            },
        });

        // x-confirm="'message'"  — on a <form>, a <button>, or an <a>.
        Alpine.directive('confirm', (el, { expression }, { evaluateLater, cleanup }) => {
            const getMessage = evaluateLater(expression);
            const isForm = el.tagName === 'FORM';
            const eventName = isForm ? 'submit' : 'click';

            const handler = (event) => {
                // Second pass, after the person said yes: let it through and clear the flag so
                // the next use of the same element asks again.
                if (el.dataset.confirmed === '1') {
                    delete el.dataset.confirmed;
                    return;
                }

                event.preventDefault();
                event.stopImmediatePropagation();

                getMessage((message) => {
                    Alpine.store('confirm').ask({
                        message: String(message),
                        title: el.dataset.confirmTitle,
                        label: el.dataset.confirmLabel,
                        danger: ! el.hasAttribute('data-confirm-safe'),
                    }).then((ok) => {
                        if (! ok) return;
                        el.dataset.confirmed = '1';

                        if (isForm) {
                            // requestSubmit, not submit(): submit() bypasses submit listeners
                            // and validation, which is exactly the path we just intercepted.
                            el.requestSubmit ? el.requestSubmit() : el.submit();
                        } else if (el.tagName === 'A' && el.href) {
                            window.location.href = el.href;
                        } else {
                            el.click();
                        }
                    });
                });
            };

            // Capture phase, so this runs before any inline handler or Alpine @submit on the
            // same element and can stop them.
            el.addEventListener(eventName, handler, true);
            cleanup(() => el.removeEventListener(eventName, handler, true));
        });
    });
</script>
