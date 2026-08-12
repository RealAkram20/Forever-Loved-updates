/**
 * Memorial public page - AJAX interactions, no page reload
 */
document.addEventListener('DOMContentLoaded', () => {
    const memorialSlug = document.querySelector('[data-memorial-slug]')?.dataset.memorialSlug;
    const canEdit = document.querySelector('[data-can-edit]')?.dataset.canEdit === '1';
    const canUpload = document.querySelector('[data-can-upload]')?.dataset.canUpload === '1';
    // `let`, not `const`: a guest's first write signs them in mid-page (see adoptSession),
    // and everything downstream should immediately behave like the signed-in page.
    let isAuthenticated = document.querySelector('[data-is-authenticated]')?.dataset.isAuthenticated === '1';

    if (!memorialSlug) return;

    const container = document.querySelector('[data-memorial-slug]');
    const tributeUrl = container?.dataset.tributeUrl;
    const scrollToChapterId = container?.dataset.scrollChapter || '';
    const baseUrl = tributeUrl ? tributeUrl.replace(/\/tribute$/, '') : `/m/${memorialSlug}`;
    let csrf = document.querySelector('meta[name="csrf-token"]')?.content;

    /**
     * A guest's first write created their account and signed this session into it — the
     * server says so by returning `signed_in` with a fresh CSRF token (signing in rotates
     * the session, and the old token would 419 every request after it). Adopt both, and
     * stop showing the name/email fields: they are somebody now, and asking again is the
     * exact loop this exists to end.
     */
    function adoptSession(data) {
        if (!data || !data.signed_in || !data.csrf) return;
        csrf = data.csrf;
        document.querySelector('meta[name="csrf-token"]')?.setAttribute('content', data.csrf);
        isAuthenticated = true;
        document.querySelectorAll('[data-guest-fields]').forEach(el => el.classList.add('hidden'));
    }

    const fetchOpts = (method, body = null) => ({
        method,
        headers: {
            'Content-Type': 'application/json',
            'Accept': 'application/json',
            'X-CSRF-TOKEN': csrf,
            'X-Requested-With': 'XMLHttpRequest',
        },
        body: body ? JSON.stringify(body) : null,
    });

    const formDataOpts = (body) => ({
        method: 'POST',
        headers: {
            'Accept': 'application/json',
            'X-CSRF-TOKEN': csrf,
            'X-Requested-With': 'XMLHttpRequest',
        },
        body,
    });

    /**
     * Keyboard and focus behaviour shared by this page's dialogs: focus moves inside on open
     * and returns to whatever opened it on close, Tab cycles within the dialog instead of
     * wandering onto the page behind the overlay, and Escape closes.
     *
     * Returns the release function — call it when hiding the dialog.
     */
    const DIALOG_FOCUSABLE = 'a[href], button:not([disabled]), input:not([disabled]), select:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex="-1"])';

    function openDialog(dialog, { initialFocus, onClose } = {}) {
        if (!dialog) return () => {};
        const previouslyFocused = document.activeElement;

        const focusableItems = () => Array.from(dialog.querySelectorAll(DIALOG_FOCUSABLE))
            .filter(el => el.offsetParent !== null);

        const onKeydown = (e) => {
            if (e.key === 'Escape') {
                e.preventDefault();
                onClose?.();
                return;
            }
            if (e.key !== 'Tab') return;
            const items = focusableItems();
            if (!items.length) return;
            const first = items[0];
            const last = items[items.length - 1];
            if (e.shiftKey && document.activeElement === first) {
                e.preventDefault();
                last.focus();
            } else if (!e.shiftKey && document.activeElement === last) {
                e.preventDefault();
                first.focus();
            }
        };

        dialog.addEventListener('keydown', onKeydown);
        requestAnimationFrame(() => (initialFocus ?? focusableItems()[0])?.focus());

        return () => {
            dialog.removeEventListener('keydown', onKeydown);
            if (previouslyFocused instanceof HTMLElement) previouslyFocused.focus();
        };
    }

    /** Full-screen strip below site header: upload % for large media on slow networks */
    function getMemorialUploadProgressUi() {
        const root = document.getElementById('memorial-upload-progress');
        const bar = document.getElementById('memorial-upload-progress-bar');
        const pctEl = document.getElementById('memorial-upload-progress-pct');
        const labelEl = document.getElementById('memorial-upload-progress-label');
        if (!root || !bar || !pctEl || !labelEl) {
            return {
                show() {},
                hide() {},
                setLabel() {},
                setProgress() {},
            };
        }
        return {
            show(labelText = 'Uploading…') {
                labelEl.textContent = labelText;
                root.classList.remove('hidden');
                root.setAttribute('aria-hidden', 'false');
                root.setAttribute('aria-valuenow', '0');
                bar.style.width = '0%';
                bar.classList.remove('animate-pulse');
                pctEl.textContent = '0%';
            },
            hide() {
                root.classList.add('hidden');
                root.setAttribute('aria-hidden', 'true');
                bar.style.width = '0%';
                bar.classList.remove('animate-pulse');
                pctEl.textContent = '0%';
                root.setAttribute('aria-valuenow', '0');
            },
            setLabel(text) {
                labelEl.textContent = text;
            },
            setProgress(percent) {
                if (percent == null || !Number.isFinite(percent)) {
                    bar.style.width = '100%';
                    bar.classList.add('animate-pulse');
                    pctEl.textContent = '…';
                    root.removeAttribute('aria-valuenow');
                    return;
                }
                bar.classList.remove('animate-pulse');
                const p = Math.min(100, Math.max(0, Math.round(percent)));
                bar.style.width = `${p}%`;
                pctEl.textContent = `${p}%`;
                root.setAttribute('aria-valuenow', String(p));
            },
        };
    }

    /**
     * Send the visitor to sign in, carrying where they are now, so signing in puts them
     * straight back on this memorial rather than on the dashboard. Every "please sign in"
     * moment on this page goes through here — losing someone's place at the exact moment
     * they tried to say something is how they stop trying.
     *
     * The full sign-in page, not the code flow: it leads with one-click Google, takes a
     * password right there, and offers the emailed code as the fallback — the code flow
     * alone (type email, open inbox, copy six digits) is the slowest possible door to
     * hand a person who just wants to post something.
     */
    function goSignIn(message) {
        const here = location.pathname + location.search + location.hash;
        $toast('warning', (message || 'Please sign in to continue.') + ' Taking you to sign in…');
        setTimeout(() => {
            window.location.href = '/login?return=' + encodeURIComponent(here);
        }, 1600);
    }

    /**
     * POST multipart FormData with upload progress (fetch cannot report upload %).
     * @param {string} url
     * @param {FormData} formData
     * @param {{ label?: string, timeoutMs?: number }} options
     */
    function postFormDataWithUploadProgress(url, formData, options = {}) {
        const { label = 'Uploading…', timeoutMs = 600000 } = options;
        const ui = getMemorialUploadProgressUi();
        ui.show(label);

        return new Promise((resolve, reject) => {
            const xhr = new XMLHttpRequest();
            xhr.timeout = timeoutMs;

            xhr.upload.addEventListener('progress', (e) => {
                if (e.lengthComputable && e.total > 0) {
                    ui.setProgress((e.loaded / e.total) * 100);
                } else {
                    ui.setProgress(null);
                }
            });

            xhr.upload.addEventListener('loadend', () => {
                ui.setProgress(100);
            });

            xhr.addEventListener('load', () => {
                ui.hide();
                let data = {};
                try {
                    data = JSON.parse(xhr.responseText || '{}');
                } catch (_) {
                    data = {};
                }
                if (xhr.status >= 200 && xhr.status < 300) {
                    resolve(data);
                } else {
                    const msg = data.error || data.message || `Upload failed (${xhr.status})`;
                    const err = new Error(msg);
                    // The server's "this address has an account, sign in first" carries a
                    // flag; keep it on the error so the caller can send them to sign in
                    // (with a way back) instead of dead-ending on a red toast.
                    err.requiresLogin = !!data.requires_login;
                    reject(err);
                }
            });

            xhr.addEventListener('error', () => {
                ui.hide();
                reject(new Error('Network error. Check your connection and try again.'));
            });

            xhr.addEventListener('abort', () => {
                ui.hide();
                reject(new Error('Upload cancelled.'));
            });

            xhr.addEventListener('timeout', () => {
                ui.hide();
                reject(new Error('Upload timed out. Try a smaller file or a stronger connection.'));
            });

            xhr.open('POST', url);
            xhr.setRequestHeader('Accept', 'application/json');
            xhr.setRequestHeader('X-CSRF-TOKEN', csrf || '');
            xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
            xhr.send(formData);
        });
    }

    function shareDropdownHtml(url) {
        return `<a href="#" data-share="whatsapp" data-share-url="${url}" class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm font-medium transition hover:bg-green-50 dark:hover:bg-green-950/30 text-gray-700 dark:text-gray-300 group">
            <svg class="h-5 w-5 text-[#25D366] shrink-0" viewBox="0 0 24 24" fill="currentColor"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/></svg>
            <span class="group-hover:text-[#25D366] transition">WhatsApp</span>
        </a>
        <a href="#" data-share="facebook" data-share-url="${url}" class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm font-medium transition hover:bg-blue-50 dark:hover:bg-blue-950/30 text-gray-700 dark:text-gray-300 group">
            <svg class="h-5 w-5 text-[#1877F2] shrink-0" viewBox="0 0 24 24" fill="currentColor"><path d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z"/></svg>
            <span class="group-hover:text-[#1877F2] transition">Facebook</span>
        </a>
        <a href="#" data-share="linkedin" data-share-url="${url}" class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm font-medium transition hover:bg-sky-50 dark:hover:bg-sky-950/30 text-gray-700 dark:text-gray-300 group">
            <svg class="h-5 w-5 text-[#0A66C2] shrink-0" viewBox="0 0 24 24" fill="currentColor"><path d="M20.447 20.452h-3.554v-5.569c0-1.328-.027-3.037-1.852-3.037-1.853 0-2.136 1.445-2.136 2.939v5.667H9.351V9h3.414v1.561h.046c.477-.9 1.637-1.85 3.37-1.85 3.601 0 4.267 2.37 4.267 5.455v6.286zM5.337 7.433a2.062 2.062 0 01-2.063-2.065 2.064 2.064 0 112.063 2.065zm1.782 13.019H3.555V9h3.564v11.452zM22.225 0H1.771C.792 0 0 .774 0 1.729v20.542C0 23.227.792 24 1.771 24h20.451C23.2 24 24 23.227 24 22.271V1.729C24 .774 23.2 0 22.222 0h.003z"/></svg>
            <span class="group-hover:text-[#0A66C2] transition">LinkedIn</span>
        </a>
        <div class="my-1 border-t border-gray-100 dark:border-gray-700"></div>
        <button type="button" data-share="copy" data-share-url="${url}" class="flex w-full items-center gap-3 px-3 py-2.5 rounded-lg text-sm font-medium transition hover:bg-gray-100 dark:hover:bg-white/10 text-gray-700 dark:text-gray-300 group">
            <svg class="h-5 w-5 text-gray-400 dark:text-gray-500 group-hover:text-brand-500 shrink-0 transition" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1"/></svg>
            <span class="group-hover:text-brand-500 transition">Copy link</span>
        </button>`;
    }

    // --- Tab switching ---
    //
    // Life, then Tributes & Stories with two sub-tabs inside it, are all one panel now:
    // everything anyone writes is a story. The old names are kept as aliases because they
    // are still on the page in share links and preview buttons, and every one of them
    // always meant "show me what people wrote".
    const TAB_ALIASES = { life: 'stories', tributes: 'stories' };

    function switchToTab(panelId) {
        const target = TAB_ALIASES[panelId] || panelId;
        document.querySelector(`.memorial-tab-btn[data-tab-panel="${target}"]`)?.click();
    }
    const tabButtons = Array.from(document.querySelectorAll('.memorial-tab-btn'));

    tabButtons.forEach(btn => {
        btn.addEventListener('click', () => {
            const panelId = btn.dataset.tabPanel;
            tabButtons.forEach(b => {
                b.classList.remove('text-brand-600', 'dark:text-brand-400', 'border-brand-500', 'bg-brand-50/50', 'dark:bg-brand-500/10');
                b.classList.add('text-gray-600', 'dark:text-gray-400', 'border-transparent');
                // Roving tabindex: only the selected tab is in the tab order, so Tab moves
                // out of the tablist and into the panel rather than across four buttons.
                b.setAttribute('aria-selected', 'false');
                b.setAttribute('tabindex', '-1');
            });
            btn.classList.add('text-brand-600', 'dark:text-brand-400', 'border-brand-500', 'bg-brand-50/50', 'dark:bg-brand-500/10');
            btn.classList.remove('text-gray-600', 'dark:text-gray-400', 'border-transparent');
            btn.setAttribute('aria-selected', 'true');
            btn.setAttribute('tabindex', '0');

            document.querySelectorAll('.memorial-tab-panel').forEach(p => p.classList.add('hidden'));
            const panel = document.getElementById('tab-' + panelId);
            if (panel) panel.classList.remove('hidden');

            // Stories opens with the composer on screen, so this is the last moment Quill
            // can be fetched without the visitor waiting on it.
            if (panelId === 'stories') initComposerEditors();
        });

        // Arrow/Home/End navigation, per the WAI-ARIA tabs pattern.
        btn.addEventListener('keydown', (e) => {
            const keys = { ArrowRight: 1, ArrowLeft: -1 };
            let target = null;
            if (e.key in keys) {
                const from = tabButtons.indexOf(btn);
                target = tabButtons[(from + keys[e.key] + tabButtons.length) % tabButtons.length];
            } else if (e.key === 'Home') {
                target = tabButtons[0];
            } else if (e.key === 'End') {
                target = tabButtons[tabButtons.length - 1];
            }
            if (!target) return;
            e.preventDefault();
            target.click();
            target.focus();
        });
    });

    document.addEventListener('click', (e) => {
        const switchEl = e.target.closest('[data-switch-tab]');
        if (switchEl) {
            e.preventDefault();
            const panelId = switchEl.dataset.switchTab;
            if (panelId) switchToTab(panelId);
            return;
        }
        const previewLb = e.target.closest('[data-gallery-preview-lightbox]');
        if (previewLb) {
            e.preventDefault();
            const mediaId = parseInt(previewLb.dataset.galleryPreviewLightbox ?? '0', 10);
            switchToTab('gallery');
            const openWhenReady = () => {
                const galleryEl = document.getElementById('tab-gallery');
                if (!galleryEl || typeof Alpine === 'undefined') return;
                try {
                    const d = Alpine.$data(galleryEl);
                    if (d && typeof d.openLightbox === 'function') {
                        if (Object.prototype.hasOwnProperty.call(d, 'subTab')) {
                            d.subTab = 'images';
                        }
                        // The preview strip shows the gallery unfiltered, so a photo picked
                        // there can sit outside whatever category was last selected.
                        if (typeof d.selectCat === 'function') d.selectCat('all');
                        d.openLightbox(mediaId);
                    }
                } catch (_) { /* Alpine not ready */ }
            };
            requestAnimationFrame(() => {
                requestAnimationFrame(openWhenReady);
            });
        }
    });

    // --- Feed filters ---
    //
    // Two filters over one list: the marker somebody chose when they wrote (a candle, a
    // prayer, nothing) and the chapter the family filed it under. They are resolved in one
    // place because they are not independent — a story is shown when it passes both, and
    // two handlers each setting `display` on their own would take turns undoing the other.
    const feedFilters = { marker: '', chapter: '' };

    function applyFeedFilters() {
        let shown = 0;
        document.querySelectorAll('article.life-feed-post').forEach(article => {
            const matchesMarker = !feedFilters.marker || (article.dataset.marker || 'story') === feedFilters.marker;
            const matchesChapter = !feedFilters.chapter || (article.dataset.chapterId || '') === feedFilters.chapter;
            const visible = matchesMarker && matchesChapter;
            article.style.display = visible ? '' : 'none';
            if (visible) shown++;
        });

        // "Nothing written yet" and "nothing matches that filter" are different situations
        // and get different words; showing the first one over a filtered list would read as
        // the memorial being empty.
        const total = document.querySelectorAll('article.life-feed-post').length;
        const filtering = !!(feedFilters.marker || feedFilters.chapter);
        document.getElementById('story-feed-empty')?.classList.toggle('hidden', total > 0);
        document.getElementById('story-filter-empty')?.classList.toggle('hidden', !(total > 0 && shown === 0 && filtering));
    }

    /** The tally in the profile card, so posting a story is visible without a reload. */
    function bumpStoryCount(delta) {
        document.querySelectorAll('[data-story-count]').forEach(el => {
            const next = Math.max(0, parseInt((el.textContent || '0').replace(/\D/g, '') || 0, 10) + delta);
            el.textContent = next;
            const label = el.nextElementSibling;
            if (label) label.textContent = next === 1 ? 'Story' : 'Stories';
        });
    }

    document.querySelectorAll('.story-filter').forEach(btn => {
        btn.addEventListener('click', () => {
            feedFilters.marker = btn.dataset.storyMarker || '';
            document.querySelectorAll('.story-filter').forEach(b => b.classList.toggle('is-active', b === btn));
            applyFeedFilters();
        });
    });

    document.querySelectorAll('.chapter-filter').forEach(btn => {
        btn.addEventListener('click', () => {
            feedFilters.chapter = btn.dataset.chapter || '';
            document.querySelectorAll('.chapter-filter').forEach(b => {
                b.classList.remove('bg-brand-50', 'dark:bg-brand-500/20', 'text-brand-600', 'dark:text-brand-400');
                b.classList.add('text-gray-600', 'dark:text-gray-400');
            });
            btn.classList.add('bg-brand-50', 'dark:bg-brand-500/20', 'text-brand-600', 'dark:text-brand-400');
            btn.classList.remove('text-gray-600', 'dark:text-gray-400');
            applyFeedFilters();
        });
    });

    // --- Chapter edit/delete ---
    if (canEdit) {
        // Edit chapter: open modal
        let releaseChapterModal = null;

        // Exposed so the modal's Cancel button can close it the same way Escape does,
        // rather than only stripping the `hidden` class and stranding focus inside.
        window.closeEditChapterModal = () => {
            const modal = document.getElementById('edit-chapter-modal');
            if (!modal || modal.classList.contains('hidden')) return;
            modal.classList.add('hidden');
            releaseChapterModal?.();
            releaseChapterModal = null;
        };
        const closeChapterModal = window.closeEditChapterModal;

        document.addEventListener('click', (e) => {
            const editBtn = e.target.closest('[data-edit-chapter]');
            if (!editBtn) return;
            e.stopPropagation();
            const chapterId = editBtn.dataset.editChapter;
            const title = editBtn.dataset.chapterTitle || '';
            const desc = editBtn.dataset.chapterDesc || '';
            const modal = document.getElementById('edit-chapter-modal');
            if (!modal) return;
            document.getElementById('edit-chapter-id').value = chapterId;
            document.getElementById('edit-chapter-title').value = title;
            document.getElementById('edit-chapter-desc').value = desc;
            modal.classList.remove('hidden');
            releaseChapterModal = openDialog(modal, {
                initialFocus: document.getElementById('edit-chapter-title'),
                onClose: closeChapterModal,
            });
        });

        // Edit chapter: submit
        document.getElementById('edit-chapter-form')?.addEventListener('submit', (e) => {
            e.preventDefault();
            const chapterId = document.getElementById('edit-chapter-id').value;
            const title = document.getElementById('edit-chapter-title').value.trim();
            const desc = document.getElementById('edit-chapter-desc').value.trim();
            if (!title) return;
            const submitBtn = e.target.querySelector('button[type="submit"]');
            submitBtn.disabled = true;
            submitBtn.textContent = 'Saving...';
            fetch(`${baseUrl}/chapters/${chapterId}`, fetchOpts('PATCH', { title, description: desc || null }))
                .then(r => r.json())
                .then(data => {
                    if (data.success && data.chapter) {
                        const pill = document.querySelector(`[data-chapter-pill="${chapterId}"]`);
                        if (pill) {
                            const filterBtn = pill.querySelector('.chapter-filter');
                            if (filterBtn) filterBtn.textContent = data.chapter.title;
                            const editBtn = pill.querySelector('[data-edit-chapter]');
                            if (editBtn) {
                                editBtn.dataset.chapterTitle = data.chapter.title;
                                editBtn.dataset.chapterDesc = data.chapter.description || '';
                            }
                        }
                        document.querySelectorAll(`article.life-feed-post[data-chapter-id="${chapterId}"]`).forEach(article => {
                            const chapterLabel = article.querySelector('.text-xs.text-gray-500');
                            if (chapterLabel) {
                                const parts = chapterLabel.innerHTML.split(' · ');
                                if (parts.length > 1) {
                                    parts[parts.length - 1] = escapeHtml(data.chapter.title);
                                    chapterLabel.innerHTML = parts.join(' · ');
                                }
                            }
                        });
                        closeChapterModal();
                    } else if (data.error) {
                        $toast('error', data.error);
                    }
                })
                .catch(() => $toast('error', 'Something went wrong.'))
                .finally(() => {
                    submitBtn.disabled = false;
                    submitBtn.textContent = 'Save';
                });
        });

        // Delete chapter
        document.addEventListener('click', async (e) => {
            const deleteBtn = e.target.closest('[data-delete-chapter]');
            if (!deleteBtn) return;
            e.stopPropagation();
            const chapterId = deleteBtn.dataset.deleteChapter;
            if (!await $confirm('Posts in this chapter will be moved to "Life" (uncategorized).', { title: 'Delete this chapter?', confirmText: 'Delete chapter' })) return;
            deleteBtn.disabled = true;
            fetch(`${baseUrl}/chapters/${chapterId}`, fetchOpts('DELETE'))
                .then(r => r.json())
                .then(data => {
                    if (data.success) {
                        const pill = document.querySelector(`[data-chapter-pill="${chapterId}"]`);
                        pill?.remove();
                        document.querySelectorAll(`article.life-feed-post[data-chapter-id="${chapterId}"]`).forEach(article => {
                            article.dataset.chapterId = '';
                            const chapterLabel = article.querySelector('.text-xs.text-gray-500');
                            if (chapterLabel) {
                                const parts = chapterLabel.innerHTML.split(' · ');
                                if (parts.length > 1) {
                                    parts[parts.length - 1] = 'Life';
                                    chapterLabel.innerHTML = parts.join(' · ');
                                }
                            }
                        });
                    } else if (data.error) {
                        $toast('error', data.error);
                    }
                })
                .catch(() => { $toast('error', 'Something went wrong.'); deleteBtn.disabled = false; });
        });

        // Inline Quill editing for posts
        const postQuillInstances = {};

        function initPostEditor(postId) {
            if (postQuillInstances[postId]) return Promise.resolve(postQuillInstances[postId]);
            const editorEl = document.getElementById(`post-editor-${postId}`);
            if (!editorEl) return Promise.resolve(null);

            return loadQuill().then(() => {
                if (postQuillInstances[postId]) return postQuillInstances[postId];
                const q = new Quill(`#post-editor-${postId}`, {
                    theme: 'snow',
                    placeholder: 'Write your story...',
                    modules: {
                        toolbar: [
                            ['bold', 'italic', 'underline'],
                            [{ 'color': [] }],
                            ['link', 'blockquote'],
                            [{ 'list': 'ordered' }, { 'list': 'bullet' }],
                            ['clean']
                        ]
                    }
                });
                postQuillInstances[postId] = q;
                return q;
            }).catch(() => {
                $toast('error', 'The editor could not be loaded. Check your connection and try again.');
                return null;
            });
        }

        // Open post inline editor
        document.addEventListener('click', (e) => {
            const trigger = e.target.closest('[data-post-edit-trigger]');
            if (!trigger) return;
            e.stopPropagation();
            const postId = trigger.dataset.postEditTrigger;
            const article = document.querySelector(`#life-feed article.life-feed-post[data-post-id="${postId}"]`);
            if (!article) return;

            const displayEl = article.querySelector(`[data-post-display="${postId}"]`);
            const editEl = article.querySelector(`[data-post-edit="${postId}"]`);
            if (!displayEl || !editEl) return;

            displayEl.classList.add('hidden');
            editEl.classList.remove('hidden');

            const proseEl = displayEl.querySelector('.prose');
            const html = proseEl?.innerHTML?.trim() || '';
            initPostEditor(postId).then(quill => {
                if (!quill) return;
                quill.setContents([]);
                if (html) {
                    quill.clipboard.dangerouslyPasteHTML(0, html);
                }
                requestAnimationFrame(() => quill.focus());
            });
        });

        // Save post inline edit
        document.addEventListener('click', (e) => {
            const saveBtn = e.target.closest('[data-post-save]');
            if (!saveBtn) return;
            e.stopPropagation();
            const postId = saveBtn.dataset.postSave;
            const article = document.querySelector(`#life-feed article.life-feed-post[data-post-id="${postId}"]`);
            if (!article) return;

            const displayEl = article.querySelector(`[data-post-display="${postId}"]`);
            const editEl = article.querySelector(`[data-post-edit="${postId}"]`);
            const titleInput = article.querySelector(`[data-post-edit-title="${postId}"]`);
            const quill = postQuillInstances[postId];

            const newTitle = titleInput?.value?.trim() || null;
            const newContent = quill ? quill.root.innerHTML?.trim() : null;
            const isEmpty = !newContent || newContent === '<p><br></p>';

            saveBtn.disabled = true;
            saveBtn.textContent = 'Saving...';

            const syncLifePostDisplay = (displayRoot, post) => {
                const textMount = displayRoot.querySelector('[data-post-text-body]') || displayRoot;
                let titleEl = textMount.querySelector('h3');
                let proseEl = textMount.querySelector('.prose');
                if (post.title) {
                    if (!titleEl) {
                        titleEl = document.createElement('h3');
                        titleEl.className = 'mt-2 font-medium text-gray-900 dark:text-white/90';
                        textMount.insertBefore(titleEl, textMount.firstChild);
                    }
                    titleEl.textContent = post.title;
                } else if (titleEl) {
                    titleEl.remove();
                }
                if (post.content) {
                    if (!proseEl) {
                        proseEl = document.createElement('div');
                        proseEl.className = 'mt-2 text-sm text-gray-700 dark:text-gray-300 prose prose-sm dark:prose-invert max-w-none break-words overflow-hidden';
                        textMount.appendChild(proseEl);
                    }
                    proseEl.innerHTML = post.content;
                } else if (proseEl) {
                    proseEl.innerHTML = '';
                }
            };

            /**
             * The marker as it reads on the card: the verb beside the author's name and the
             * artwork on the right. Applied to every copy of this story on the page, because
             * the Biography tab's preview strip holds a second one.
             */
            const syncLifePostMarker = (art, post) => {
                art.dataset.marker = post.tribute_type || 'story';
                const verbEl = art.querySelector('[data-post-marker-verb]');
                if (verbEl) verbEl.textContent = post.marker_verb ? ` · ${post.marker_verb}` : '';
                const artEl = art.querySelector('[data-post-marker-art]');
                if (artEl) {
                    artEl.innerHTML = post.tribute_type
                        ? (document.querySelector(`#story-composer input[name="story-marker"][value="${post.tribute_type}"]`)
                            ?.closest('.story-marker-chip')?.querySelector('.story-marker-chip__art')?.innerHTML || '')
                        : '';
                    artEl.classList.toggle('hidden', !post.tribute_type);
                }
            };

            fetch(`${baseUrl}/posts/${postId}`, fetchOpts('PATCH', {
                title: newTitle,
                content: isEmpty ? null : newContent,
                tribute_type: article.querySelector(`input[name="post-marker-${postId}"]:checked`)?.value || null,
            }))
                .then(r => r.json())
                .then(data => {
                    if (data.success && data.post) {
                        syncLifePostDisplay(displayEl, data.post);
                        syncLifePostMarker(article, data.post);
                        displayEl.classList.remove('hidden');
                        editEl.classList.add('hidden');
                        document.querySelectorAll(`article.life-feed-post[data-post-id="${postId}"]`).forEach((art) => {
                            if (art === article) return;
                            syncLifePostMarker(art, data.post);
                            const d = art.querySelector(`[data-post-display="${postId}"]`);
                            if (d) syncLifePostDisplay(d, data.post);
                        });
                    } else if (data.error) {
                        $toast('error', data.error);
                    }
                })
                .catch(() => $toast('error', 'Something went wrong.'))
                .finally(() => {
                    saveBtn.disabled = false;
                    saveBtn.textContent = 'Save';
                });
        });

        // Cancel post inline edit
        document.addEventListener('click', (e) => {
            const cancelBtn = e.target.closest('[data-post-cancel]');
            if (!cancelBtn) return;
            e.stopPropagation();
            const postId = cancelBtn.dataset.postCancel;
            const article = document.querySelector(`#life-feed article.life-feed-post[data-post-id="${postId}"]`);
            if (!article) return;

            const displayEl = article.querySelector(`[data-post-display="${postId}"]`);
            const editEl = article.querySelector(`[data-post-edit="${postId}"]`);
            if (displayEl) displayEl.classList.remove('hidden');
            if (editEl) editEl.classList.add('hidden');
        });

        // Delete post from inline edit panel
        document.addEventListener('click', async (e) => {
            const deleteBtn = e.target.closest('[data-post-delete]');
            if (!deleteBtn) return;
            e.stopPropagation();
            const postId = deleteBtn.dataset.postDelete;
            if (!await $confirm('This cannot be undone.', { title: 'Delete this story?', confirmText: 'Delete story' })) return;
            deleteBtn.disabled = true;
            fetch(`${baseUrl}/posts/${postId}`, fetchOpts('DELETE'))
                .then(r => r.json())
                .then(data => {
                    if (data.success) {
                        document.querySelectorAll(`article.life-feed-post[data-post-id="${postId}"]`).forEach(a => a.remove());
                        bumpStoryCount(-1);
                        // The feed may have just become empty, or empty under the current
                        // filter; both have something to say and neither says it by itself.
                        applyFeedFilters();
                    } else if (data.error) {
                        $toast('error', data.error);
                    }
                })
                .catch(() => { $toast('error', 'Something went wrong.'); deleteBtn.disabled = false; });
        });
    }

    // --- Profile photo upload ---
    if (canEdit) {
        // By attribute, for the same reason as the cover: there are two of these. One sits
        // on the avatar in the profile card, which is not rendered below `md`, and one in
        // the owner's editing strip, which is where the control lives at that size.
        document.querySelectorAll('[data-profile-photo-input]').forEach(input => input.addEventListener('change', (e) => {
            const file = e.target.files?.[0];
            if (!file) return;
            const fd = new FormData();
            fd.append('photo', file);
            fd.append('_token', csrf);
            postFormDataWithUploadProgress(`${baseUrl}/profile-photo`, fd, { label: 'Uploading profile photo…' })
                .then(data => {
                    if (data.success) {
                        // Keyed by id, not by the wrapper's utility classes — those move
                        // whenever the card layout is adjusted, and this broke silently.
                        const container = document.getElementById('memorial-profile-photo');
                        if (container) {
                            container.innerHTML = `<img src="${data.url}" alt="" class="h-full w-full object-cover" />`;
                        }
                        // The hero shows the same portrait, and is hidden until one exists.
                        const heroPortrait = document.getElementById('memorial-hero-portrait');
                        const heroPortraitImage = document.getElementById('memorial-hero-portrait-image');
                        if (heroPortraitImage) {
                            // The server-rendered srcset still lists the old photo's
                            // derivatives, and srcset outranks src — drop it or the swap
                            // is invisible.
                            heroPortraitImage.removeAttribute('srcset');
                            heroPortraitImage.src = data.url;
                        }
                        heroPortrait?.classList.remove('hidden');
                    } else if (data.error) {
                        $toast('error', data.error);
                    }
                })
                .catch(err => { $toast('error', err.message || 'Photo upload failed.'); });
            e.target.value = '';
        }));
    }

    // --- Cover banner upload / removal ---
    if (canEdit) {
        // Selected by attribute, not id, because there are two sets of these controls: one
        // floating on the banner and one inside the card for small screens, where the
        // banner is not rendered at all. Ids would have to be unique and so could only ever
        // wire up one of them.
        const coverRemoveBtns = document.querySelectorAll('[data-cover-remove]');
        const coverLabels = document.querySelectorAll('[data-cover-label]');

        // The cover dresses two places: the card banner and the hero backdrop. Both are
        // updated together so an upload never leaves one of them showing the fallback.
        const coverSurfaces = [
            { image: document.getElementById('memorial-cover-image'), fallback: document.getElementById('memorial-cover-fallback') },
            { image: document.getElementById('memorial-hero-image'), fallback: document.getElementById('memorial-hero-fallback') },
        ];

        const showCover = (url) => {
            coverSurfaces.forEach(({ image, fallback }) => {
                if (image) {
                    // srcset outranks src; without this the freshly uploaded cover
                    // would lose to the old photo's derivative ladder.
                    image.removeAttribute('srcset');
                    image.src = url;
                    image.classList.remove('hidden');
                }
                fallback?.classList.add('hidden');
            });
            coverRemoveBtns.forEach(b => b.classList.remove('hidden'));
            coverLabels.forEach(l => { l.textContent = 'Change cover'; });
        };

        const clearCover = () => {
            coverSurfaces.forEach(({ image, fallback }) => {
                if (image) {
                    image.classList.add('hidden');
                    image.removeAttribute('src');
                }
                fallback?.classList.remove('hidden');
            });
            coverRemoveBtns.forEach(b => b.classList.add('hidden'));
            coverLabels.forEach(l => { l.textContent = 'Add cover'; });
        };

        document.querySelectorAll('[data-cover-input]').forEach(input => input.addEventListener('change', (e) => {
            const file = e.target.files?.[0];
            if (!file) return;
            const fd = new FormData();
            fd.append('photo', file);
            fd.append('_token', csrf);
            postFormDataWithUploadProgress(`${baseUrl}/cover-photo`, fd, { label: 'Uploading cover photo…' })
                .then(data => {
                    if (data.success) {
                        showCover(data.url);
                        $toast('success', 'Cover photo updated.');
                    } else if (data.error) {
                        $toast('error', data.error);
                    }
                })
                .catch(err => { $toast('error', err.message || 'Cover upload failed.'); });
            e.target.value = '';
        }));

        coverRemoveBtns.forEach(btn => btn.addEventListener('click', async () => {
            if (!await $confirm('The banner will go back to its default look.', { title: 'Remove cover photo?', confirmText: 'Remove cover' })) return;
            // Both copies, not just the one that was pressed — the other is the same action.
            coverRemoveBtns.forEach(b => { b.disabled = true; });
            fetch(`${baseUrl}/cover-photo`, fetchOpts('DELETE'))
                .then(r => r.json())
                .then(data => {
                    if (data.success) {
                        clearCover();
                        $toast('success', 'Cover photo removed.');
                    } else if (data.error) {
                        $toast('error', data.error);
                    }
                })
                .catch(() => $toast('error', 'Something went wrong.'))
                .finally(() => { coverRemoveBtns.forEach(b => { b.disabled = false; }); });
        }));
    }

    // --- Gallery ---
    //
    // The gallery is server-rendered and then patched in place. Everything below shares one
    // rule: the Alpine component on #tab-gallery is the single source of truth for which
    // category an item belongs to, and the DOM cells only ask it. Nothing here recomputes
    // positions, because nothing is addressed by position any more.
    function galleryData() {
        const el = document.getElementById('tab-gallery');
        if (!el || typeof Alpine === 'undefined') return null;
        try {
            return Alpine.$data(el);
        } catch {
            return null;
        }
    }

    const GALLERY_ICON_EDIT = '<svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"/></svg>';
    const GALLERY_ICON_DELETE = '<svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>';
    const GALLERY_OVERLAY_BTN = 'flex h-7 w-7 items-center justify-center rounded-full bg-black/60 text-white transition active:scale-[0.97] motion-reduce:active:scale-100';

    // Built with DOM calls rather than an HTML string: a caption containing a quote used to
    // be able to break out of the data-current-caption attribute.
    function buildGalleryControls(media, type) {
        const wrap = document.createElement('div');
        wrap.className = type === 'video'
            ? 'absolute top-2 right-2 z-20 flex items-center gap-1'
            : 'absolute top-1 right-1 z-10 flex items-center gap-1';

        const edit = document.createElement('button');
        edit.type = 'button';
        edit.className = `${GALLERY_OVERLAY_BTN} hover:bg-brand-500`;
        edit.title = type === 'video' ? 'Edit video' : 'Edit photo';
        edit.dataset.galleryEditCaption = media.id;
        edit.dataset.currentCaption = media.caption || '';
        edit.dataset.currentCategory = media.gallery_category_id ?? '';
        edit.innerHTML = GALLERY_ICON_EDIT;
        wrap.appendChild(edit);

        const del = document.createElement('button');
        del.type = 'button';
        del.className = `${GALLERY_OVERLAY_BTN} hover:bg-red-500`;
        del.title = 'Delete';
        del.dataset.galleryDelete = media.id;
        del.innerHTML = GALLERY_ICON_DELETE;
        wrap.appendChild(del);

        return wrap;
    }

    // A cell built here has to be the same cell Blade renders — same data-* hooks, same
    // x-show binding, same controls. It used to be a bare <button>, so a photo you had just
    // uploaded could not be captioned or deleted until you reloaded the page.
    function buildGalleryCell(media) {
        const isVideo = media.type === 'video';
        const cell = document.createElement('div');
        cell.className = isVideo
            ? 'group/vid relative'
            : 'group/img relative aspect-square overflow-hidden rounded-lg bg-gray-200 dark:bg-gray-700';
        cell.setAttribute('x-show', `matches(${media.id})`);
        cell.setAttribute('data-gallery-item', '');
        cell.dataset.mediaId = media.id;
        cell.dataset.mediaType = isVideo ? 'video' : 'photo';

        if (isVideo) {
            cell.insertAdjacentHTML('beforeend', buildVideoPlayerHtml(media.url, media.caption));
        } else {
            const open = document.createElement('button');
            open.type = 'button';
            open.className = 'block h-full w-full focus:outline-none focus:ring-2 focus:ring-brand-500 focus:ring-offset-2';
            open.setAttribute('@click', `openLightbox(${media.id})`);
            const img = document.createElement('img');
            img.src = media.url;
            img.alt = media.caption || 'Gallery photo';
            img.loading = 'lazy';
            img.className = 'h-full w-full object-cover transition duration-300 group-hover/img:scale-105';
            open.appendChild(img);
            cell.appendChild(open);
        }

        if (canEdit) cell.appendChild(buildGalleryControls(media, isVideo ? 'video' : 'photo'));

        return cell;
    }

    // --- Gallery upload (supports Images/Videos sub-tabs + lightbox) ---
    if (canUpload) {
        document.getElementById('gallery-upload')?.addEventListener('change', (e) => {
            const file = e.target.files?.[0];
            if (!file) return;
            const d = galleryData();
            // Upload into whatever category is being browsed, so the picture lands where the
            // person was looking instead of somewhere they then have to go and find.
            const targetCat = d && /^\d+$/.test(String(d.activeCat)) ? String(d.activeCat) : '';
            const fd = new FormData();
            fd.append('file', file);
            fd.append('_token', csrf);
            if (targetCat) fd.append('gallery_category_id', targetCat);
            const isVideo = file.type.startsWith('video/');
            const label = isVideo ? 'Uploading video to gallery…' : 'Uploading photo to gallery…';
            postFormDataWithUploadProgress(`${baseUrl}/gallery`, fd, { label })
                .then(data => {
                    if (!data.success || !data.media) {
                        if (data.error) $toast('error', data.error);
                        return;
                    }

                    const media = data.media;
                    const video = media.type === 'video';
                    const grid = document.getElementById(video ? 'gallery-grid-videos' : 'gallery-grid-images');
                    if (!grid) return;

                    const keys = media.gallery_category_id ? [String(media.gallery_category_id)] : ['uncategorised'];
                    if (video) {
                        d?.addVideo(media.id, keys);
                    } else {
                        d?.addImage(media.id, media.url, media.caption || '', keys);
                    }

                    // Appended before initTree so Alpine can resolve matches() and
                    // openLightbox() from the enclosing gallery scope.
                    const cell = buildGalleryCell(media);
                    grid.appendChild(cell);
                    if (typeof Alpine !== 'undefined') Alpine.initTree(cell);
                })
                .catch(err => { $toast('error', err.message || 'Gallery upload failed.'); });
            e.target.value = '';
        });
    }

    // --- Gallery delete ---
    document.addEventListener('click', async (e) => {
        const deleteBtn = e.target.closest('[data-gallery-delete]');
        if (!deleteBtn) return;
        e.stopPropagation();
        const mediaId = deleteBtn.dataset.galleryDelete;
        if (!await $confirm('This media will be permanently removed.', { title: 'Delete this gallery item?', confirmText: 'Delete' })) return;
        deleteBtn.disabled = true;

        const item = deleteBtn.closest('[data-gallery-item]');
        const type = item?.dataset.mediaType;

        try {
            const r = await fetch(`${baseUrl}/gallery/${mediaId}`, fetchOpts('DELETE'));
            const data = r.ok ? await r.json() : null;

            if (!r.ok || !data?.success) {
                const msg = data?.error || data?.message || `Delete failed (${r.status})`;
                $toast('error', msg);
                deleteBtn.disabled = false;
                return;
            }

            item?.remove();

            // One write, by id. The old form spliced the lightbox array by grid position and
            // then renumbered every cell after it — which a filtered grid has no way to do.
            galleryData()?.removeMedia(parseInt(mediaId, 10));

            // Update quota counter
            const quotaAttr = type === 'photo' ? 'data-quota-images' : 'data-quota-videos';
            const quotaEl = document.querySelector(`[${quotaAttr}]`);
            if (quotaEl) {
                const current = Math.max(0, parseInt(quotaEl.dataset.current || 0) - 1);
                const max = quotaEl.dataset.max;
                quotaEl.dataset.current = current;
                const label = type === 'photo' ? 'Images' : 'Videos';
                quotaEl.textContent = `${label}: ${current}/${max}`;
                quotaEl.classList.remove('text-red-500', 'dark:text-red-400', 'font-medium');
            }

            // The empty states are driven by Alpine off visibleImages / visibleVideoCount,
            // so removeMedia() above has already shown them if this was the last one. They
            // used to be toggled here, which could not know whether a filter was active.

            $toast('success', 'Gallery item deleted.');
        } catch {
            $toast('error', 'Something went wrong. Please try again.');
            deleteBtn.disabled = false;
        }
    });

    // --- Gallery caption edit ---
    let releaseCaptionEditor = null;

    function closeCaptionEditor() {
        const editor = document.getElementById('gallery-caption-editor');
        if (!editor || editor.classList.contains('hidden')) return;
        editor.classList.add('hidden');
        releaseCaptionEditor?.();
        releaseCaptionEditor = null;
    }

    document.addEventListener('click', (e) => {
        const editBtn = e.target.closest('[data-gallery-edit-caption]');
        if (!editBtn) return;
        e.stopPropagation();
        const mediaId = editBtn.dataset.galleryEditCaption;
        const currentCaption = editBtn.dataset.currentCaption || '';
        const currentCategory = editBtn.dataset.currentCategory || '';
        const editor = document.getElementById('gallery-caption-editor');
        const input = document.getElementById('gallery-caption-input');
        const select = document.getElementById('gallery-category-select');
        const mediaIdInput = document.getElementById('gallery-caption-media-id');
        if (!editor || !input || !mediaIdInput) return;

        mediaIdInput.value = mediaId;
        input.value = currentCaption;
        if (select) select.value = currentCategory;

        // A story's picture can also be filed somewhere, and stays under From Stories either
        // way. Saying so stops the empty select reading as "this is filed nowhere".
        const fromStory = !!editBtn.closest('[data-gallery-item]')?.hasAttribute('data-from-story');
        document.getElementById('gallery-caption-story-note')?.classList.toggle('hidden', !fromStory);

        editor.classList.remove('hidden');
        releaseCaptionEditor = openDialog(editor, { initialFocus: input, onClose: closeCaptionEditor });
    });

    // Caption + category save
    document.getElementById('gallery-caption-save')?.addEventListener('click', () => {
        const input = document.getElementById('gallery-caption-input');
        const select = document.getElementById('gallery-category-select');
        const mediaId = document.getElementById('gallery-caption-media-id')?.value;
        if (!mediaId) return;

        const saveBtn = document.getElementById('gallery-caption-save');
        const caption = input.value.trim();
        const categoryId = select ? select.value : '';
        saveBtn.disabled = true;
        saveBtn.textContent = 'Saving...';

        fetch(`${baseUrl}/gallery/${mediaId}`, fetchOpts('PATCH', {
            caption: caption || null,
            gallery_category_id: categoryId ? parseInt(categoryId, 10) : null,
        }))
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    const id = parseInt(mediaId, 10);
                    const savedCategory = data.media?.gallery_category_id ?? null;

                    document.querySelectorAll(`[data-gallery-edit-caption="${mediaId}"]`).forEach(btn => {
                        btn.dataset.currentCaption = caption;
                        btn.dataset.currentCategory = savedCategory ?? '';
                    });

                    const item = document.querySelector(`[data-gallery-item][data-media-id="${mediaId}"]`);
                    // Story membership is derived server-side and never changes here, so it
                    // is carried over rather than recomputed from the response.
                    const keys = [];
                    if (item?.hasAttribute('data-from-story')) keys.push('stories');
                    if (savedCategory) keys.push(String(savedCategory));

                    const d = galleryData();
                    d?.setCats(id, keys.length ? keys : ['uncategorised']);
                    d?.setCaption(id, caption);

                    if (item?.dataset.mediaType === 'photo') {
                        const img = item.querySelector('img');
                        if (img) img.alt = caption || 'Gallery photo';
                    } else if (item) {
                        const captionEl = item.querySelector('.memorial-video-player + div p, .memorial-video-player .text-xs');
                        if (captionEl) captionEl.textContent = caption;
                    }

                    closeCaptionEditor();
                    $toast('success', 'Saved.');
                } else if (data.error) {
                    $toast('error', data.error);
                }
            })
            .catch(() => $toast('error', 'Something went wrong.'))
            .finally(() => {
                saveBtn.disabled = false;
                saveBtn.textContent = 'Save';
            });
    });

    // Caption cancel
    document.getElementById('gallery-caption-cancel')?.addEventListener('click', closeCaptionEditor);

    // Backdrop click. Escape is handled by openDialog, which also restores focus.
    document.getElementById('gallery-caption-editor')?.addEventListener('click', (e) => {
        if (e.target.id === 'gallery-caption-editor') closeCaptionEditor();
    });

    // Caption save on Enter
    document.getElementById('gallery-caption-input')?.addEventListener('keydown', (e) => {
        if (e.key === 'Enter') {
            e.preventDefault();
            document.getElementById('gallery-caption-save')?.click();
        }
    });

    // --- Gallery categories ---
    //
    // The chip row, the filing dropdown and this dialog are three views of one list, so
    // every mutation below patches all three plus the Alpine catMap. Reloading the page
    // after each edit would be simpler and considerably worse: a curator files photos in
    // runs, and a full round trip between each one loses their place in the grid.
    let releaseCategoryEditor = null;

    function closeCategoryEditor() {
        const editor = document.getElementById('gallery-category-editor');
        if (!editor || editor.classList.contains('hidden')) return;
        editor.classList.add('hidden');
        releaseCategoryEditor?.();
        releaseCategoryEditor = null;
    }

    function categoryError(message) {
        const el = document.getElementById('gallery-category-error');
        if (!el) return;
        el.textContent = message || '';
        el.classList.toggle('hidden', !message);
    }

    function syncCategoryEmptyState() {
        const list = document.getElementById('gallery-category-list');
        document.getElementById('gallery-category-empty')?.classList.toggle('hidden', !!list?.children.length);
    }

    function addCategoryChip(category) {
        const row = document.querySelector('[data-category-chips]');
        if (!row) return;

        const chip = document.createElement('button');
        chip.type = 'button';
        chip.dataset.categoryChip = category.id;
        // Class strings mirror the Blade chips. Kept as literals rather than read off a
        // sibling, because the first category can be added when no sibling chip exists.
        chip.className = 'shrink-0 whitespace-nowrap rounded-full border px-3 py-1.5 text-xs font-medium transition-colors duration-150 active:scale-[0.97] motion-reduce:active:scale-100';
        chip.setAttribute('@click', `selectCat('${category.id}')`);
        chip.setAttribute(':aria-pressed', `activeCat === '${category.id}'`);
        chip.setAttribute(':class', `activeCat === '${category.id}' ? 'border-brand-500 bg-brand-500 text-white' : 'border-gray-200 bg-white text-gray-600 hover:border-gray-300 hover:text-gray-900 dark:border-gray-700 dark:bg-white/[0.03] dark:text-gray-400 dark:hover:border-gray-600 dark:hover:text-white'`);

        const name = document.createElement('span');
        name.setAttribute('data-category-chip-name', '');
        name.textContent = category.name;
        chip.appendChild(name);
        chip.appendChild(document.createTextNode(' '));

        const count = document.createElement('span');
        count.className = 'opacity-60';
        count.setAttribute('x-text', `catCount('${category.id}')`);
        chip.appendChild(count);

        // Before "Other", which is always the last chip, so the family's own categories stay
        // together in the order they arranged them.
        row.insertBefore(chip, row.querySelector('[data-chip-unfiled]'));
        if (typeof Alpine !== 'undefined') Alpine.initTree(chip);
    }

    function addCategoryRow(category) {
        const list = document.getElementById('gallery-category-list');
        if (!list) return;

        const li = document.createElement('li');
        li.className = 'flex items-center gap-2';
        li.dataset.categoryRow = category.id;

        const input = document.createElement('input');
        input.type = 'text';
        input.value = category.name;
        // Blade-rendered rows fall back to defaultValue for the rename baseline; a row built
        // here has none, because setting .value sets the property and not the attribute.
        input.dataset.previousName = category.name;
        input.maxLength = 60;
        input.setAttribute('aria-label', 'Category name');
        input.setAttribute('data-category-name', '');
        input.className = 'min-w-0 flex-1 rounded-lg border border-gray-300 bg-white px-3 py-1.5 text-sm focus:border-brand-300 focus:outline-none focus:ring-2 focus:ring-brand-500/20 dark:border-gray-600 dark:bg-gray-900';
        li.appendChild(input);

        const del = document.createElement('button');
        del.type = 'button';
        del.title = 'Delete category';
        del.dataset.categoryDelete = category.id;
        del.className = 'flex h-8 w-8 shrink-0 items-center justify-center rounded-lg text-gray-400 transition-colors duration-150 hover:bg-red-50 hover:text-red-500 active:scale-[0.97] motion-reduce:active:scale-100 dark:hover:bg-red-500/10';
        del.innerHTML = '<svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>';
        li.appendChild(del);

        list.appendChild(li);
        syncCategoryEmptyState();
    }

    function addCategoryOption(category) {
        const select = document.getElementById('gallery-category-select');
        if (!select) return;
        const option = document.createElement('option');
        option.value = category.id;
        option.textContent = category.name;
        select.appendChild(option);
    }

    document.addEventListener('click', (e) => {
        if (e.target.closest('[data-category-manage]')) {
            const editor = document.getElementById('gallery-category-editor');
            if (!editor) return;
            categoryError('');
            editor.classList.remove('hidden');
            releaseCategoryEditor = openDialog(editor, {
                initialFocus: document.getElementById('gallery-category-new'),
                onClose: closeCategoryEditor,
            });
        }
    });

    document.getElementById('gallery-category-done')?.addEventListener('click', closeCategoryEditor);

    document.getElementById('gallery-category-editor')?.addEventListener('click', (e) => {
        if (e.target.id === 'gallery-category-editor') closeCategoryEditor();
    });

    document.querySelector('[data-category-add]')?.addEventListener('click', () => {
        const input = document.getElementById('gallery-category-new');
        const button = document.querySelector('[data-category-add]');
        const name = (input?.value || '').trim();
        if (!name) {
            categoryError('Give the category a name.');
            input?.focus();
            return;
        }

        categoryError('');
        button.disabled = true;

        fetch(`${baseUrl}/gallery-categories`, fetchOpts('POST', { name }))
            .then(async r => ({ ok: r.ok, data: await r.json().catch(() => null) }))
            .then(({ ok, data }) => {
                if (!ok || !data?.success) {
                    categoryError(data?.error || 'Could not add that category.');
                    return;
                }
                addCategoryRow(data.category);
                addCategoryOption(data.category);
                addCategoryChip(data.category);
                input.value = '';
                input.focus();
            })
            .catch(() => categoryError('Something went wrong. Please try again.'))
            .finally(() => { button.disabled = false; });
    });

    document.getElementById('gallery-category-new')?.addEventListener('keydown', (e) => {
        if (e.key === 'Enter') {
            e.preventDefault();
            document.querySelector('[data-category-add]')?.click();
        }
    });

    // Renamed on blur rather than behind a Save button: the input already looks editable,
    // and there is nothing here worth confirming.
    document.addEventListener('focusout', (e) => {
        const input = e.target.closest('[data-category-name]');
        if (!input) return;
        const row = input.closest('[data-category-row]');
        const categoryId = row?.dataset.categoryRow;
        if (!categoryId) return;

        const name = input.value.trim();
        const previous = input.dataset.previousName ?? input.defaultValue;
        if (!name || name === previous) {
            input.value = previous;
            return;
        }

        fetch(`${baseUrl}/gallery-categories/${categoryId}`, fetchOpts('PATCH', { name }))
            .then(async r => ({ ok: r.ok, data: await r.json().catch(() => null) }))
            .then(({ ok, data }) => {
                if (!ok || !data?.success) {
                    categoryError(data?.error || 'Could not rename that category.');
                    input.value = previous;
                    return;
                }
                categoryError('');
                input.dataset.previousName = data.category.name;
                input.value = data.category.name;
                document.querySelector(`[data-category-chip="${categoryId}"] [data-category-chip-name]`)
                    ?.replaceChildren(document.createTextNode(data.category.name));
                const option = document.querySelector(`#gallery-category-select option[value="${categoryId}"]`);
                if (option) option.textContent = data.category.name;
            })
            .catch(() => {
                categoryError('Something went wrong. Please try again.');
                input.value = previous;
            });
    });

    document.addEventListener('click', async (e) => {
        const deleteBtn = e.target.closest('[data-category-delete]');
        if (!deleteBtn) return;
        const categoryId = deleteBtn.dataset.categoryDelete;
        const name = deleteBtn.closest('[data-category-row]')?.querySelector('[data-category-name]')?.value || 'this category';

        if (!await $confirm(`Photos in ${name} will stay in the gallery — they just won't be filed anywhere.`, {
            title: 'Delete this category?',
            confirmText: 'Delete',
        })) return;

        deleteBtn.disabled = true;

        try {
            const r = await fetch(`${baseUrl}/gallery-categories/${categoryId}`, fetchOpts('DELETE'));
            const data = await r.json().catch(() => null);

            if (!r.ok || !data?.success) {
                categoryError(data?.error || 'Could not delete that category.');
                deleteBtn.disabled = false;
                return;
            }

            categoryError('');
            galleryData()?.unfileCat(String(categoryId));
            deleteBtn.closest('[data-category-row]')?.remove();
            document.querySelector(`[data-category-chip="${categoryId}"]`)?.remove();
            document.querySelector(`#gallery-category-select option[value="${categoryId}"]`)?.remove();
            // The edit buttons of anything that was filed here still name the category.
            document.querySelectorAll(`[data-gallery-edit-caption][data-current-category="${categoryId}"]`)
                .forEach(btn => { btn.dataset.currentCategory = ''; });
            syncCategoryEmptyState();
        } catch {
            categoryError('Something went wrong. Please try again.');
            deleteBtn.disabled = false;
        }
    });

    // --- Quill editors ---
    // Most visitors here are reading a memorial someone shared with them and will never
    // open an editor, so Quill is fetched the first time one is actually needed rather
    // than blocking the first paint of every visit.
    let chapterQuill;
    let biographyQuill;
    let quillScriptPromise = null;
    let composerEditorsPromise = null;

    function loadQuill() {
        if (typeof Quill !== 'undefined') return Promise.resolve();
        if (quillScriptPromise) return quillScriptPromise;

        quillScriptPromise = new Promise((resolve, reject) => {
            const css = document.createElement('link');
            css.rel = 'stylesheet';
            css.href = 'https://cdn.quilljs.com/1.3.7/quill.snow.css';
            document.head.appendChild(css);

            const script = document.createElement('script');
            script.src = 'https://cdn.quilljs.com/1.3.7/quill.min.js';
            script.async = true;
            script.onload = () => resolve();
            script.onerror = () => {
                // Let a later attempt retry rather than wedging every editor on the page.
                quillScriptPromise = null;
                reject(new Error('Quill failed to load'));
            };
            document.head.appendChild(script);
        });

        return quillScriptPromise;
    }

    /**
     * The two page-level composers (the story composer, and the biography editor). Resolves
     * once they exist, so callers can await it instead of assuming the variables are set.
     */
    function initComposerEditors() {
        if (composerEditorsPromise) return composerEditorsPromise;

        const mounts = ['chapter-editor', 'biography-editor']
            .filter(id => document.getElementById(id));
        if (!mounts.length) return Promise.resolve();

        composerEditorsPromise = loadQuill().then(() => {
            const quillToolbar = [
                [{ 'size': ['small', false, 'large', 'huge'] }],
                ['bold', 'italic', 'underline'],
                [{ 'color': [] }],
                ['link', 'blockquote'],
                [{ 'list': 'ordered' }, { 'list': 'bullet' }],
                [{ 'align': [] }],
                ['clean'],
                ['code-block']
            ];
            const quillOpts = {
                theme: 'snow',
                placeholder: 'Share your memories...',
                modules: {
                    toolbar: quillToolbar
                }
            };
            if (!chapterQuill && document.getElementById('chapter-editor')) {
                chapterQuill = new Quill('#chapter-editor', quillOpts);
                chapterQuill.on('text-change', () => {
                    const el = document.getElementById('chapter-content');
                    if (el) el.value = chapterQuill.root.innerHTML;
                    syncComposerSubmitState();
                });
            }
            if (!biographyQuill && document.getElementById('biography-editor')) {
                biographyQuill = new Quill('#biography-editor', quillOpts);
                biographyQuill.on('text-change', () => {
                    const el = document.getElementById('biography-content');
                    if (el) el.value = biographyQuill.root.innerHTML;
                });
            }
        }).catch(() => {
            composerEditorsPromise = null;
            $toast('error', 'The editor could not be loaded. Check your connection and try again.');
        });

        return composerEditorsPromise;
    }

    // --- The story composer ---
    //
    // One composer, at the top of the feed, opening in place. Both of the ones it replaced
    // were buttons that scrolled you to a form somewhere else before you could type.
    const storyComposer = document.getElementById('story-composer');
    const storyComposerForm = document.getElementById('story-composer-form');
    const storyComposerPrompt = document.getElementById('story-composer-open');
    const cancelStoryBtn = document.getElementById('cancel-story-btn');
    const tributePostForm = document.getElementById('tribute-post-form');

    /** Has the visitor written anything worth posting? Quill submits markup, not '', when empty. */
    function composerHasWords() {
        const html = chapterQuill ? chapterQuill.root.innerHTML : (document.getElementById('chapter-content')?.value || '');
        return !!html.replace(/<(p|br|div)[^>]*>|<\/(p|div)>|&nbsp;|\s/gi, '').trim();
    }

    /** A Post button that cannot do anything says so, rather than failing after the tap. */
    function syncComposerSubmitState() {
        const btn = tributePostForm?.querySelector('button[type="submit"]');
        if (!btn || btn.dataset.busy === '1') return;
        const files = tributePostForm?.querySelector('input[name="files[]"]')?.files;
        btn.disabled = !composerHasWords() && !files?.length;
    }

    /**
     * @param {string} marker  Preselect flower/candle/prayer — used when somebody taps a
     *                         card and then decides to say something about it.
     */
    function openStoryComposer(marker) {
        if (!storyComposer) return;
        switchToTab('stories');
        storyComposer.dataset.open = '1';
        storyComposerForm?.classList.remove('hidden');
        storyComposerPrompt?.setAttribute('aria-expanded', 'true');

        // Empty clears the lot — a story with no marker is the default and has to be
        // reachable, now that there is no chip standing for it.
        tributePostForm?.querySelectorAll('input[name="story-marker"]').forEach(r => { r.checked = false; });
        if (marker) {
            const radio = tributePostForm?.querySelector(`input[name="story-marker"][value="${marker}"]`);
            if (radio) radio.checked = true;
        }

        syncComposerSubmitState();
        storyComposer.scrollIntoView({ behavior: 'smooth', block: 'center' });
        initComposerEditors().then(() => {
            document.querySelector('#chapter-editor .ql-editor')?.focus();
        });
    }

    function closeStoryComposer() {
        if (!storyComposer) return;
        storyComposer.dataset.open = '0';
        storyComposerForm?.classList.add('hidden');
        storyComposerPrompt?.setAttribute('aria-expanded', 'false');
    }

    // --- The draft survives the sign-in round trip ---
    //
    // When posting turns out to need signing in, the page navigates away and Quill's
    // contents would go with it — which, for someone who just wrote three paragraphs about
    // a person they lost, is the cruellest possible failure. So the draft is stashed
    // before leaving and poured back into a reopened composer when they land back here
    // (sign-in returns them via ?return=). sessionStorage, not localStorage: a draft
    // belongs to this visit, not to the machine.
    const COMPOSER_DRAFT_KEY = `memorial-draft:${location.pathname}`;

    function stashComposerDraft() {
        try {
            sessionStorage.setItem(COMPOSER_DRAFT_KEY, JSON.stringify({
                title: tributePostForm?.title?.value || '',
                content: chapterQuill ? chapterQuill.root.innerHTML : (document.getElementById('chapter-content')?.value || ''),
                marker: tributePostForm?.querySelector('input[name="story-marker"]:checked')?.value || '',
            }));
        } catch (_) { /* storage unavailable — the sign-in redirect still works */ }
    }

    function restoreComposerDraft() {
        let draft = null;
        try {
            draft = JSON.parse(sessionStorage.getItem(COMPOSER_DRAFT_KEY) || 'null');
            if (draft) sessionStorage.removeItem(COMPOSER_DRAFT_KEY);
        } catch (_) { return; }
        if (!draft || (!draft.title && !draft.content)) return;

        openStoryComposer(draft.marker || '');
        if (tributePostForm?.title) tributePostForm.title.value = draft.title || '';
        initComposerEditors().then(() => {
            if (chapterQuill && draft.content) {
                // Their own words, written in this same editor minutes ago; the server
                // sanitises every post on the way in regardless.
                chapterQuill.clipboard.dangerouslyPasteHTML(0, draft.content);
            }
            $toast('success', 'Welcome back — your words are still here.');
        });
    }

    restoreComposerDraft();

    /**
     * The marker artwork for a story built here, matching what the tribute-art partial
     * renders server-side so a card added by JS is indistinguishable from one from Blade.
     *
     * Lifted off the composer's own chip rather than assembled from a hardcoded path: the
     * chip already holds whatever that partial decided to draw — a PNG the family dropped
     * into public/images/tributes, or the inline SVG fallback — under whatever asset URL
     * this install happens to serve. Nothing here has to know about any of it.
     */
    function markerArtHtml(type) {
        if (!type) return '';
        const art = document.querySelector(`#story-composer input[name="story-marker"][value="${type}"]`)
            ?.closest('.story-marker-chip')?.querySelector('.story-marker-chip__art')?.innerHTML;

        return art ? `<span data-post-marker-art class="pointer-events-none block h-9 w-9 shrink-0" aria-hidden="true">${art}</span>` : '';
    }

    // --- Clearing a marker ---
    //
    // A radio cannot be unchecked by clicking it, and there is no longer a chip standing
    // for "no marker" — an unmarked story is simply what you get by choosing nothing, so a
    // chip naming that state was a control for something you already had. Tapping the
    // chosen marker a second time therefore has to clear it, which means knowing what was
    // selected *before* the browser applied the new selection.
    //
    // Delegated, because the story edit panels carry their own group and one of those can
    // arrive in the feed long after this runs.
    const markerWas = new WeakMap();
    const snapshotMarker = (group) => {
        if (group) markerWas.set(group, group.querySelector('input:checked')?.value ?? '');
    };

    document.addEventListener('pointerdown', (e) => snapshotMarker(e.target.closest('[data-marker-group]')));
    document.addEventListener('keydown', (e) => {
        // Space activates a focused radio; arrow keys move between them, which is a
        // different gesture and should keep selecting rather than clearing.
        if (e.key === ' ' || e.key === 'Spacebar') snapshotMarker(e.target.closest('[data-marker-group]'));
    });
    document.addEventListener('click', (e) => {
        // The label forwards its click to the sr-only input, so both bubble here. Acting
        // only on the input's own event keeps this from firing twice per tap.
        const input = e.target.closest('[data-marker-group] input[type="radio"]');
        if (!input) return;
        const group = input.closest('[data-marker-group]');
        const cleared = markerWas.get(group) === input.value;
        if (cleared) input.checked = false;
        markerWas.set(group, cleared ? '' : input.value);
    });

    storyComposerPrompt?.addEventListener('click', () => openStoryComposer());
    document.addEventListener('click', (e) => {
        if (e.target.closest('[data-open-story-composer]')) openStoryComposer();
    });
    tributePostForm?.querySelector('input[name="files[]"]')?.addEventListener('change', syncComposerSubmitState);

    if (tributePostForm) {
        let chapterFormSubmitting = false;
        syncComposerSubmitState();

        cancelStoryBtn?.addEventListener('click', () => {
            if (chapterQuill) chapterQuill.setText('');
            tributePostForm.reset();
            closeStoryComposer();
        });

        tributePostForm?.addEventListener('submit', (e) => {
            e.preventDefault();
            e.stopImmediatePropagation();
            if (chapterFormSubmitting) return;
            chapterFormSubmitting = true;

            const form = e.target;
            const submitBtn = form.querySelector('button[type="submit"]');
            submitBtn.disabled = true;
            submitBtn.dataset.busy = '1';
            const originalText = submitBtn.textContent;
            submitBtn.textContent = 'Posting...';
            form.style.pointerEvents = 'none';

            const resetButton = () => {
                chapterFormSubmitting = false;
                submitBtn.dataset.busy = '0';
                submitBtn.disabled = false;
                submitBtn.textContent = originalText;
                form.style.pointerEvents = '';
                syncComposerSubmitState();
            };

            const marker = form.querySelector('input[name="story-marker"]:checked')?.value || '';

            const fd = new FormData();
            fd.append('idempotency_key', crypto.randomUUID?.() || Date.now().toString(36) + Math.random().toString(36).slice(2));
            fd.append('title', form.title?.value || '');
            fd.append('content', chapterQuill ? chapterQuill.root.innerHTML : (form.content?.value || ''));
            // Only when there is one. An empty string would fail `Rule::in`, so the absence
            // of a marker has to be the absence of the field.
            if (marker) fd.append('tribute_type', marker);
            fd.append('_token', csrf);
            if (!isAuthenticated) {
                const guestName = document.getElementById('chapter-guest-name')?.value?.trim();
                const guestEmail = document.getElementById('chapter-guest-email')?.value?.trim();
                if (!guestName || !guestEmail) {
                    $toast('warning', 'Please add your name and email so people know who wrote this.');
                    resetButton();
                    return;
                }
                fd.append('guest_name', guestName);
                fd.append('guest_email', guestEmail);
            }
            const files = form.querySelector('input[name="files[]"]')?.files;
            if (files?.length) {
                for (let i = 0; i < files.length; i++) {
                    fd.append('files[]', files[i]);
                }
            }
            const hasMedia = !!(files && files.length);
            const uploadLabel = hasMedia
                ? 'Posting your story and media…'
                : 'Posting your story…';
            postFormDataWithUploadProgress(`${baseUrl}/tribute-post`, fd, { label: uploadLabel })
                .then(data => {
                    adoptSession(data);
                    if (data.success && data.post) {
                        prependPostArticle(data.post);
                        latestKnownPostId = Math.max(latestKnownPostId, data.post.id || 0);
                        if (chapterQuill) chapterQuill.setText('');
                        form.reset();
                        closeStoryComposer();
                        bumpStoryCount(1);
                        // Their own story might not match whatever filter is on, and a
                        // Post button that appears to do nothing is worse than the filter
                        // being reset for them.
                        document.querySelector('.story-filter--all')?.click();
                        applyFeedFilters();
                    } else if (data.error) {
                        $toast('error', data.error);
                    }
                    resetButton();
                })
                .catch(err => {
                    if (err.requiresLogin) {
                        // Their address already has an account. Stash the words — the
                        // sign-in navigates away — then send them off; the return trip
                        // lands back here and restoreComposerDraft() reopens the
                        // composer holding everything they wrote.
                        stashComposerDraft();
                        goSignIn(err.message);
                    } else {
                        $toast('error', err.message || 'Something went wrong. Please try again.');
                    }
                    resetButton();
                });
        });
    }

    // --- Inline editing (admin/owner only) ---
    if (canEdit) {
        document.querySelectorAll('[data-editable]').forEach(el => {
            const section = el.dataset.editable;
            const displayEl = el.querySelector('[data-display]');
            const editEl = el.querySelector('[data-edit]');
            const pencilBtn = el.querySelector('[data-edit-trigger]');

            if (!displayEl || !editEl || !pencilBtn) return;

            const plainToHtml = (text) => {
                if (!text || !String(text).trim()) return '';
                const div = document.createElement('div');
                div.textContent = text;
                const escaped = div.innerHTML;
                const withBold = escaped.replace(/\*\*(.+?)\*\*/g, '<strong>$1</strong>');
                const paragraphs = withBold.split(/\n\n+/).filter(p => p.trim());
                if (paragraphs.length === 0) return withBold.replace(/\n/g, '<br>');
                return paragraphs.map(p => '<p>' + p.trim().replace(/\n/g, '<br>') + '</p>').join('');
            };

            pencilBtn.addEventListener('click', () => {
                displayEl.classList.add('hidden');
                editEl.classList.remove('hidden');
                const input = editEl.querySelector('input, textarea');
                if (section === 'biography') {
                    // Use display content as source (what's shown on page) - most reliable.
                    // Read it before awaiting the editor, while the markup is untouched.
                    let initial = '';
                    const placeholder = 'Add biography...';
                    if (displayEl.textContent.trim() !== placeholder) {
                        initial = displayEl.innerHTML.trim();
                    }
                    initComposerEditors().then(() => {
                        if (!biographyQuill) return;
                        requestAnimationFrame(() => {
                            biographyQuill.setContents([]);
                            if (initial) {
                                if (initial.includes('<')) {
                                    biographyQuill.clipboard.dangerouslyPasteHTML(0, initial);
                                } else {
                                    const html = plainToHtml(initial);
                                    biographyQuill.clipboard.dangerouslyPasteHTML(0, html);
                                }
                            }
                            biographyQuill.focus();
                        });
                    });
                } else if (input) {
                    if (section !== 'biography') {
                        input.value = displayEl.textContent.trim();
                    }
                    input.focus();
                }
            });

            const formatBiography = (text) => {
                if (!text || !String(text).trim()) return 'Add biography...';
                const div = document.createElement('div');
                div.textContent = text;
                const escaped = div.innerHTML;
                return escaped.replace(/\*\*(.+?)\*\*/g, '<strong>$1</strong>').replace(/\n/g, '<br>');
            };

            const save = () => {
                const saveBtn = editEl.querySelector('[data-save]');
                const origText = saveBtn?.textContent || 'Save';
                if (saveBtn) {
                    saveBtn.textContent = saveBtn.dataset.savingText || 'Saving...';
                    saveBtn.disabled = true;
                }

                let body = { section };
                if (section === 'dates') {
                    const birthInput = editEl.querySelector('[data-date-type="birth"]');
                    const deathInput = editEl.querySelector('[data-date-type="death"]');
                    body.date_of_birth = birthInput?.value || null;
                    body.date_of_passing = deathInput?.value || null;
                } else if (section === 'biography' && biographyQuill) {
                    body.value = biographyQuill.root.innerHTML?.trim() ?? '';
                } else {
                    const input = editEl.querySelector('input, textarea');
                    body.value = input?.value?.trim() ?? '';
                }

                fetch(`${baseUrl}/section`, fetchOpts('PATCH', body))
                    .then(r => r.json())
                    .then(data => {
                        if (data.success) {
                            if (section === 'dates') {
                                const birthInput = editEl.querySelector('[data-date-type="birth"]');
                                const deathInput = editEl.querySelector('[data-date-type="death"]');
                                const parts = [];
                                if (birthInput?.value) parts.push(birthInput.value);
                                if (deathInput?.value) parts.push(deathInput.value);
                                displayEl.textContent = parts.join(' – ') || 'Add dates';
                            } else if (section === 'biography') {
                                const value = biographyQuill ? biographyQuill.root.innerHTML?.trim() ?? '' : (editEl.querySelector('input, textarea')?.value?.trim() ?? '');
                                displayEl.innerHTML = value && value !== '<p><br></p>' ? (value.includes('<') ? value : formatBiography(value)) : formatBiography('');
                            } else {
                                const input = editEl.querySelector('input, textarea');
                                const value = input?.value?.trim() ?? '';
                                displayEl.textContent = value || (section === 'date_of_birth' || section === 'date_of_passing' ? '' : '—');
                            }
                            displayEl.classList.remove('hidden');
                            editEl.classList.add('hidden');
                        } else {
                            // Keep the edit form open so the user's input survives the retry.
                            $toast('error', data.error || 'Saving failed. Try again.');
                        }
                    })
                    .catch(() => $toast('error', 'Saving failed. Check your connection and try again.'))
                    .finally(() => {
                        if (saveBtn) {
                            saveBtn.textContent = saveBtn.dataset.saveText || origText;
                            saveBtn.disabled = false;
                        }
                    });
            };

            editEl.querySelector('[data-save]')?.addEventListener('click', save);
            editEl.querySelector('input, textarea')?.addEventListener('keydown', (e) => {
                if (e.key === 'Enter' && !e.shiftKey) {
                    e.preventDefault();
                    save();
                }
                if (e.key === 'Escape') {
                    displayEl.classList.remove('hidden');
                    editEl.classList.add('hidden');
                }
            });
        });
    }

    // --- Guest modal (name + email for tributes/reactions) ---
    const guestModal = document.getElementById('guest-modal');
    const guestForm = document.getElementById('guest-form');
    let pendingAction = null;

    let releaseGuestModal = null;

    window.showGuestModal = (action) => {
        pendingAction = action;
        guestModal?.classList.remove('hidden');
        releaseGuestModal = openDialog(guestModal, {
            initialFocus: document.getElementById('guest-name'),
            onClose: () => window.hideGuestModal(),
        });
    };

    window.hideGuestModal = () => {
        guestModal?.classList.add('hidden');
        pendingAction = null;
        releaseGuestModal?.();
        releaseGuestModal = null;
    };

    document.addEventListener('click', (e) => {
        if (e.target.closest('[data-close-guest-modal]')) window.hideGuestModal();
        if (e.target.closest('[data-close-edit-chapter-modal]')) window.closeEditChapterModal?.();
    });

    guestForm?.addEventListener('submit', (e) => {
        e.preventDefault();
        const name = document.getElementById('guest-name')?.value?.trim();
        const email = document.getElementById('guest-email')?.value?.trim();
        if (!name || !email) return;

        // The one-tap cards no longer come through here: a tap asks for nothing. What is
        // left is the things that still carry a name — a heart on someone's story, and a
        // comment.
        if (pendingAction?.type === 'reaction') {
            pendingAction.callback?.(name, email) ?? submitReaction(pendingAction.payload, name, email);
        } else if (pendingAction?.type === 'comment') {
            pendingAction.callback?.(name, email);
        }
        hideGuestModal();
    });

    // --- Taps: flower, candle, prayer ---
    //
    // A tap is a gesture and nothing more — it moves the tally under its card and leaves
    // nothing in the feed, the way a like does. Anything anyone writes is a story and goes
    // through the composer instead. Resolves to ok only when the tap was recorded, so the
    // caller knows whether it may celebrate.
    //
    // Carries no identity. It used to take a name and an email collected from a modal, and
    // the server turned those into an account; now the server recognises a returning
    // visitor by a cookie of its own, and a guest is asked for nothing at all.
    function submitTribute(payload) {
        const body = { ...payload };
        const url = tributeUrl || `${baseUrl}/tribute`;
        return fetch(url, fetchOpts('POST', body))
            .then(async (r) => {
                const data = await r.json().catch(() => ({}));
                if (!r.ok) {
                    const msg = data.error || data.message || (data.errors && Object.values(data.errors).flat().find(Boolean)) || `Request failed (${r.status})`;
                    throw new Error(msg);
                }
                return data;
            })
            .then(data => {
                if (data.success) {
                    // A repeat tap is a success, not a failure — the server hands back the
                    // one this person already left. Nothing moves, so the only thing that
                    // happens is the burst the caller played.
                    if (data.duplicate) {
                        return { ok: true, duplicate: true };
                    }

                    updateTributeActionCount(data.tribute?.type || body.type, 1);
                    return { ok: true, duplicate: false };
                } else if (data.error) {
                    $toast('error', data.error);
                }
                return { ok: false, duplicate: false };
            })
            .catch(err => {
                console.error('Tribute error:', err);
                $toast('error', err.message || 'Could not submit tribute. Please try again.');
                return { ok: false, duplicate: false };
            });
    }

    // The invite panel offers the same channels as a story's Share, through the same
    // partial and the same delegated handlers — so all this has to do is open it, and get
    // out of the way of any other dropdown that is already showing.
    document.getElementById('invite-share-btn')?.addEventListener('click', (e) => {
        const dropdown = document.getElementById('invite-share-dropdown');
        if (!dropdown) return;
        const opening = dropdown.classList.contains('hidden');
        document.querySelectorAll('[data-share-dropdown]').forEach(d => d.classList.add('hidden'));
        dropdown.classList.toggle('hidden', !opening);
        e.currentTarget.setAttribute('aria-expanded', opening ? 'true' : 'false');
    });

    // --- One-tap tribute cards: burst on tap, count ticks up in place ---
    //
    // Modelled on the Instagram/TikTok double-tap: a motif pops at the point of contact and
    // a scatter of particles radiates outward, arcing and fading. Everything runs through the
    // Web Animations API rather than keyframes so it stays on the compositor and so repeated
    // taps each get their own independent, interruptible run.
    const BURST_MOTIFS = {
        flower: {
            pop: '<svg viewBox="0 0 48 48" width="72" height="72"><g transform="translate(24,24)"><ellipse rx="6" ry="10" cy="-10" fill="#f9a8d4"/><ellipse rx="6" ry="10" cy="-10" fill="#f472b6" transform="rotate(72)"/><ellipse rx="6" ry="10" cy="-10" fill="#f9a8d4" transform="rotate(144)"/><ellipse rx="6" ry="10" cy="-10" fill="#f472b6" transform="rotate(216)"/><ellipse rx="6" ry="10" cy="-10" fill="#f9a8d4" transform="rotate(288)"/><circle r="5" fill="#fbbf24"/></g></svg>',
            particles: [
                '<svg viewBox="0 0 24 24" width="18" height="18"><path d="M12 2c5 4 8 8 8 12a8 8 0 01-16 0c0-4 3-8 8-12z" fill="#f472b6"/></svg>',
                '<svg viewBox="0 0 24 24" width="14" height="14"><path d="M12 2c5 4 8 8 8 12a8 8 0 01-16 0c0-4 3-8 8-12z" fill="#f9a8d4"/></svg>',
                '<svg viewBox="0 0 24 24" width="12" height="12"><path d="M12 2c5 4 8 8 8 12a8 8 0 01-16 0c0-4 3-8 8-12z" fill="#fbcfe8"/></svg>',
            ],
        },
        candle: {
            pop: '<svg viewBox="0 0 48 48" width="72" height="72"><ellipse cx="24" cy="24" rx="11" ry="16" fill="#f97316" opacity="0.9"/><ellipse cx="24" cy="26" rx="6" ry="11" fill="#fbbf24"/><ellipse cx="24" cy="28" rx="3" ry="6" fill="#fef3c7"/></svg>',
            particles: [
                '<svg viewBox="0 0 24 24" width="14" height="14"><path d="M12 0l2.6 9.4L24 12l-9.4 2.6L12 24l-2.6-9.4L0 12l9.4-2.6z" fill="#fbbf24"/></svg>',
                '<svg viewBox="0 0 24 24" width="10" height="10"><path d="M12 0l2.6 9.4L24 12l-9.4 2.6L12 24l-2.6-9.4L0 12l9.4-2.6z" fill="#fde68a"/></svg>',
                '<svg viewBox="0 0 24 24" width="8" height="8"><circle cx="12" cy="12" r="12" fill="#fcd34d"/></svg>',
            ],
        },
        prayer: {
            pop: '<svg viewBox="0 0 48 48" width="76" height="76"><g stroke="#fbbf24" stroke-width="1.6" stroke-linecap="round" opacity="0.6"><path d="M24 2.5v3M12.9 6.8l1.8 2.5M35.1 6.8l-1.8 2.5M6.4 17.2l3 .8M41.6 17.2l-3 .8"/></g><circle cx="24" cy="17" r="12" fill="#fbbf24" opacity="0.3"/><g transform="translate(48,0) scale(-1,1)"><path d="M22.9 3.5c-2.2 2-3.9 4.6-5.1 7.7-1.7 4.3-2.6 8.8-2.6 13.5v5.6c0 2.9 1.8 5.5 4.5 6.5l3.2 1.2z" fill="#c07f4a"/><path d="M22.9 33v6.5l-4.4 2.7a4.8 4.8 0 0 1-6.6-1.6 4.8 4.8 0 0 1 1.6-6.6l5.4-3.3z" fill="#6b7d94"/></g><path d="M22.9 3.5c-2.2 2-3.9 4.6-5.1 7.7-1.7 4.3-2.6 8.8-2.6 13.5v5.6c0 2.9 1.8 5.5 4.5 6.5l3.2 1.2z" fill="#e8ab77"/><path d="M22.9 33v6.5l-4.4 2.7a4.8 4.8 0 0 1-6.6-1.6 4.8 4.8 0 0 1 1.6-6.6l5.4-3.3z" fill="#94a3b8"/></svg>',
            particles: [
                '<svg viewBox="0 0 24 24" width="15" height="15"><path d="M12 0l2.2 9.8L24 12l-9.8 2.2L12 24l-2.2-9.8L0 12l9.8-2.2z" fill="#fcd34d"/></svg>',
                '<svg viewBox="0 0 24 24" width="11" height="11"><path d="M12 0l2.2 9.8L24 12l-9.8 2.2L12 24l-2.2-9.8L0 12l9.8-2.2z" fill="#e0f2fe"/></svg>',
                '<svg viewBox="0 0 24 24" width="8" height="8"><circle cx="12" cy="12" r="12" fill="#fef3c7"/></svg>',
            ],
        },
    };

    // --- Petal rain -------------------------------------------------------------
    //
    // Seven petal silhouettes, each drawn once into a hidden sprite and stamped out with
    // <use>. The body takes `currentColor` so one shape can be recoloured per petal, and
    // the shading is two clipped overlays — a dark side and a lit side — rather than a
    // gradient per petal. Clipping matters: without it the highlight spills past the
    // silhouette on the curled and twisted shapes.
    //
    // Seven shapes x five reds x a mirror flip is seventy distinct petals before the
    // per-petal size, tumble and depth are applied, which is more variety than a folder
    // of fifty fixed assets would give for a fraction of the bytes — and it stays one
    // sprite in the DOM however many petals are in the air.
    const PETAL_SPRITE = `
<svg width="0" height="0" aria-hidden="true" style="position:absolute"><defs>
<path id="mpb-0" d="M30 78C18 62 4 48 4 32 4 15 16 4 30 12 44 4 56 15 56 32c0 16-14 30-26 46z"/>
<path id="mpb-1" d="M30 78C24 60 14 44 14 28 14 12 21 2 30 2s16 10 16 26c0 16-10 32-16 50z"/>
<path id="mpb-2" d="M30 78C16 62 6 46 8 30 10 14 22 2 34 6c10 3 16 14 14 28-2 16-10 30-18 44z"/>
<path id="mpb-3" d="M30 76C12 68 2 50 4 32 6 16 18 6 30 10c12-4 24 6 26 22 2 18-8 36-26 44z"/>
<path id="mpb-4" d="M32 76c-7-14-10-30-8-45 2-13 7-24 13-29 5 11 5 25 2 40-3 15-5 26-7 34z"/>
<path id="mpb-5" d="M28 78c-9-13-5-25 1-34 6-9 5-19-4-27 15-8 31 3 31 18 0 11-8 19-13 26-5 7-7 12-6 17z"/>
<path id="mpb-6" d="M30 74c-8-10-14-22-13-34C18 28 25 18 34 16c9-2 16 5 16 16 0 14-10 30-20 42z"/>
<clipPath id="mpc-0"><use href="#mpb-0"/></clipPath>
<clipPath id="mpc-1"><use href="#mpb-1"/></clipPath>
<clipPath id="mpc-2"><use href="#mpb-2"/></clipPath>
<clipPath id="mpc-3"><use href="#mpb-3"/></clipPath>
<clipPath id="mpc-4"><use href="#mpb-4"/></clipPath>
<clipPath id="mpc-5"><use href="#mpb-5"/></clipPath>
<clipPath id="mpc-6"><use href="#mpb-6"/></clipPath>
<symbol id="mp-0" viewBox="0 0 60 80"><use href="#mpb-0" fill="currentColor"/><g clip-path="url(#mpc-0)"><path d="M30 82C16 64 0 48 0 30c0-10 3-18 9-22 4 10 5 22 4 34-1 14 4 25 17 43z" fill="#000" opacity=".2"/><path d="M36 10c9-3 19 4 20 16 1 8-1 15-5 22 1-11-1-21-6-28-3-5-8-8-9-10z" fill="#fff" opacity=".26"/><path d="M30 74c-2-16-2-32 0-52M30 74c6-12 12-24 15-36M30 74c-6-12-12-24-15-36" stroke="#000" stroke-width="1.1" opacity=".14" fill="none"/></g></symbol>
<symbol id="mp-1" viewBox="0 0 60 80"><use href="#mpb-1" fill="currentColor"/><g clip-path="url(#mpc-1)"><path d="M30 82C22 60 12 44 12 28c0-9 2-17 6-22-1 12 0 24 3 36 3 12 7 24 9 40z" fill="#000" opacity=".2"/><path d="M34 0c8 3 14 12 14 26 0 9-3 18-6 26 1-13 0-25-2-34-2-8-4-15-6-18z" fill="#fff" opacity=".26"/><path d="M30 74c-1-18 0-38 0-52" stroke="#000" stroke-width="1.1" opacity=".14" fill="none"/></g></symbol>
<symbol id="mp-2" viewBox="0 0 60 80"><use href="#mpb-2" fill="currentColor"/><g clip-path="url(#mpc-2)"><path d="M34 6c-7 9-10 20-8 31 2 11 6 21 4 45-16-18-28-34-26-52C6 12 20 0 34 6z" fill="#000" opacity=".18"/><path d="M34 6c11 3 17 13 16 27-1 10-4 19-9 27 2-13 1-24-2-33-3-9-6-16-5-21z" fill="#fff" opacity=".28"/></g></symbol>
<symbol id="mp-3" viewBox="0 0 60 80"><use href="#mpb-3" fill="currentColor"/><g clip-path="url(#mpc-3)"><path d="M34 80C14 70 0 50 2 30 4 14 16 4 34 8z" fill="#000" opacity=".16"/><path d="M30 10c14-4 26 6 28 22 1 11-2 22-8 31 2-15 0-28-6-38-5-9-11-14-14-15z" fill="#fff" opacity=".22"/><path d="M31 74C29 54 29 32 32 12" stroke="#000" stroke-width="1" opacity=".13" fill="none"/></g></symbol>
<symbol id="mp-4" viewBox="0 0 60 80"><use href="#mpb-4" fill="currentColor"/><g clip-path="url(#mpc-4)"><path d="M32 80c-9-14-12-32-10-47 1-8 3-15 6-20-1 14 0 27 3 39 3 12 5 22 1 28z" fill="#000" opacity=".24"/><path d="M37 0c6 11 6 25 3 40-1 6-2 11-3 16 0-14-1-26-2-35-1-9-2-17 2-21z" fill="#fff" opacity=".22"/></g></symbol>
<symbol id="mp-5" viewBox="0 0 60 80"><use href="#mpb-5" fill="currentColor"/><g clip-path="url(#mpc-5)"><path d="M28 82c-11-15-7-27-1-36 6-9 5-19-6-29 6-4 13-4 19-2-8 10-9 20-5 29 4 9 5 20-7 38z" fill="#000" opacity=".22"/><path d="M41 2c11 3 17 11 17 21 0 11-8 19-13 26-3 4-6 8-7 11 3-12 7-22 8-31 1-11-2-20-5-27z" fill="#fff" opacity=".24"/></g></symbol>
<symbol id="mp-6" viewBox="0 0 60 80"><use href="#mpb-6" fill="currentColor"/><g clip-path="url(#mpc-6)"><path d="M30 78c-10-11-16-24-15-38 1-7 4-14 9-18-2 11-1 21 2 30 3 9 6 18 4 26z" fill="#000" opacity=".22"/><path d="M34 16c11-2 18 5 18 16 0 9-4 19-10 28 3-12 3-22 1-30-2-8-7-12-9-14z" fill="#fff" opacity=".24"/></g></symbol>
</defs></svg>`;

    // Sampled off the flower artwork itself rather than picked by eye, so the petals that
    // fall are the same purples the rose on the card is made of. Re-sample these whenever
    // that file changes — public/images/tributes/README.md says so for this reason.
    //
    // The artwork's own lightest tints are left out. Each petal carries a white highlight
    // overlay, and on a tint that pale the highlight takes the whole shape to near-white —
    // which in a field of purple reads as a different object rather than as a petal
    // catching the light.
    const PETAL_COLOURS = ['#b040e0', '#a030d0', '#9020c0', '#8010b0', '#7000a0', '#600090'];

    // Drawn from rather than picked uniformly, because the narrow shapes — the edge-on
    // sliver especially — read as a hairline at small sizes. A few are what sells the
    // field as three-dimensional; an even seventh of them looks like scratches.
    const PETAL_SHAPES = [0, 0, 0, 1, 1, 2, 2, 3, 3, 3, 4, 5, 5, 6, 6];

    // Three depth bands. Size, opacity, blur and speed all move together, because that
    // combination is what the eye reads as distance: the far band is small, dim and slow,
    // the near band is large, fast and defocused like something passing the lens.
    const PETAL_LAYERS = [
        { share: 0.42, width: [13, 24], opacity: [0.3, 0.5], blur: 1.4, duration: [5400, 7200], tumble: 0.6, sway: [30, 90] },
        { share: 0.40, width: [26, 46], opacity: [0.75, 1], blur: 0, duration: [3600, 5200], tumble: 1, sway: [50, 150] },
        { share: 0.18, width: [64, 118], opacity: [0.45, 0.7], blur: 3.5, duration: [2400, 3400], tumble: 1.35, sway: [80, 220] },
    ];

    const rand = (min, max) => min + Math.random() * (max - min);

    let petalLayer = null;
    let petalsFalling = 0;

    function getPetalLayer() {
        if (petalLayer && petalLayer.isConnected) return petalLayer;
        // Tracked separately from the layer: the sprite defines ids, so injecting it a
        // second time because the layer went missing would duplicate every one of them.
        if (!document.getElementById('mpb-0')) {
            document.body.insertAdjacentHTML('beforeend', PETAL_SPRITE);
        }
        petalLayer = document.createElement('div');
        petalLayer.className = 'memorial-petal-layer';
        petalLayer.setAttribute('aria-hidden', 'true');
        document.body.appendChild(petalLayer);
        return petalLayer;
    }

    /**
     * Keyframes for one petal's whole fall.
     *
     * The vertical travel is linear — gravity does not ease — while the horizontal
     * position is sampled off a sine so the petal is pushed sideways and back rather
     * than sliding in a straight diagonal. Sampling into fixed keyframes rather than
     * driving it per frame keeps the animation on the compositor.
     */
    function petalKeyframes(spec) {
        const { fromX, toX, fromY, toY, amp, waves, phase, rx, ry, rz, scale } = spec;
        const steps = 10;
        const frames = [];

        for (let i = 0; i <= steps; i++) {
            const t = i / steps;
            const x = fromX + (toX - fromX) * t + Math.sin(phase + t * Math.PI * waves) * amp;
            const y = fromY + (toY - fromY) * t;
            // In and out at the edges of the fall, so nothing blinks into existence
            // mid-screen and nothing is cut off at the bottom.
            const fade = t < 0.06 ? t / 0.06 : t > 0.88 ? (1 - t) / 0.12 : 1;

            frames.push({
                offset: t,
                opacity: fade,
                transform: `translate3d(${x.toFixed(1)}px, ${y.toFixed(1)}px, 0)`
                    + ` rotateX(${(rx * t).toFixed(1)}deg) rotateY(${(ry * t).toFixed(1)}deg) rotateZ(${(rz * t).toFixed(1)}deg)`
                    + ` scale(${scale})`,
            });
        }

        return frames;
    }

    // How long the field keeps emitting. Every petal's start is scattered across this
    // window and back into negative time, so the fall is a continuous stream rather than
    // one wave: spawning them all at once fills the screen and then empties it from the
    // top down as the first arrivals land, leaving a bare lower half within a second.
    const PETAL_EMIT_WINDOW = 3600;

    /**
     * Rain petals across the entire viewport.
     *
     * The ones given a negative delay start life part-way through their own fall, so the
     * screen is full the instant it is asked for instead of filling from the top — and
     * because they are genuinely mid-animation they arrive already moving rather than
     * popping into place.
     */
    function rainPetals() {
        if (prefersReducedMotion()) return;

        const layer = getPetalLayer();
        const vw = window.innerWidth;
        const vh = window.innerHeight;
        // Phones get a smaller cast: the screen is narrower, so the same count reads as
        // clutter and costs more on the weaker GPU.
        const target = vw >= 1024 ? 110 : vw >= 640 ? 88 : 58;
        // Repeat taps top the field back up rather than stacking a second curtain on it
        // or being swallowed — the same contract the tap burst has, without letting the
        // node count climb every time someone taps twice.
        const budget = Math.max(0, target - petalsFalling);
        if (budget === 0) return;

        PETAL_LAYERS.forEach((band) => {
            const count = Math.round(budget * band.share);

            for (let i = 0; i < count; i++) {
                // Capped against the viewport so the foreground band stays a petal
                // sweeping past rather than a red shape covering a third of a phone.
                const width = Math.min(rand(band.width[0], band.width[1]), vw * 0.26);
                const shape = PETAL_SHAPES[Math.floor(Math.random() * PETAL_SHAPES.length)];
                const colour = PETAL_COLOURS[Math.floor(Math.random() * PETAL_COLOURS.length)];
                const height = width * (80 / 60);

                const node = document.createElement('span');
                node.className = 'memorial-petal';
                node.style.opacity = '0';
                if (band.blur) node.style.filter = `blur(${band.blur}px)`;
                node.innerHTML = `<svg viewBox="0 0 60 80" width="${width.toFixed(0)}" height="${height.toFixed(0)}" style="color:${colour}"><use href="#mp-${shape}"/></svg>`;
                layer.appendChild(node);

                const fromX = rand(-width, vw);
                const duration = rand(band.duration[0], band.duration[1]);
                const frames = petalKeyframes({
                    fromX,
                    // Wind carries the whole field one way; how far depends on how long
                    // the petal is in the air.
                    toX: fromX + rand(-40, 170) * (duration / 4000),
                    fromY: -height - rand(0, vh * 0.25),
                    toY: vh + height,
                    amp: rand(band.sway[0], band.sway[1]),
                    waves: rand(1.5, 3.5),
                    phase: Math.random() * Math.PI * 2,
                    // Three axes: the Y flip is what shows the petal's back and reads as
                    // a real petal rather than a falling sticker. Both out-of-plane spins
                    // are kept well under a full turn, because a petal edge-on to the
                    // screen is a hairline — spin it freely and half the field disappears
                    // at any given moment. Z is in-plane, so it can turn as far as it likes.
                    rx: rand(-150, 150) * band.tumble,
                    ry: rand(150, 430) * band.tumble * (Math.random() < 0.5 ? -1 : 1),
                    rz: rand(-540, 540) * band.tumble,
                    // Mirrored on half of them, so a shape never repeats side by side.
                    scale: Math.random() < 0.5 ? 1 : -1,
                });

                petalsFalling++;
                const done = () => {
                    node.remove();
                    petalsFalling--;
                };
                node.animate(frames, {
                    duration,
                    // Linear: the sway is already baked into the keyframe positions, and
                    // easing the timeline on top would make the fall speed up and stall.
                    easing: 'linear',
                    // Uniform across one whole fall behind us and the emit window ahead:
                    // that keeps roughly the same number of petals in the air throughout
                    // instead of a wave that arrives and leaves together.
                    delay: rand(-duration * 0.9, PETAL_EMIT_WINDOW),
                    fill: 'forwards',
                }).finished.then(done).catch(done);
            }
        });
    }

    let burstLayer = null;

    function getBurstLayer() {
        if (burstLayer && burstLayer.isConnected) return burstLayer;
        burstLayer = document.createElement('div');
        burstLayer.className = 'memorial-burst-layer';
        burstLayer.setAttribute('aria-hidden', 'true');
        document.body.appendChild(burstLayer);
        return burstLayer;
    }

    const prefersReducedMotion = () => window.matchMedia('(prefers-reduced-motion: reduce)').matches;

    function spawnBurstNode(layer, html, x, y) {
        const node = document.createElement('span');
        node.className = 'memorial-burst-particle';
        node.innerHTML = html;
        node.style.left = `${x}px`;
        node.style.top = `${y}px`;
        layer.appendChild(node);
        return node;
    }

    /**
     * Fire the burst at a viewport coordinate. Safe to call repeatedly — each run owns its
     * own nodes and removes them on finish, so a rapid series of taps overlaps cleanly.
     */
    function burstFrom(x, y, type, artSrc) {
        // Motion is the whole point of this effect, so reduced-motion gets none of it
        // rather than a watered-down version. The count still updates.
        if (prefersReducedMotion()) return;

        const motif = BURST_MOTIFS[type] || BURST_MOTIFS.prayer;
        const layer = getBurstLayer();
        const easeOut = 'cubic-bezier(0.23, 1, 0.32, 1)';

        // A flower gets the whole screen: petals rain past from edge to edge instead of
        // the tap throwing a handful outward. The centre pop still plays on top, so the
        // tap keeps its own point of contact — it is the radial scatter that the rain
        // replaces, since firing both would put two sets of petals on screen at once.
        const rainsPetals = type === 'flower';
        if (rainsPetals) rainPetals();

        // A candle opens the full scene. Loaded on demand rather than bundled: it is the
        // largest thing on this page by some way, and most visitors never light one. The
        // pop below still plays over the top, so the page dims around the tap itself.
        if (type === 'candle') {
            import('./memorial-candle-scene.js')
                .then(m => m.playCandleScene({ originX: x, originY: y }))
                .catch(() => {});
        }

        // A prayer opens its own scene, and takes the card's artwork with it so the light
        // rises out of the same hands that were pressed.
        if (type === 'prayer') {
            import('./memorial-prayer-scene.js')
                .then(m => m.playPrayerScene({ originX: x, artSrc }))
                .catch(() => {});
        }

        // The centre pop is the card's own artwork blown up, so the burst reads as the icon
        // leaping off the card rather than a different graphic replacing it. Falls back to
        // the inline motif when a card is drawing SVG because its PNG is absent.
        const popHtml = artSrc
            ? `<img src="${escapeHtml(artSrc)}" alt="" width="132" height="132" style="display:block" />`
            : motif.pop;

        // Centre pop: overshoots slightly, then settles and fades — never from scale(0).
        const pop = spawnBurstNode(layer, popHtml, x, y);
        pop.style.transform = 'translate(-50%, -50%)';
        pop.animate([
            { transform: 'translate(-50%, -50%) scale(0.4)', opacity: 0 },
            { transform: 'translate(-50%, -50%) scale(1.15)', opacity: 1, offset: 0.35 },
            { transform: 'translate(-50%, -50%) scale(0.95)', opacity: 0.9, offset: 0.6 },
            { transform: 'translate(-50%, -50%) scale(1.35)', opacity: 0 },
        ], { duration: 720, easing: easeOut, fill: 'forwards' })
            .finished.then(() => pop.remove()).catch(() => pop.remove());

        const count = rainsPetals ? 0 : 18;
        for (let i = 0; i < count; i++) {
            const html = motif.particles[i % motif.particles.length];
            const node = spawnBurstNode(layer, html, x, y);

            // Spread evenly around the circle, jittered so it never looks like a clock face.
            const angle = (i / count) * Math.PI * 2 + (Math.random() - 0.5) * 0.5;
            const distance = 95 + Math.random() * 115;
            const driftX = Math.cos(angle) * distance;
            const driftY = Math.sin(angle) * distance;
            // Particles fall away at the end instead of stopping dead in mid-air.
            const gravity = 30 + Math.random() * 45;
            const spin = (Math.random() - 0.5) * 540;
            const duration = 820 + Math.random() * 380;

            node.animate([
                { transform: 'translate(-50%, -50%) translate(0px, 0px) scale(0.3) rotate(0deg)', opacity: 0 },
                { transform: `translate(-50%, -50%) translate(${driftX * 0.55}px, ${driftY * 0.55 - 10}px) scale(1) rotate(${spin * 0.4}deg)`, opacity: 1, offset: 0.3 },
                { transform: `translate(-50%, -50%) translate(${driftX}px, ${driftY + gravity}px) scale(0.55) rotate(${spin}deg)`, opacity: 0 },
            ], { duration, easing: easeOut, fill: 'forwards' })
                .finished.then(() => node.remove()).catch(() => node.remove());
        }
    }

    /** Bump the "N flowers" line under a card, keeping its singular/plural correct. */
    function updateTributeActionCount(type, delta) {
        const el = document.querySelector(`[data-tribute-action-count="${type}"]`);
        if (!el) return;
        const noun = el.dataset.noun || type;
        const next = Math.max(0, parseInt((el.textContent || '0').replace(/\D/g, '') || 0) + delta);
        el.textContent = `${next} ${next === 1 ? noun : noun + 's'}`;
    }

    document.querySelectorAll('[data-tribute-action]').forEach(card => {
        card.addEventListener('click', (e) => {
            e.preventDefault();
            const type = card.dataset.tributeAction;

            // Burst from the pointer itself when there is one; keyboard activation reports
            // 0,0, so fall back to the card's centre.
            const rect = card.getBoundingClientRect();
            const x = e.clientX || rect.left + rect.width / 2;
            const y = e.clientY || rect.top + rect.height / 2;
            // null when the card fell back to inline SVG, which burstFrom() handles.
            const artSrc = card.querySelector('.memorial-tribute-action__art img')?.src || null;

            // Past the plan's limit the card is still a card: it plays its effect and says
            // nothing about quotas, because a visitor tapping a candle has no idea what the
            // owner's plan is and should not be made to care. The request is what stops —
            // nothing is sent, so nothing is stored and no tally moves.
            if (card.closest('[data-tribute-quota-reached]')) {
                burstFrom(x, y, type, artSrc);
                return;
            }

            // One path for everybody, signed in or not. A tap asks for nothing — the server
            // tells two visitors apart by a cookie it issues itself — so there is nothing to
            // collect and no reason to make anyone wait.
            //
            // Fire immediately: waiting on the round trip is what makes a tap feel dead. It
            // plays on every tap, including repeats — the burst confirms the tap landed,
            // while the count only moves the first time. Same contract as double-tapping a
            // post you have already liked.
            burstFrom(x, y, type, artSrc);
            submitTribute({ type }).then(res => { if (res.ok) offerToSayMore(type); });
        });
    });

    // --- After a tap: the offer to say something ---
    //
    // A tap and a story are the same feeling at two lengths, and the page used to give no
    // way across. This appears under the cards once a tap lands, and only then: nobody owes
    // the memorial a paragraph, so it is an offer sitting quietly where the gesture was
    // made, not a prompt thrown in front of the person who just made one.
    const sayMoreBtn = document.getElementById('tribute-say-more');
    const SAY_MORE_LABELS = {
        flower: 'Say something with your flower',
        candle: 'Say something with your candle',
        prayer: 'Say something with your prayer',
    };

    function offerToSayMore(type) {
        if (!sayMoreBtn) return;
        sayMoreBtn.dataset.marker = type;
        const label = sayMoreBtn.querySelector('[data-say-more-label]');
        if (label) label.textContent = SAY_MORE_LABELS[type] || 'Add a few words';
        sayMoreBtn.classList.remove('hidden');
    }

    sayMoreBtn?.addEventListener('click', () => {
        openStoryComposer(sayMoreBtn.dataset.marker || '');
        sayMoreBtn.classList.add('hidden');
    });

    function avatarHtml(photo, name, size = 'h-10 w-10', fallbackClasses = 'bg-brand-100 dark:bg-brand-500/30 text-brand-600 dark:text-brand-400 text-sm font-semibold') {
        if (photo) {
            return `<img src="${escapeHtml(photo)}" alt="${escapeHtml(name || '')}" class="${size} shrink-0 rounded-full object-cover" />`;
        }
        const initial = (name || '?').charAt(0).toUpperCase();
        return `<div class="flex ${size} shrink-0 items-center justify-center rounded-full ${fallbackClasses}">${escapeHtml(initial)}</div>`;
    }

    // Renders one story into the feed — the same markup the page ships server-side,
    // rebuilt client-side. The composer uses it for your own story and the live poll
    // uses it for everyone else's, so both arrive looking identical.
    function prependPostArticle(p) {
        const feed = document.getElementById('life-feed');
        if (!feed) return;
        const mediaHtml = (p.media || []).map(m => {
            if (m.type === 'photo') return `<img src="${m.url}" alt="" class="max-w-full rounded-lg mt-2" />`;
            if (m.type === 'video') return buildVideoPlayerHtml(m.url, m.caption);
            if (m.type === 'music') return buildAudioPlayerHtml(m.url, m.caption, m.filename);
            return '';
        }).join('');
        const article = document.createElement('article');
        article.id = 'chapter-' + p.id;
        article.className = 'life-feed-post relative overflow-visible rounded-xl border border-gray-300 dark:border-gray-800 bg-white dark:bg-white/[0.03]';
        article.dataset.postId = p.id;
        article.dataset.chapterId = '';
        article.dataset.marker = p.tribute_type || 'story';
        article.innerHTML = `
            <div class="p-4">
                <div class="flex items-center gap-3">
                    ${avatarHtml(p.author_photo, p.author)}
                    <div class="min-w-0 flex-1">
                        <p class="truncate font-medium text-gray-900 dark:text-white/90">${escapeHtml(p.author)}</p>
                        <p class="text-xs text-gray-500 dark:text-gray-400">${p.created_at_iso ? `<span class="time-ago" data-created-at="${p.created_at_iso}">${p.created_at}</span>` : p.created_at}<span data-post-marker-verb>${p.marker_verb ? ` · ${escapeHtml(p.marker_verb)}` : ''}</span></p>
                    </div>
                    ${markerArtHtml(p.tribute_type)}
                </div>
                ${p.title ? `<h3 class="mt-2 font-medium text-gray-900 dark:text-white/90">${escapeHtml(p.title)}</h3>` : ''}
                ${p.content ? `<div class="mt-2 text-sm text-gray-700 dark:text-gray-300 prose prose-sm max-w-none">${p.content}</div>` : ''}
                ${mediaHtml ? `<div class="mt-3 space-y-2">${mediaHtml}</div>` : ''}
            </div>
            <div class="relative z-10 flex items-center gap-4 border-t border-gray-100 dark:border-gray-800 px-4 py-2">
                <div class="relative flex items-center gap-1" data-reaction-container="${p.id}">
                    <button type="button" data-reaction-btn data-reactionable-type="post" data-reactionable-id="${p.id}" data-reaction-type="like" class="inline-flex items-center gap-1.5 text-gray-600 dark:text-gray-400 hover:text-rose-500 dark:hover:text-rose-400">
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z"/></svg>
                        <span data-post-id="${p.id}" data-reaction-count class="text-sm text-gray-600 dark:text-gray-400">0</span>
                    </button>
                </div>
                <div class="flex items-center gap-1" data-comment-container="${p.id}">
                    <button type="button" data-open-comments="${p.id}" class="inline-flex items-center gap-1.5 text-gray-600 dark:text-gray-400 hover:text-gray-800 dark:hover:text-gray-200">
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"/></svg>
                        <span data-post-id="${p.id}" data-comment-count class="text-sm tabular-nums text-gray-600 dark:text-gray-400">0</span>
                    </button>
                </div>
                <div class="relative ml-auto" data-share-container="${p.id}">
                    <button type="button" data-share-toggle data-share-url="${(p.share_id ? `${window.location.origin}/${memorialSlug}/chapter/${p.share_id}` : `${window.location.origin}/${memorialSlug}/chapter/${p.id}`)}" data-post-id="${p.id}" class="inline-flex items-center gap-1.5 text-gray-600 dark:text-gray-400 hover:text-gray-800 dark:hover:text-gray-200">
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.684 13.342C8.886 12.938 9 12.482 9 12c0-.482-.114-.938-.316-1.342m0 2.684a3 3 0 110-2.684m0 2.684l6.632 3.316m-6.632-6l6.632-3.316m0 0a3 3 0 105.367-2.684 3 3 0 00-5.367 2.684zm0 9.316a3 3 0 105.368 2.684 3 3 0 00-5.368-2.684z"/></svg>
                        Share
                    </button>
                    <div data-share-dropdown="${p.id}" class="absolute right-0 top-full z-[9999] mt-1 hidden w-52 rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 shadow-xl p-1.5">
                        ${shareDropdownHtml(p.share_id ? `${window.location.origin}/${memorialSlug}/chapter/${p.share_id}` : `${window.location.origin}/${memorialSlug}/chapter/${p.id}`)}
                    </div>
                </div>
            </div>
        `;
        feed.prepend(article);
        if (typeof Alpine !== 'undefined') Alpine.initTree(article);
        const rbtn = article.querySelector('[data-reaction-btn]');
        if (rbtn) attachReactionHandler(rbtn);
        // comment + share are handled by delegated listeners

        // A soft landing rather than a pop into place — appearing with no transition at
        // all reads as a glitch. Transform and opacity only, and cleaned up after, so
        // nothing lingers to fight the feed's own hover styles.
        article.style.opacity = '0';
        article.style.transform = 'translateY(6px)';
        article.style.transition = 'opacity 240ms cubic-bezier(0.23, 1, 0.32, 1), transform 240ms cubic-bezier(0.23, 1, 0.32, 1)';
        requestAnimationFrame(() => requestAnimationFrame(() => {
            article.style.opacity = '1';
            article.style.transform = 'translateY(0)';
            setTimeout(() => {
                article.style.removeProperty('opacity');
                article.style.removeProperty('transform');
                article.style.removeProperty('transition');
            }, 300);
        }));
    }

    // --- Reactions on posts ---
    function submitReaction(payload, guestName, guestEmail) {
        fetch(`${baseUrl}/reaction`, fetchOpts('POST', {
            ...payload,
            guest_name: guestName,
            guest_email: guestEmail,
        }))
            .then(r => r.json())
                    .then(data => {
                        adoptSession(data);
                        if (data.success) {
                            document.querySelectorAll(`[data-reaction-container="${payload.reactionable_id}"] [data-reaction-count]`).forEach(el => {
                                el.textContent = data.count;
                            });
                        }
                    });
    }

    // One handler for every heart, optimistic in the social-app sense: the count moves
    // the moment the thumb lands, then reconciles with what the server says (the server
    // toggles — a second tap takes the like back). The page does not know your history
    // at load, so the first tap assumes "add"; the response's `action` corrects the
    // button's memory either way, and any failure puts the number back exactly.
    function attachReactionHandler(btn) {
        btn.addEventListener('click', () => {
            const payload = {
                reactionable_type: btn.dataset.reactionableType,
                reactionable_id: parseInt(btn.dataset.reactionableId),
                type: btn.dataset.reactionType || 'like',
            };

            const countEls = () => document.querySelectorAll(`[data-reaction-container="${payload.reactionable_id}"] [data-reaction-count]`);
            const paint = (liked) => {
                btn.dataset.liked = liked ? '1' : '';
                btn.classList.toggle('is-liked', liked);
            };

            const doReaction = (name, email) => {
                const wasLiked = btn.dataset.liked === '1';
                const prev = parseInt(countEls()[0]?.textContent || '0', 10) || 0;

                countEls().forEach(el => { el.textContent = wasLiked ? Math.max(0, prev - 1) : prev + 1; });
                paint(!wasLiked);

                const revert = () => {
                    countEls().forEach(el => { el.textContent = prev; });
                    paint(wasLiked);
                };

                const body = { ...payload };
                if (name) body.guest_name = name;
                if (email) body.guest_email = email;
                fetch(`${baseUrl}/reaction`, fetchOpts('POST', body))
                    .then(r => r.json())
                    .then(data => {
                        adoptSession(data);
                        if (data.success) {
                            countEls().forEach(el => { el.textContent = data.count; });
                            paint(data.action === 'added');
                        } else {
                            revert();
                            if (data.requires_login) {
                                goSignIn(data.error);
                            } else if (data.error) {
                                $toast('error', data.error);
                            }
                        }
                    })
                    .catch(revert);
            };

            if (isAuthenticated) {
                doReaction();
            } else {
                showGuestModal({ type: 'reaction', payload, callback: (name, email) => doReaction(name, email) });
            }
        });
    }

    document.querySelectorAll('[data-reaction-btn]').forEach(attachReactionHandler);

    // --- Live updates ---------------------------------------------------------------
    //
    // The page keeps itself current while it sits open: one light request every ~25s
    // (paused while the tab is hidden) refreshes every heart and comment tally, and a
    // story someone else publishes waits behind a quiet "new stories" pill rather than
    // splicing itself into the feed under a reader's thumb.
    let latestKnownPostId = 0;

    (function startLiveUpdates() {
        const feed = document.getElementById('life-feed');
        if (!feed || !baseUrl) return;

        latestKnownPostId = Math.max(0, ...[...feed.querySelectorAll('article[data-post-id]')]
            .map(a => parseInt(a.dataset.postId, 10) || 0));

        const pending = [];

        const pill = document.createElement('button');
        pill.type = 'button';
        pill.className = 'live-new-pill hidden';
        pill.setAttribute('aria-live', 'polite');
        feed.parentNode.insertBefore(pill, feed);

        pill.addEventListener('click', () => {
            // Oldest first, so the newest of them ends up on top — the feed's own order.
            pending.forEach(p => prependPostArticle(p));
            bumpStoryCount(pending.length);
            pending.length = 0;
            pill.classList.remove('is-visible');
            setTimeout(() => pill.classList.add('hidden'), 200);
            applyFeedFilters();
        });

        const applyCounts = (kind, map) => {
            // Every tally, zero included — a like someone took back has to come down too.
            document.querySelectorAll(`[data-${kind}-container]`).forEach(container => {
                const id = container.dataset[`${kind}Container`];
                const el = container.querySelector(`[data-${kind}-count]`);
                if (el && id) el.textContent = (map && map[id]) || 0;
            });
        };

        const poll = () => {
            fetch(`${baseUrl}/live?after_id=${latestKnownPostId}`, {
                headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            })
                .then(r => (r.ok ? r.json() : null))
                .then(data => {
                    if (!data) return;
                    applyCounts('reaction', data.reactions);
                    applyCounts('comment', data.comments);
                    (data.new_posts || []).forEach(p => {
                        // Guard against a poll that raced our own composer submit.
                        if (!document.getElementById('chapter-' + p.id) && !pending.some(q => q.id === p.id)) {
                            pending.push(p);
                        }
                    });
                    if (data.latest_post_id) {
                        latestKnownPostId = Math.max(latestKnownPostId, data.latest_post_id);
                    }
                    if (pending.length) {
                        pill.textContent = pending.length === 1 ? '1 new story' : `${pending.length} new stories`;
                        pill.classList.remove('hidden');
                        requestAnimationFrame(() => pill.classList.add('is-visible'));
                    }
                })
                .catch(() => {});
        };

        const POLL_MS = 25000;
        let timer = null;
        const start = () => { if (!timer) timer = setInterval(poll, POLL_MS); };
        const stop = () => { if (timer) { clearInterval(timer); timer = null; } };

        // A hidden tab neither needs fresh tallies nor deserves the battery cost;
        // coming back polls once immediately so the page is current the moment
        // it is looked at again.
        document.addEventListener('visibilitychange', () => {
            if (document.visibilityState === 'visible') {
                poll();
                start();
            } else {
                stop();
            }
        });

        start();
    })();


    // --- Comment sheet -------------------------------------------------------------
    //
    // Comments used to be a strip that expanded inside the story card: the composer
    // scrolled out of reach the moment you read past it, the total was never shown, every
    // reply was permanently open, and the whole thread was in the markup of every story on
    // the page whether anyone opened it or not.
    //
    // One sheet now, filled on demand. It pages by id rather than offset, it polls for what
    // other people write while it is open, and everything you do in it — post, reply, like
    // — lands immediately and reconciles with the server afterwards.

    const sheetEl = document.getElementById('comment-sheet');

    if (sheetEl) {
        const $sheet = (sel) => sheetEl.querySelector(sel);
        const listEl = $sheet('[data-sheet-list]');
        const bodyEl = $sheet('[data-sheet-body]');
        const inputEl = $sheet('[data-sheet-input]');
        const sendEl = $sheet('[data-sheet-send]');
        const totalEl = $sheet('[data-sheet-total]');
        const totalLabelEl = $sheet('[data-sheet-total-label]');
        const spinnerEl = $sheet('[data-sheet-spinner]');
        const emptyEl = $sheet('[data-sheet-empty]');
        const errorEl = $sheet('[data-sheet-error]');
        const pillEl = $sheet('[data-sheet-new-pill]');
        const pillLabelEl = $sheet('[data-sheet-new-label]');
        const replyingEl = $sheet('[data-sheet-replying]');
        const replyingToEl = $sheet('[data-sheet-replying-to]');

        // How often the open sheet asks for what it has not seen. Not a live socket —
        // there is no broadcast stack in this app — but for a memorial, where two people
        // reading at once is a busy day, a poll on this cadence is indistinguishable from
        // one and costs nothing when nobody is looking.
        const POLL_MS = 8000;

        const state = {
            postId: null,
            newestId: 0,      // highest top-level id held; the polling cursor
            oldestId: null,   // lowest id held; the paging cursor
            hasMore: false,
            loading: false,
            total: 0,
            replyTo: null,    // { id, author }
            pending: 0,       // counter for optimistic row ids
            queued: [],       // arrived while the reader was scrolled away
            pushed: false,
            timer: null,
            release: null,    // openDialog's focus-trap teardown
        };

        // Enter sends on a keyboard; on a touch keyboard Enter has to stay a newline, or
        // writing a second sentence becomes impossible.
        const entersSends = window.matchMedia('(hover: hover) and (pointer: fine)').matches;

        function setTotal(n) {
            state.total = Math.max(0, n);
            totalEl.textContent = state.total;
            totalLabelEl.textContent = state.total === 1 ? 'comment' : 'comments';
            syncCommentCount(state.postId, state.total);
        }

        /** The tally on the story card — in the feed and in the Biography preview both. */
        function syncCommentCount(postId, total) {
            if (!postId) return;
            document.querySelectorAll(`[data-comment-container="${postId}"] [data-comment-count]`)
                .forEach(el => { el.textContent = total; });
        }

        function guestIdentity() {
            return {
                name: $sheet('[data-sheet-guest-name]')?.value?.trim() || '',
                email: $sheet('[data-sheet-guest-email]')?.value?.trim() || '',
            };
        }

        function heartHtml(c) {
            return `<button type="button" data-comment-like="${c.id}" aria-pressed="${c.reacted ? 'true' : 'false'}"
                    class="comment-like" aria-label="Like this comment">
                    <svg class="h-4 w-4" viewBox="0 0 24 24" fill="${c.reacted ? 'currentColor' : 'none'}" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z"/></svg>
                    <span class="comment-like__count tabular-nums" data-like-count>${c.reaction_count || 0}</span>
                </button>`;
        }

        function commentRowHtml(c, { isReply = false } = {}) {
            const avatar = avatarHtml(c.author_photo, c.author, 'h-8 w-8', 'bg-brand-100 dark:bg-brand-500/25 text-brand-600 dark:text-brand-400 text-xs font-semibold');
            const del = c.can_delete
                ? `<button type="button" data-comment-delete="${c.id}" class="comment-row__action">Delete</button>`
                : '';
            const reply = isReply ? '' : `<button type="button" data-comment-reply="${c.id}" class="comment-row__action">Reply</button>`;

            // Only rendered when there are replies to fold. "View 0 replies" is a control
            // that promises something and then does nothing.
            const replyCount = c.reply_count || 0;
            const shownReplies = (c.replies || []).length;
            const toggle = (!isReply && replyCount > 0)
                ? `<button type="button" data-replies-toggle="${c.id}" data-count="${replyCount}" data-loaded="${shownReplies >= replyCount ? '1' : '0'}" class="comment-replies__toggle" aria-expanded="false">
                       <span class="comment-replies__rule" aria-hidden="true"></span>
                       <span data-replies-label>View ${replyCount} ${replyCount === 1 ? 'reply' : 'replies'}</span>
                   </button>`
                : '';
            const repliesList = (!isReply && replyCount > 0)
                ? `<ol class="comment-replies hidden" data-replies-list="${c.id}">${(c.replies || []).map(r => commentRowHtml(r, { isReply: true })).join('')}</ol>`
                : '';

            return `<li class="comment-row${isReply ? ' comment-row--reply' : ''}" data-comment-id="${c.id}"${isReply ? '' : ` data-top-level="1"`}>
                <div class="comment-row__main">
                    ${avatar}
                    <div class="comment-row__content">
                        <p class="comment-row__author">${escapeHtml(c.author)}</p>
                        <p class="comment-row__text">${escapeHtml(c.content)}</p>
                        <div class="comment-row__meta">
                            <span class="time-ago" data-created-at="${c.created_at_iso || ''}">${escapeHtml(c.created_at || '')}</span>
                            ${reply}
                            ${del}
                        </div>
                    </div>
                    ${heartHtml(c)}
                </div>
                ${toggle}
                ${repliesList}
            </li>`;
        }

        function showState({ spinner = false, empty = false, error = false } = {}) {
            spinnerEl.classList.toggle('hidden', !spinner);
            emptyEl.classList.toggle('hidden', !empty);
            errorEl.classList.toggle('hidden', !error);
        }

        function fetchPage({ before = null } = {}) {
            if (state.loading) return Promise.resolve();
            state.loading = true;
            showState({ spinner: true });

            const url = new URL(`${baseUrl}/posts/${state.postId}/comments`, window.location.origin);
            if (before) url.searchParams.set('before', before);

            return fetch(url, { headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' } })
                .then(r => { if (!r.ok) throw new Error('load failed'); return r.json(); })
                .then(data => {
                    state.loading = false;
                    showState({});
                    setTotal(data.total ?? state.total);
                    state.hasMore = !!data.has_more;

                    (data.comments || []).forEach(c => {
                        state.newestId = Math.max(state.newestId, c.id);
                        state.oldestId = state.oldestId === null ? c.id : Math.min(state.oldestId, c.id);
                        listEl.insertAdjacentHTML('beforeend', commentRowHtml(c));
                    });

                    if (!listEl.children.length) showState({ empty: true });
                    updateTimeAgoElements();
                })
                .catch(() => {
                    state.loading = false;
                    showState({ error: !listEl.children.length });
                    if (listEl.children.length) $toast('error', 'Could not load more comments.');
                });
        }

        /** Everything posted since the last one we hold. */
        function poll() {
            if (!state.postId || document.hidden || state.loading) return;

            fetch(`${baseUrl}/posts/${state.postId}/comments?after=${state.newestId}`, {
                headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            })
                .then(r => (r.ok ? r.json() : null))
                .then(data => {
                    if (!data) return;
                    setTotal(data.total ?? state.total);
                    const fresh = (data.comments || []).filter(c => !listEl.querySelector(`[data-comment-id="${c.id}"]`));
                    if (!fresh.length) return;

                    fresh.forEach(c => { state.newestId = Math.max(state.newestId, c.id); });

                    // At the top of the list, new comments simply appear. Further down,
                    // inserting above the reader shifts everything they are reading; they
                    // are offered instead.
                    if (bodyEl.scrollTop <= 24) {
                        prependComments(fresh);
                    } else {
                        state.queued.push(...fresh);
                        pillLabelEl.textContent = state.queued.length === 1
                            ? '1 new comment'
                            : `${state.queued.length} new comments`;
                        pillEl.classList.remove('hidden');
                    }
                })
                .catch(() => { /* a dropped poll is not worth a toast; the next one will do */ });
        }

        function prependComments(list) {
            showState({});
            list.forEach(c => listEl.insertAdjacentHTML('afterbegin', commentRowHtml(c)));
            updateTimeAgoElements();
        }

        pillEl.addEventListener('click', () => {
            prependComments(state.queued.splice(0));
            pillEl.classList.add('hidden');
            bodyEl.scrollTo({ top: 0, behavior: 'smooth' });
        });

        function startPolling() {
            stopPolling();
            state.timer = setInterval(poll, POLL_MS);
        }

        function stopPolling() {
            if (state.timer) clearInterval(state.timer);
            state.timer = null;
        }

        // Coming back to the tab is the moment the reader most wants to be up to date, and
        // the moment a timer that has been firing into a hidden tab is most stale.
        document.addEventListener('visibilitychange', () => {
            if (!state.postId) return;
            if (document.hidden) stopPolling();
            else { poll(); startPolling(); }
        });

        function openSheet(postId, { push = true } = {}) {
            if (state.postId === String(postId)) return;
            state.postId = String(postId);
            state.newestId = 0;
            state.oldestId = null;
            state.hasMore = false;
            state.total = 0;
            state.queued = [];
            setReplyTo(null);
            listEl.innerHTML = '';
            pillEl.classList.add('hidden');
            inputEl.value = '';
            syncSendState();
            autoGrow();

            sheetEl.classList.remove('hidden');
            requestAnimationFrame(() => sheetEl.classList.add('is-open'));
            document.body.style.overflow = 'hidden';

            state.release = openDialog(sheetEl, { onClose: closeSheet });

            // An entry in history, so the phone's Back gesture closes the sheet instead of
            // leaving the memorial — the thing every native app does and every web sheet
            // that skips this gets wrong. The address is also a real link: send someone
            // #comments-42 and they land in that conversation.
            if (push) {
                state.pushed = true;
                history.pushState({ commentSheet: state.postId }, '', `#comments-${state.postId}`);
            }

            fetchPage().then(startPolling);
        }

        function closeSheet({ pop = true } = {}) {
            if (sheetEl.classList.contains('hidden')) return;
            stopPolling();
            state.postId = null;

            if (pop && state.pushed) {
                state.pushed = false;
                history.back();
            }
            sheetEl.classList.remove('is-open');
            document.body.style.overflow = '';
            const release = state.release;
            state.release = null;

            // Wait out the slide before hiding, so the sheet leaves rather than vanishes.
            const done = () => { sheetEl.classList.add('hidden'); release?.(); };
            const reduced = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
            reduced ? done() : setTimeout(done, 220);
        }

        document.addEventListener('click', (e) => {
            const opener = e.target.closest('[data-open-comments]');
            if (opener) {
                e.preventDefault();
                openSheet(opener.dataset.openComments);
                return;
            }
            if (e.target.closest('[data-close-comment-sheet]')) closeSheet();
        });

        window.addEventListener('popstate', () => {
            // Back landed us here, so the entry is already gone — closing must not pop again.
            state.pushed = false;
            if (state.postId) closeSheet({ pop: false });
        });

        // Arriving on #comments-42: open straight into that conversation, without pushing a
        // second entry over the one the address already is.
        const deepLink = /^#comments-(\d+)$/.exec(window.location.hash);
        if (deepLink) {
            requestAnimationFrame(() => openSheet(deepLink[1], { push: false }));
        }

        $sheet('[data-sheet-retry]').addEventListener('click', () => fetchPage());

        // --- Paging: the next page is fetched a screen before the reader reaches the end,
        // so the list simply continues rather than stalling at a spinner.
        bodyEl.addEventListener('scroll', () => {
            if (!state.hasMore || state.loading || !state.postId) return;
            if (bodyEl.scrollTop + bodyEl.clientHeight >= bodyEl.scrollHeight - 400) {
                fetchPage({ before: state.oldestId });
            }
        });

        // --- Composer ---
        function setReplyTo(target) {
            state.replyTo = target;
            replyingEl.classList.toggle('hidden', !target);
            if (target) {
                replyingToEl.textContent = target.author;
                inputEl.placeholder = `Reply to ${target.author}…`;
            } else {
                inputEl.placeholder = 'Add comment…';
            }
        }

        function autoGrow() {
            inputEl.style.height = 'auto';
            inputEl.style.height = Math.min(inputEl.scrollHeight, 120) + 'px';
        }

        function syncSendState() {
            sendEl.disabled = !inputEl.value.trim();
        }

        inputEl.addEventListener('input', () => { autoGrow(); syncSendState(); });
        inputEl.addEventListener('keydown', (e) => {
            if (e.key === 'Enter' && !e.shiftKey && entersSends) {
                e.preventDefault();
                sendEl.click();
            }
            if (e.key === 'Escape' && state.replyTo) {
                e.stopPropagation();
                setReplyTo(null);
            }
        });
        $sheet('[data-sheet-cancel-reply]').addEventListener('click', () => { setReplyTo(null); inputEl.focus(); });

        sendEl.addEventListener('click', () => {
            const content = inputEl.value.trim();
            if (!content || !state.postId) return;

            const guest = guestIdentity();
            if (!isAuthenticated && (!guest.name || !guest.email)) {
                $toast('warning', 'Add your name and email so people know who wrote this.');
                $sheet('[data-sheet-guest-name]')?.focus();
                return;
            }

            const parent = state.replyTo;
            const tempId = `tmp-${++state.pending}`;

            // Optimistic: the comment is on screen before the request leaves. What it costs
            // is a failure path — the row turns into a retry rather than disappearing and
            // taking the words with it.
            const optimistic = {
                id: tempId,
                content,
                author: document.querySelector('[data-user-name]')?.dataset.userName || guest.name || 'You',
                author_photo: null,
                created_at: 'now',
                created_at_iso: '',
                reaction_count: 0,
                reacted: false,
                can_delete: false,
                reply_count: 0,
                replies: [],
            };

            if (parent) {
                const holder = ensureRepliesList(parent.id);
                holder?.insertAdjacentHTML('beforeend', commentRowHtml(optimistic, { isReply: true }));
                holder?.classList.remove('hidden');
            } else {
                showState({});
                listEl.insertAdjacentHTML('afterbegin', commentRowHtml(optimistic));
            }
            const row = listEl.querySelector(`[data-comment-id="${tempId}"]`);
            row?.classList.add('is-pending');
            setTotal(state.total + 1);

            inputEl.value = '';
            autoGrow();
            syncSendState();
            setReplyTo(null);
            if (!parent) bodyEl.scrollTo({ top: 0, behavior: 'smooth' });

            const body = { content };
            if (parent) body.parent_id = parent.id;
            if (!isAuthenticated) { body.guest_name = guest.name; body.guest_email = guest.email; }

            fetch(`${baseUrl}/posts/${state.postId}/comments`, fetchOpts('POST', body))
                .then(async (r) => {
                    const data = await r.json().catch(() => ({}));
                    if (!r.ok || !data.success) {
                        const err = new Error(data.error || 'Could not post that.');
                        err.requiresLogin = !!data.requires_login;
                        throw err;
                    }
                    return data;
                })
                .then(data => {
                    const real = commentRowHtml(data.comment, { isReply: !!parent });
                    row?.insertAdjacentHTML('afterend', real);
                    row?.remove();
                    if (!parent) state.newestId = Math.max(state.newestId, data.comment.id);
                    setTotal(data.total ?? state.total);
                    if (parent) bumpReplyCount(parent.id, 1);
                    updateTimeAgoElements();
                })
                .catch((err) => {
                    setTotal(state.total - 1);
                    if (err.requiresLogin) {
                        // Retrying an identical send can only fail identically — their
                        // address has an account, so the way forward is signing in, and
                        // the return trip lands them back on this memorial.
                        row?.remove();
                        goSignIn(err.message);
                        return;
                    }
                    if (!row) { $toast('error', err.message); return; }
                    row.classList.remove('is-pending');
                    row.classList.add('is-failed');
                    row.querySelector('.comment-row__meta').innerHTML =
                        `<span class="comment-row__failed">Didn’t send</span>
                         <button type="button" data-comment-retry class="comment-row__action">Try again</button>
                         <button type="button" data-comment-discard class="comment-row__action">Discard</button>`;
                    row.dataset.retryContent = content;
                    row.dataset.retryParent = parent?.id || '';
                    row.dataset.retryAuthor = parent?.author || '';
                });
        });

        // --- Row actions ---
        function ensureRepliesList(commentId) {
            const parentRow = listEl.querySelector(`[data-comment-id="${commentId}"]`);
            if (!parentRow) return null;
            let holder = parentRow.querySelector(`[data-replies-list="${commentId}"]`);
            if (!holder) {
                parentRow.insertAdjacentHTML('beforeend', `<ol class="comment-replies" data-replies-list="${commentId}"></ol>`);
                holder = parentRow.querySelector(`[data-replies-list="${commentId}"]`);
            }
            return holder;
        }

        function bumpReplyCount(commentId, delta) {
            const toggle = listEl.querySelector(`[data-replies-toggle="${commentId}"]`);
            if (!toggle) return;
            const next = Math.max(0, parseInt(toggle.dataset.count || '0', 10) + delta);
            toggle.dataset.count = next;
            const label = toggle.querySelector('[data-replies-label]');
            const open = toggle.getAttribute('aria-expanded') === 'true';
            if (label) label.textContent = open ? 'Hide replies' : `View ${next} ${next === 1 ? 'reply' : 'replies'}`;
        }

        sheetEl.addEventListener('click', (e) => {
            const likeBtn = e.target.closest('[data-comment-like]');
            if (likeBtn) return toggleLike(likeBtn);

            const replyBtn = e.target.closest('[data-comment-reply]');
            if (replyBtn) {
                const row = replyBtn.closest('.comment-row');
                setReplyTo({ id: replyBtn.dataset.commentReply, author: row?.querySelector('.comment-row__author')?.textContent || 'them' });
                inputEl.focus();
                return;
            }

            const toggle = e.target.closest('[data-replies-toggle]');
            if (toggle) return toggleReplies(toggle);

            const retry = e.target.closest('[data-comment-retry]');
            if (retry) {
                const row = retry.closest('.comment-row');
                inputEl.value = row.dataset.retryContent || '';
                if (row.dataset.retryParent) setReplyTo({ id: row.dataset.retryParent, author: row.dataset.retryAuthor });
                row.remove();
                autoGrow();
                syncSendState();
                sendEl.click();
                return;
            }

            const discard = e.target.closest('[data-comment-discard]');
            if (discard) { discard.closest('.comment-row')?.remove(); return; }

            const delBtn = e.target.closest('[data-comment-delete]');
            if (delBtn) return deleteComment(delBtn);
        });

        function toggleLike(btn) {
            const id = btn.dataset.commentLike;
            if (String(id).startsWith('tmp-')) return;

            const countEl = btn.querySelector('[data-like-count]');
            const wasOn = btn.getAttribute('aria-pressed') === 'true';
            const paint = (on, n) => {
                btn.setAttribute('aria-pressed', on ? 'true' : 'false');
                btn.querySelector('svg')?.setAttribute('fill', on ? 'currentColor' : 'none');
                countEl.textContent = Math.max(0, n);
                btn.classList.toggle('is-on', on);
            };
            const before = parseInt(countEl.textContent || '0', 10);

            // Flip first. A heart that waits for the network to agree feels broken, and the
            // server is the one that settles the number a moment later either way.
            paint(!wasOn, wasOn ? before - 1 : before + 1);
            if (!wasOn) btn.classList.add('is-popping');
            setTimeout(() => btn.classList.remove('is-popping'), 320);

            const send = (name, email) => {
                const body = { reactionable_type: 'comment', reactionable_id: Number(id), type: 'like' };
                if (name) body.guest_name = name;
                if (email) body.guest_email = email;
                fetch(`${baseUrl}/reaction`, fetchOpts('POST', body))
                    .then(r => r.json())
                    .then(data => {
                        adoptSession(data);
                        if (data.success) { paint(data.action === 'added', data.count); return; }
                        paint(wasOn, before);
                        if (data.requires_login) goSignIn(data.error);
                        else if (data.error) $toast('error', data.error);
                    })
                    .catch(() => { paint(wasOn, before); $toast('error', 'That didn’t save.'); });
            };

            if (isAuthenticated) return send();

            // A guest who has already typed their details into the composer should not be
            // asked for them a second time to press a heart.
            const guest = guestIdentity();
            if (guest.name && guest.email) return send(guest.name, guest.email);

            paint(wasOn, before);
            showGuestModal({
                type: 'reaction',
                payload: { reactionable_type: 'comment', reactionable_id: Number(id), type: 'like' },
                callback: (name, email) => { paint(!wasOn, wasOn ? before - 1 : before + 1); send(name, email); },
            });
        }

        function toggleReplies(toggle) {
            const id = toggle.dataset.repliesToggle;
            const holder = listEl.querySelector(`[data-replies-list="${id}"]`);
            if (!holder) return;

            const open = toggle.getAttribute('aria-expanded') === 'true';
            const label = toggle.querySelector('[data-replies-label]');
            const count = parseInt(toggle.dataset.count || '0', 10);

            if (open) {
                holder.classList.add('hidden');
                toggle.setAttribute('aria-expanded', 'false');
                label.textContent = `View ${count} ${count === 1 ? 'reply' : 'replies'}`;
                return;
            }

            toggle.setAttribute('aria-expanded', 'true');
            holder.classList.remove('hidden');
            label.textContent = 'Hide replies';

            // The list ships with the newest three; the rest are fetched the first time
            // anyone actually asks to see them.
            if (toggle.dataset.loaded === '1') return;
            label.textContent = 'Loading…';
            fetch(`${baseUrl}/comments/${id}/replies`, { headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' } })
                .then(r => r.json())
                .then(data => {
                    holder.innerHTML = (data.replies || []).map(r => commentRowHtml(r, { isReply: true })).join('');
                    toggle.dataset.loaded = '1';
                    label.textContent = 'Hide replies';
                    updateTimeAgoElements();
                })
                .catch(() => { label.textContent = 'Hide replies'; $toast('error', 'Could not load the replies.'); });
        }

        async function deleteComment(btn) {
            const id = btn.dataset.commentDelete;
            if (!await $confirm('This cannot be undone.', { title: 'Delete this comment?', confirmText: 'Delete comment' })) return;
            btn.disabled = true;

            fetch(`${baseUrl}/comments/${id}`, fetchOpts('DELETE'))
                .then(r => r.json())
                .then(data => {
                    if (!data.success) { btn.disabled = false; $toast('error', data.error || 'Could not delete that.'); return; }
                    const row = listEl.querySelector(`[data-comment-id="${id}"]`);
                    const parentRow = row?.parentElement?.closest('.comment-row');
                    row?.remove();
                    if (parentRow) bumpReplyCount(parentRow.dataset.commentId, -1);
                    setTotal(state.total - (data.deleted_count || 1));
                    if (!listEl.children.length) showState({ empty: true });
                })
                .catch(() => { btn.disabled = false; $toast('error', 'Something went wrong.'); });
        }

        // --- Drag the sheet down to dismiss (touch only) ---
        const panelEl = $sheet('.comment-sheet__panel');
        const grabEl = $sheet('[data-comment-sheet-grab]');
        let dragFrom = null;

        grabEl.addEventListener('pointerdown', (e) => {
            dragFrom = e.clientY;
            grabEl.setPointerCapture(e.pointerId);
            panelEl.style.transition = 'none';
        });
        grabEl.addEventListener('pointermove', (e) => {
            if (dragFrom === null) return;
            // Downward only, and with resistance past the halfway point so it slows to a
            // stop the way a real thing would rather than hitting a wall.
            const raw = Math.max(0, e.clientY - dragFrom);
            const dy = raw > 160 ? 160 + (raw - 160) * 0.4 : raw;
            panelEl.style.transform = `translateY(${dy}px)`;
        });
        const endDrag = (e) => {
            if (dragFrom === null) return;
            const dy = Math.max(0, e.clientY - dragFrom);
            dragFrom = null;
            panelEl.style.transition = '';
            panelEl.style.transform = '';
            if (dy > 120) closeSheet();
        };
        grabEl.addEventListener('pointerup', endDrag);
        grabEl.addEventListener('pointercancel', endDrag);
    }

    // --- Share toggle ---
    document.addEventListener('click', (e) => {
        const shareToggle = e.target.closest('[data-share-toggle]');
        if (shareToggle) {
            e.preventDefault();
            e.stopPropagation();
            const postId = shareToggle.dataset.postId;
            const shareUrl = shareToggle.dataset.shareUrl;
            let dropdown;
            if (postId) {
                dropdown = document.querySelector(`[data-share-dropdown="${postId}"]`);
            } else if (shareUrl) {
                dropdown = shareToggle.nextElementSibling;
            }
            document.querySelectorAll('[data-share-dropdown]').forEach(d => { if (d !== dropdown) d.classList.add('hidden'); });
            dropdown?.classList.toggle('hidden');
            return;
        }
    });

    // --- Click outside to close dropdowns (share only, comments are inline) ---
    document.addEventListener('click', (e) => {
        if (e.target.closest('[data-share-container], #invite-share-btn, #invite-share-dropdown')) return;
        document.querySelectorAll('[data-share-dropdown]').forEach(d => d.classList.add('hidden'));
        document.getElementById('invite-share-dropdown')?.classList.add('hidden');
    });



    function escapeHtml(s) {
        if (!s) return '';
        const div = document.createElement('div');
        div.textContent = s;
        return div.innerHTML;
    }

    function buildVideoPlayerHtml(src, caption) {
        const cap = caption ? `<div x-show="!elementFs" class="bg-gray-50 dark:bg-white/[0.03] px-3 py-2"><p class="text-xs text-gray-500 dark:text-gray-400">${escapeHtml(caption)}</p></div>` : '';
        return `<div x-data="{
            playing:false,muted:false,volume:1,currentTime:0,duration:0,buffered:0,loading:true,elementFs:false,iosFs:false,portraitFullscreenHint:false,showControls:true,controlTimeout:null,dragging:false,showVolume:false,
            get progress(){return this.duration?(this.currentTime/this.duration)*100:0},
            get bufferProgress(){return this.duration?(this.buffered/this.duration)*100:0},
            formatTime(s){if(isNaN(s))return '0:00';const m=Math.floor(s/60);const sec=Math.floor(s%60);return m+':'+(sec<10?'0':'')+sec},
            syncFullscreenState(){const el=this.$refs.container;const a=document.fullscreenElement||document.webkitFullscreenElement;this.elementFs=!!el&&a===el;this.updatePortraitHint()},
            updatePortraitHint(){this.portraitFullscreenHint=this.elementFs&&typeof window.matchMedia==='function'&&window.matchMedia('(orientation: portrait)').matches&&Math.min(window.screen?.width||0,window.innerWidth)<1024},
            init(){const v=this.$refs.video;v.addEventListener('loadedmetadata',()=>{this.duration=v.duration;this.loading=false});v.addEventListener('timeupdate',()=>{if(!this.dragging)this.currentTime=v.currentTime});v.addEventListener('progress',()=>{if(v.buffered.length>0)this.buffered=v.buffered.end(v.buffered.length-1)});v.addEventListener('ended',()=>{this.playing=false});v.addEventListener('waiting',()=>{this.loading=true});v.addEventListener('canplay',()=>{this.loading=false});v.addEventListener('webkitbeginfullscreen',()=>{this.iosFs=true;this.updatePortraitHint()});v.addEventListener('webkitendfullscreen',()=>{this.iosFs=false;this.portraitFullscreenHint=false})},
            toggle(){const v=this.$refs.video;if(v.paused){v.play();this.playing=true}else{v.pause();this.playing=false}},
            seek(e){const r=this.$refs.progressBar.getBoundingClientRect();const p=Math.max(0,Math.min(1,(e.clientX-r.left)/r.width));this.$refs.video.currentTime=p*this.duration;this.currentTime=this.$refs.video.currentTime},
            setVolume(e){const r=this.$refs.volumeBar.getBoundingClientRect();const p=Math.max(0,Math.min(1,(e.clientX-r.left)/r.width));this.volume=p;this.$refs.video.volume=p;this.muted=p===0},
            toggleMute(){this.muted=!this.muted;this.$refs.video.muted=this.muted},
            toggleFullscreen(){const v=this.$refs.video;const el=this.$refs.container;const docFs=document.fullscreenElement||document.webkitFullscreenElement;if(docFs===el){document.exitFullscreen?.()||document.webkitExitFullscreen?.();this.elementFs=false;this.portraitFullscreenHint=false;return}if(this.iosFs&&typeof v.webkitExitFullscreen==='function'){try{v.webkitExitFullscreen();return}catch(e){}}if(docFs)return;const req=el.requestFullscreen||el.webkitRequestFullscreen||el.mozRequestFullScreen;if(req){Promise.resolve(req.call(el)).then(()=>this.syncFullscreenState()).catch(()=>{if(typeof v.webkitEnterFullscreen==='function'){try{v.webkitEnterFullscreen()}catch(e2){}}});return}if(typeof v.webkitEnterFullscreen==='function'){try{v.webkitEnterFullscreen()}catch(e){}}},
            scheduleHide(){clearTimeout(this.controlTimeout);this.showControls=true;if(this.playing){this.controlTimeout=setTimeout(()=>{this.showControls=false},2500)}}
        }" x-ref="container" @mousemove="scheduleHide()" @mouseleave="if(playing)showControls=false" @fullscreenchange.window="syncFullscreenState()" @webkitfullscreenchange.window="syncFullscreenState()" @orientationchange.window="updatePortraitHint()" class="memorial-video-player group relative overflow-hidden rounded-xl bg-gray-900 shadow-lg" :class="elementFs ? 'flex h-full min-h-[100dvh] w-full max-w-none flex-col items-center justify-center !rounded-none bg-black' : ''">
            <div x-show="portraitFullscreenHint" x-cloak x-transition class="pointer-events-none absolute left-1/2 top-[max(0.5rem,env(safe-area-inset-top))] z-20 w-[min(100%,20rem)] -translate-x-1/2 rounded-lg bg-black/75 px-3 py-2 text-center text-xs font-medium text-white shadow-lg backdrop-blur-sm">Rotate your phone for a wider full-screen view</div>
            <video x-ref="video" preload="metadata" playsinline webkit-playsinline @click="toggle()" @dblclick="toggleFullscreen()" class="cursor-pointer bg-black object-contain" :class="elementFs ? 'max-h-[calc(100dvh-6rem)] max-w-full w-full shrink-0' : 'aspect-video w-full'"><source src="${src}" type="video/mp4"></video>
            <div x-show="loading" x-cloak class="absolute inset-0 flex items-center justify-center bg-black/30 pointer-events-none"><div class="h-10 w-10 animate-spin rounded-full border-3 border-white/30 border-t-brand-400"></div></div>
            <div x-show="!playing&&showControls" x-cloak @click="toggle()" class="absolute inset-0 flex cursor-pointer items-center justify-center"><div class="flex h-16 w-16 items-center justify-center rounded-full bg-brand-500/90 text-white shadow-xl backdrop-blur-sm transition hover:bg-brand-600 hover:scale-110"><svg class="ml-1 h-7 w-7" fill="currentColor" viewBox="0 0 24 24"><path d="M8 5v14l11-7z"/></svg></div></div>
            <div x-show="showControls||!playing" class="absolute inset-x-0 bottom-0 bg-gradient-to-t from-black/80 via-black/40 to-transparent px-3 pb-3 pt-10">
                <div x-ref="progressBar" @mousedown.prevent="dragging=true;seek($event)" @mousemove="if(dragging)seek($event)" @mouseup="dragging=false" @mouseleave="dragging=false" class="group/progress mb-2.5 flex h-1.5 cursor-pointer items-center rounded-full bg-white/20 transition-all hover:h-2.5">
                    <div class="pointer-events-none absolute left-0 h-full rounded-full bg-white/10" :style="'width:'+bufferProgress+'%'"></div>
                    <div class="pointer-events-none relative h-full rounded-full bg-brand-400 transition-all" :style="'width:'+progress+'%'"><div class="absolute -right-1.5 -top-0.5 h-3.5 w-3.5 rounded-full border-2 border-white bg-brand-500 opacity-0 shadow transition group-hover/progress:opacity-100"></div></div>
                </div>
                <div class="flex items-center gap-3 text-white">
                    <button type="button" @click="toggle()" class="shrink-0 transition hover:text-brand-300"><template x-if="!playing"><svg class="h-5 w-5" fill="currentColor" viewBox="0 0 24 24"><path d="M8 5v14l11-7z"/></svg></template><template x-if="playing"><svg class="h-5 w-5" fill="currentColor" viewBox="0 0 24 24"><path d="M6 19h4V5H6v14zm8-14v14h4V5h-4z"/></svg></template></button>
                    <span class="min-w-[80px] text-xs font-medium tabular-nums text-white/80" x-text="formatTime(currentTime)+' / '+formatTime(duration)"></span>
                    <div class="flex-1"></div>
                    <div class="relative flex items-center" @mouseenter="showVolume=true" @mouseleave="showVolume=false">
                        <button type="button" @click="toggleMute()" class="shrink-0 transition hover:text-brand-300"><template x-if="muted||volume===0"><svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5.586 15H4a1 1 0 01-1-1v-4a1 1 0 011-1h1.586l4.707-4.707C10.923 3.663 12 4.109 12 5v14c0 .891-1.077 1.337-1.707.707L5.586 15z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2"/></svg></template><template x-if="!muted&&volume>0"><svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.536 8.464a5 5 0 010 7.072M18.364 5.636a9 9 0 010 12.728M5.586 15H4a1 1 0 01-1-1v-4a1 1 0 011-1h1.586l4.707-4.707C10.923 3.663 12 4.109 12 5v14c0 .891-1.077 1.337-1.707.707L5.586 15z"/></svg></template></button>
                        <div x-show="showVolume" x-cloak x-transition class="ml-2 flex w-20 items-center"><div x-ref="volumeBar" @click="setVolume($event)" class="h-1 w-full cursor-pointer rounded-full bg-white/20"><div class="h-full rounded-full bg-brand-400" :style="'width:'+(muted?0:volume*100)+'%'"></div></div></div>
                    </div>
                    <button type="button" @click="toggleFullscreen()" class="shrink-0 transition hover:text-brand-300"><template x-if="!(elementFs||iosFs)"><svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 8V4m0 0h4M4 4l5 5m11-1V4m0 0h-4m4 0l-5 5M4 16v4m0 0h4m-4 0l5-5m11 5l-5-5m5 5v-4m0 4h-4"/></svg></template><template x-if="elementFs||iosFs"><svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 9V4H4m0 0l5 5M9 15v5H4m0 0l5-5m6-6V4h5m0 0l-5 5m5 6v5h-5m0 0l5-5"/></svg></template></button>
                </div>
            </div>
            ${cap}
        </div>`;
    }

    function buildAudioPlayerHtml(src, caption, filename) {
        const name = escapeHtml(caption || filename || src.split('/').pop());
        const ext = (name.split('.').pop() || 'MP3').toUpperCase();
        return `<div x-data="{
            playing:false,currentTime:0,duration:0,volume:1,muted:false,loading:true,dragging:false,showVolume:false,
            get progress(){return this.duration?(this.currentTime/this.duration)*100:0},
            formatTime(s){if(isNaN(s)||s===0)return '0:00';const m=Math.floor(s/60);const sec=Math.floor(s%60);return m+':'+(sec<10?'0':'')+sec},
            init(){const a=this.$refs.audio;a.addEventListener('loadedmetadata',()=>{this.duration=a.duration;this.loading=false});a.addEventListener('timeupdate',()=>{if(!this.dragging)this.currentTime=a.currentTime});a.addEventListener('ended',()=>{this.playing=false;this.currentTime=0});a.addEventListener('canplay',()=>{this.loading=false})},
            toggle(){const a=this.$refs.audio;if(a.paused){a.play();this.playing=true}else{a.pause();this.playing=false}},
            seek(e){const r=this.$refs.progressBar.getBoundingClientRect();const p=Math.max(0,Math.min(1,(e.clientX-r.left)/r.width));this.$refs.audio.currentTime=p*this.duration;this.currentTime=this.$refs.audio.currentTime},
            skip(sec){const a=this.$refs.audio;a.currentTime=Math.max(0,Math.min(a.duration,a.currentTime+sec))},
            setVolume(e){const r=this.$refs.volumeBar.getBoundingClientRect();const p=Math.max(0,Math.min(1,(e.clientX-r.left)/r.width));this.volume=p;this.$refs.audio.volume=p;this.muted=p===0},
            toggleMute(){this.muted=!this.muted;this.$refs.audio.muted=this.muted}
        }" class="memorial-audio-player overflow-hidden rounded-xl border border-gray-200 dark:border-gray-700/50 bg-gradient-to-r from-brand-50 via-white to-brand-50/50 dark:from-brand-950/50 dark:via-gray-900 dark:to-brand-950/30 shadow-sm">
            <audio x-ref="audio" preload="metadata"><source src="${src}"></audio>
            <div class="flex items-center gap-3 p-3">
                <div class="relative flex h-12 w-12 shrink-0 items-center justify-center rounded-lg bg-brand-500 shadow-md shadow-brand-500/30">
                    <div class="flex items-end gap-[3px] h-5">
                        <span class="w-[3px] rounded-full bg-white/90" :class="playing?'audio-eq-bar audio-eq-bar-1':'h-1'"></span>
                        <span class="w-[3px] rounded-full bg-white/90" :class="playing?'audio-eq-bar audio-eq-bar-2':'h-2'"></span>
                        <span class="w-[3px] rounded-full bg-white/90" :class="playing?'audio-eq-bar audio-eq-bar-3':'h-3'"></span>
                        <span class="w-[3px] rounded-full bg-white/90" :class="playing?'audio-eq-bar audio-eq-bar-4':'h-1.5'"></span>
                        <span class="w-[3px] rounded-full bg-white/90" :class="playing?'audio-eq-bar audio-eq-bar-5':'h-2.5'"></span>
                    </div>
                    <div x-show="loading" x-cloak class="absolute inset-0 flex items-center justify-center rounded-lg bg-brand-600/50"><div class="h-5 w-5 animate-spin rounded-full border-2 border-white/30 border-t-white"></div></div>
                </div>
                <div class="min-w-0 flex-1">
                    <div class="flex items-center gap-2">
                        <p class="truncate text-sm font-medium text-gray-900 dark:text-white/90">${name}</p>
                        <span class="shrink-0 rounded bg-brand-100 dark:bg-brand-500/20 px-1.5 py-0.5 text-[10px] font-semibold text-brand-600 dark:text-brand-400">${ext}</span>
                    </div>
                    <div class="mt-1.5 flex items-center gap-2.5">
                        <span class="w-8 text-right text-[11px] tabular-nums text-gray-500 dark:text-gray-400" x-text="formatTime(currentTime)"></span>
                        <div x-ref="progressBar" @mousedown.prevent="dragging=true;seek($event)" @mousemove="if(dragging)seek($event)" @mouseup="dragging=false" @mouseleave="dragging=false" class="group/bar relative h-1.5 flex-1 cursor-pointer rounded-full bg-gray-200 dark:bg-white/10 transition-all hover:h-2">
                            <div class="absolute left-0 h-full rounded-full bg-brand-500 transition-all" :style="'width:'+progress+'%'"><div class="absolute -right-1 -top-[3px] h-3 w-3 rounded-full border-2 border-white dark:border-gray-900 bg-brand-500 shadow opacity-0 transition group-hover/bar:opacity-100"></div></div>
                        </div>
                        <span class="w-8 text-[11px] tabular-nums text-gray-500 dark:text-gray-400" x-text="formatTime(duration)"></span>
                    </div>
                </div>
                <div class="flex shrink-0 items-center gap-1">
                    <button type="button" @click="skip(-10)" class="rounded-full p-1.5 text-gray-500 dark:text-gray-400 transition hover:bg-brand-100 dark:hover:bg-brand-500/20 hover:text-brand-600 dark:hover:text-brand-400" title="Back 10s"><svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12.066 11.2a1 1 0 000 1.6l5.334 4A1 1 0 0019 16V8a1 1 0 00-1.6-.8l-5.333 4zM4.066 11.2a1 1 0 000 1.6l5.334 4A1 1 0 0011 16V8a1 1 0 00-1.6-.8l-5.334 4z"/></svg></button>
                    <button type="button" @click="toggle()" class="flex h-9 w-9 items-center justify-center rounded-full bg-brand-500 text-white shadow-md shadow-brand-500/30 transition hover:bg-brand-600 active:scale-95"><template x-if="!playing"><svg class="ml-0.5 h-4 w-4" fill="currentColor" viewBox="0 0 24 24"><path d="M8 5v14l11-7z"/></svg></template><template x-if="playing"><svg class="h-4 w-4" fill="currentColor" viewBox="0 0 24 24"><path d="M6 19h4V5H6v14zm8-14v14h4V5h-4z"/></svg></template></button>
                    <button type="button" @click="skip(10)" class="rounded-full p-1.5 text-gray-500 dark:text-gray-400 transition hover:bg-brand-100 dark:hover:bg-brand-500/20 hover:text-brand-600 dark:hover:text-brand-400" title="Forward 10s"><svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11.933 12.8a1 1 0 000-1.6L6.6 7.2A1 1 0 005 8v8a1 1 0 001.6.8l5.333-4zM19.933 12.8a1 1 0 000-1.6l-5.333-4A1 1 0 0013 8v8a1 1 0 001.6.8l5.333-4z"/></svg></button>
                    <div class="flex items-center" @mouseenter="showVolume=true" @mouseleave="showVolume=false">
                        <button type="button" @click="toggleMute()" class="rounded-full p-1.5 text-gray-500 dark:text-gray-400 transition hover:bg-brand-100 dark:hover:bg-brand-500/20 hover:text-brand-600 dark:hover:text-brand-400">
                            <template x-if="muted||volume===0"><svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5.586 15H4a1 1 0 01-1-1v-4a1 1 0 011-1h1.586l4.707-4.707C10.923 3.663 12 4.109 12 5v14c0 .891-1.077 1.337-1.707.707L5.586 15z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2"/></svg></template>
                            <template x-if="!muted&&volume>0"><svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.536 8.464a5 5 0 010 7.072M5.586 15H4a1 1 0 01-1-1v-4a1 1 0 011-1h1.586l4.707-4.707C10.923 3.663 12 4.109 12 5v14c0 .891-1.077 1.337-1.707.707L5.586 15z"/></svg></template>
                        </button>
                        <div x-show="showVolume" x-cloak x-transition class="ml-1 flex w-16 items-center"><div x-ref="volumeBar" @click="setVolume($event)" class="h-1.5 w-full cursor-pointer rounded-full bg-gray-200 dark:bg-white/10"><div class="h-full rounded-full bg-brand-500 transition-all" :style="'width:'+(muted?0:volume*100)+'%'"></div></div></div>
                    </div>
                </div>
            </div>
        </div>`;
    }

    function formatTimeAgo(date) {
        const seconds = Math.floor((Date.now() - date) / 1000);
        if (seconds < 60) return 'just now';
        if (seconds < 3600) return Math.floor(seconds / 60) + ' minute' + (seconds >= 120 ? 's' : '') + ' ago';
        if (seconds < 86400) return Math.floor(seconds / 3600) + ' hour' + (seconds >= 7200 ? 's' : '') + ' ago';
        if (seconds < 2592000) return Math.floor(seconds / 86400) + ' day' + (seconds >= 172800 ? 's' : '') + ' ago';
        if (seconds < 31536000) return Math.floor(seconds / 2592000) + ' month' + (seconds >= 5184000 ? 's' : '') + ' ago';
        return Math.floor(seconds / 31536000) + ' year' + (seconds >= 63072000 ? 's' : '') + ' ago';
    }

    function updateTimeAgoElements() {
        document.querySelectorAll('.time-ago[data-created-at]').forEach(el => {
            const iso = el.dataset.createdAt;
            if (iso) {
                const date = new Date(iso);
                if (!isNaN(date)) el.textContent = formatTimeAgo(date);
            }
        });
    }
    updateTimeAgoElements();
    setInterval(updateTimeAgoElements, 60000);

    // --- Scroll to a shared story on deep link load ---
    if (scrollToChapterId) {
        switchToTab('stories');
        setTimeout(() => {
            document.getElementById('chapter-' + scrollToChapterId)?.scrollIntoView({ behavior: 'smooth', block: 'center' });
        }, 200);
    }

    // --- Social share (with tracking) ---
    function getShareUrl(btn) {
        if (btn.dataset.shareUrl) return btn.dataset.shareUrl;
        const base = window.location.origin + window.location.pathname;
        return btn.dataset.postId ? `${base}#post-${btn.dataset.postId}` : base;
    }
    function getShareType(btn) {
        const t = btn.dataset.share;
        return ['whatsapp', 'facebook', 'linkedin', 'copy', 'invite'].includes(t) ? t : 'invite';
    }
    function applyStats(stats) {
        const map = {
            'data-stats-views-today': stats.views_today,
            'data-stats-views-week': stats.views_last_week,
            'data-stats-views-all': stats.views_all_time,
            'data-stats-shares-today': stats.shares_today,
            'data-stats-shares-week': stats.shares_last_week,
            'data-stats-shares-all': stats.shares_all_time,
        };
        for (const [attr, val] of Object.entries(map)) {
            const el = document.querySelector(`[${attr}]`);
            if (el) el.textContent = val;
        }
    }

    function trackShare(shareType) {
        fetch(`${baseUrl}/track-share`, fetchOpts('POST', { share_type: shareType }))
            .then(r => r.json())
            .then(data => { if (data.stats) applyStats(data.stats); })
            .catch(() => {});
    }
    document.querySelectorAll('[data-share]').forEach(btn => {
        btn.addEventListener('click', (e) => {
            e.preventDefault();
            const url = getShareUrl(btn);
            const encoded = encodeURIComponent(url);
            const title = encodeURIComponent(document.title || 'Memorial');
            const shareType = getShareType(btn);
            trackShare(shareType);
            switch (shareType) {
                case 'whatsapp':
                    window.open(`https://wa.me/?text=${title}%20${encoded}`, '_blank', 'noopener');
                    break;
                case 'facebook':
                    window.open(`https://www.facebook.com/sharer/sharer.php?u=${encoded}`, '_blank', 'noopener');
                    break;
                case 'linkedin':
                    window.open(`https://www.linkedin.com/sharing/share-offsite/?url=${encoded}`, '_blank', 'noopener');
                    break;
                case 'copy':
                    navigator.clipboard.writeText(url).then(() => {
                        const orig = btn.textContent;
                        btn.textContent = 'Copied';
                        setTimeout(() => { btn.textContent = orig; }, 1500);
                    });
                    break;
            }
            document.querySelectorAll('[data-share-dropdown]').forEach(d => d.classList.add('hidden'));
        });
    });

    // The invite button is the only share toggle that reports its state, and three separate
    // handlers can close its dropdown — picking a channel, opening another dropdown, or
    // clicking away. Rather than teaching each of them about this button, its state is
    // reconciled once after every click. Registered last, so every other click handler has
    // already run by the time this reads the DOM.
    document.addEventListener('click', () => {
        const dropdown = document.getElementById('invite-share-dropdown');
        document.getElementById('invite-share-btn')
            ?.setAttribute('aria-expanded', dropdown && !dropdown.classList.contains('hidden') ? 'true' : 'false');
    });

    // Refresh stats shortly after load so the view the visitor just caused is reflected
    setTimeout(() => {
        fetch(`${baseUrl}/stats`, { headers: { 'Accept': 'application/json' } })
            .then(r => r.json())
            .then(applyStats)
            .catch(() => {});
    }, 1500);
});
