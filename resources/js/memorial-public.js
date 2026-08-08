/**
 * Memorial public page - AJAX interactions, no page reload
 */
document.addEventListener('DOMContentLoaded', () => {
    const memorialSlug = document.querySelector('[data-memorial-slug]')?.dataset.memorialSlug;
    const canEdit = document.querySelector('[data-can-edit]')?.dataset.canEdit === '1';
    const canUpload = document.querySelector('[data-can-upload]')?.dataset.canUpload === '1';
    const isAuthenticated = document.querySelector('[data-is-authenticated]')?.dataset.isAuthenticated === '1';

    if (!memorialSlug) return;

    const container = document.querySelector('[data-memorial-slug]');
    const tributeUrl = container?.dataset.tributeUrl;
    const scrollToTributeId = container?.dataset.scrollTribute || '';
    const scrollToChapterId = container?.dataset.scrollChapter || '';
    const baseUrl = tributeUrl ? tributeUrl.replace(/\/tribute$/, '') : `/m/${memorialSlug}`;
    const csrf = document.querySelector('meta[name="csrf-token"]')?.content;

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
     * A tribute can be on the page twice — once in the Biography tab's preview strip and
     * once in the Tributes tab — so its comment tally has two homes. Update every copy from
     * a single reading, or the tab the visitor is actually looking at goes stale.
     */
    function bumpTributeCommentCount(tributeId, delta) {
        const countEls = document.querySelectorAll(`[data-tribute-comment-container="${tributeId}"] [data-tribute-comment-count]`);
        if (!countEls.length) return;
        const current = parseInt((countEls[0].textContent || '0').replace(/\D/g, '') || 0);
        const next = Math.max(0, current + delta);
        countEls.forEach(el => { el.textContent = next; });
    }

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
                    reject(new Error(msg));
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
    // The Life tab was folded into Tributes & Stories, which holds its two halves in
    // Alpine rather than as separate panels. A dozen call sites still ask for 'life' by
    // name and every one of them means "show me the stories", so the alias is resolved
    // here once instead of being chased through all of them.
    const TAB_ALIASES = {
        life: { panel: 'tributes', pane: 'stories' },
        stories: { panel: 'tributes', pane: 'stories' },
        tributes: { panel: 'tributes', pane: 'tributes' },
    };

    /** Select one of the two panes inside the Tributes & Stories panel. */
    function showTributePane(pane) {
        const el = document.getElementById('tab-tributes');
        if (!el || typeof Alpine === 'undefined') return;
        try {
            const data = Alpine.$data(el);
            if (data && Object.prototype.hasOwnProperty.call(data, 'pane')) data.pane = pane;
        } catch (_) { /* Alpine not started; the panel opens on its default pane */ }
    }

    function switchToTab(panelId) {
        const alias = TAB_ALIASES[panelId];
        const target = alias ? alias.panel : panelId;
        const btn = document.querySelector(`.memorial-tab-btn[data-tab-panel="${target}"]`);
        if (btn) btn.click();
        // Two frames, matching how the gallery reaches its own Alpine state: the panel has
        // to be un-hidden and Alpine has to have walked it before the pane can be set.
        if (alias) {
            requestAnimationFrame(() => requestAnimationFrame(() => showTributePane(alias.pane)));
        }
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

            // Tributes & Stories opens with a composer on screen in either pane, so this is
            // the last moment Quill can be fetched without the visitor waiting on it.
            if (panelId === 'tributes') initComposerEditors();
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
            const idx = parseInt(previewLb.dataset.galleryPreviewLightbox ?? '0', 10);
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
                        d.openLightbox(idx);
                    }
                } catch (_) { /* Alpine not ready */ }
            };
            requestAnimationFrame(() => {
                requestAnimationFrame(openWhenReady);
            });
        }
    });

    // --- Chapter filter ---
    document.querySelectorAll('.chapter-filter').forEach(btn => {
        btn.addEventListener('click', () => {
            const chapterId = btn.dataset.chapter || '';
            document.querySelectorAll('.chapter-filter').forEach(b => b.classList.remove('bg-brand-50', 'dark:bg-brand-500/20', 'text-brand-600', 'dark:text-brand-400'));
            document.querySelectorAll('.chapter-filter').forEach(b => b.classList.add('text-gray-600', 'dark:text-gray-400'));
            btn.classList.add('bg-brand-50', 'dark:bg-brand-500/20', 'text-brand-600', 'dark:text-brand-400');
            btn.classList.remove('text-gray-600', 'dark:text-gray-400');

            document.querySelectorAll('article.life-feed-post').forEach(article => {
                const artChapter = article.dataset.chapterId || '';
                article.style.display = (chapterId === '' || artChapter === chapterId) ? '' : 'none';
            });
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

            fetch(`${baseUrl}/posts/${postId}`, fetchOpts('PATCH', {
                title: newTitle,
                content: isEmpty ? null : newContent,
            }))
                .then(r => r.json())
                .then(data => {
                    if (data.success && data.post) {
                        syncLifePostDisplay(displayEl, data.post);
                        displayEl.classList.remove('hidden');
                        editEl.classList.add('hidden');
                        document.querySelectorAll(`article.life-feed-post[data-post-id="${postId}"]`).forEach((art) => {
                            if (art === article) return;
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
            if (!await $confirm('This cannot be undone.', { title: 'Delete this post?', confirmText: 'Delete post' })) return;
            deleteBtn.disabled = true;
            fetch(`${baseUrl}/posts/${postId}`, fetchOpts('DELETE'))
                .then(r => r.json())
                .then(data => {
                    if (data.success) {
                        document.querySelectorAll(`article.life-feed-post[data-post-id="${postId}"]`).forEach(a => a.remove());
                    } else if (data.error) {
                        $toast('error', data.error);
                    }
                })
                .catch(() => { $toast('error', 'Something went wrong.'); deleteBtn.disabled = false; });
        });
    }

    // --- Profile photo upload ---
    if (canEdit) {
        document.getElementById('profile-photo-input')?.addEventListener('change', (e) => {
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
                        if (heroPortraitImage) heroPortraitImage.src = data.url;
                        heroPortrait?.classList.remove('hidden');
                    } else if (data.error) {
                        $toast('error', data.error);
                    }
                })
                .catch(err => { $toast('error', err.message || 'Photo upload failed.'); });
            e.target.value = '';
        });
    }

    // --- Cover banner upload / removal ---
    if (canEdit) {
        const coverRemoveBtn = document.getElementById('cover-photo-remove');
        const coverLabel = document.getElementById('cover-photo-label');

        // The cover dresses two places: the card banner and the hero backdrop. Both are
        // updated together so an upload never leaves one of them showing the fallback.
        const coverSurfaces = [
            { image: document.getElementById('memorial-cover-image'), fallback: document.getElementById('memorial-cover-fallback') },
            { image: document.getElementById('memorial-hero-image'), fallback: document.getElementById('memorial-hero-fallback') },
        ];

        const showCover = (url) => {
            coverSurfaces.forEach(({ image, fallback }) => {
                if (image) {
                    image.src = url;
                    image.classList.remove('hidden');
                }
                fallback?.classList.add('hidden');
            });
            coverRemoveBtn?.classList.remove('hidden');
            if (coverLabel) coverLabel.textContent = 'Change cover';
        };

        const clearCover = () => {
            coverSurfaces.forEach(({ image, fallback }) => {
                if (image) {
                    image.classList.add('hidden');
                    image.removeAttribute('src');
                }
                fallback?.classList.remove('hidden');
            });
            coverRemoveBtn?.classList.add('hidden');
            if (coverLabel) coverLabel.textContent = 'Add cover';
        };

        document.getElementById('cover-photo-input')?.addEventListener('change', (e) => {
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
        });

        coverRemoveBtn?.addEventListener('click', async () => {
            if (!await $confirm('The banner will go back to its default look.', { title: 'Remove cover photo?', confirmText: 'Remove cover' })) return;
            coverRemoveBtn.disabled = true;
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
                .finally(() => { coverRemoveBtn.disabled = false; });
        });
    }

    // --- Gallery upload (supports Images/Videos sub-tabs + lightbox) ---
    if (canUpload) {
        document.getElementById('gallery-upload')?.addEventListener('change', (e) => {
            const file = e.target.files?.[0];
            if (!file) return;
            const fd = new FormData();
            fd.append('file', file);
            fd.append('_token', csrf);
            const isVideo = file.type.startsWith('video/');
            const label = isVideo ? 'Uploading video to gallery…' : 'Uploading photo to gallery…';
            postFormDataWithUploadProgress(`${baseUrl}/gallery`, fd, { label })
                .then(data => {
                    if (data.success && data.media) {
                        const video = data.media.type === 'video';
                        if (video) {
                            const grid = document.getElementById('gallery-grid-videos');
                            if (grid) {
                                const el = buildVideoPlayerHtml(data.media.url, data.media.caption);
                                grid.insertAdjacentHTML('beforeend', el);
                                const added = grid.lastElementChild;
                                if (typeof Alpine !== 'undefined' && added) Alpine.initTree(added);
                            }
                        } else {
                            const galleryEl = document.getElementById('tab-gallery');
                            const alpineData = galleryEl?.__x?.$data || Alpine.$data(galleryEl);
                            if (alpineData) {
                                const idx = alpineData.images.length;
                                alpineData.addImage(data.media.url, data.media.caption || '');
                                const grid = document.getElementById('gallery-grid-images');
                                if (grid) {
                                    const btn = document.createElement('button');
                                    btn.type = 'button';
                                    btn.className = 'group relative block aspect-square overflow-hidden rounded-lg bg-gray-200 dark:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-brand-500 focus:ring-offset-2';
                                    btn.setAttribute('@click', `openLightbox(${idx})`);
                                    const altText = data.media.caption || `Gallery photo ${idx + 1}`;
                                    btn.innerHTML = `<img src="${data.media.url}" alt="${escapeHtml(altText)}" class="h-full w-full object-cover transition duration-300 group-hover:scale-105" loading="lazy" /><div class="absolute inset-0 bg-black/0 transition group-hover:bg-black/10"></div>`;
                                    grid.appendChild(btn);
                                }
                            }
                        }
                    } else if (data.error) {
                        $toast('error', data.error);
                    }
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

            // Sync Alpine lightbox data for photos
            if (type === 'photo') {
                try {
                    const galleryEl = document.getElementById('tab-gallery');
                    const alpineData = galleryEl && typeof Alpine !== 'undefined' ? Alpine.$data(galleryEl) : null;
                    if (alpineData?.images) {
                        const idx = parseInt(item?.dataset.galleryIndex ?? -1);
                        if (idx >= 0) {
                            alpineData.images.splice(idx, 1);
                        }
                        document.querySelectorAll('#gallery-grid-images [data-gallery-item][data-media-type="photo"]').forEach((el, i) => {
                            el.dataset.galleryIndex = i;
                        });
                    }
                } catch (_) { /* lightbox state will self-correct on next open */ }
            }

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

            // Show empty state if grid is now empty
            if (type === 'photo') {
                const grid = document.getElementById('gallery-grid-images');
                if (grid && !grid.children.length) {
                    document.getElementById('gallery-images-empty')?.classList.remove('hidden');
                }
            } else {
                const grid = document.getElementById('gallery-grid-videos');
                if (grid && !grid.children.length) {
                    document.getElementById('gallery-videos-empty')?.classList.remove('hidden');
                }
            }

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
        const editor = document.getElementById('gallery-caption-editor');
        const input = document.getElementById('gallery-caption-input');
        const mediaIdInput = document.getElementById('gallery-caption-media-id');
        if (!editor || !input || !mediaIdInput) return;

        mediaIdInput.value = mediaId;
        input.value = currentCaption;
        editor.classList.remove('hidden');
        releaseCaptionEditor = openDialog(editor, { initialFocus: input, onClose: closeCaptionEditor });
    });

    // Caption save
    document.getElementById('gallery-caption-save')?.addEventListener('click', () => {
        const editor = document.getElementById('gallery-caption-editor');
        const input = document.getElementById('gallery-caption-input');
        const mediaId = document.getElementById('gallery-caption-media-id')?.value;
        if (!mediaId) return;

        const saveBtn = document.getElementById('gallery-caption-save');
        const caption = input.value.trim();
        saveBtn.disabled = true;
        saveBtn.textContent = 'Saving...';

        fetch(`${baseUrl}/gallery/${mediaId}`, fetchOpts('PATCH', { caption: caption || null }))
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    // Update the edit button's data attribute
                    document.querySelectorAll(`[data-gallery-edit-caption="${mediaId}"]`).forEach(btn => {
                        btn.dataset.currentCaption = caption;
                    });

                    // Update caption in Alpine images array (for lightbox)
                    const item = document.querySelector(`[data-gallery-item][data-media-id="${mediaId}"][data-media-type="photo"]`);
                    if (item) {
                        const idx = parseInt(item.dataset.galleryIndex ?? -1);
                        try {
                            const galleryEl = document.getElementById('tab-gallery');
                            const alpineData = galleryEl && typeof Alpine !== 'undefined' ? Alpine.$data(galleryEl) : null;
                            if (alpineData?.images?.[idx]) {
                                alpineData.images[idx].caption = caption || '';
                                alpineData.images[idx].alt = caption || `Gallery photo ${idx + 1}`;
                            }
                        } catch (_) { /* lightbox will use alt text as fallback */ }
                        const img = item.querySelector('img');
                        if (img) img.alt = caption || `Gallery photo ${idx + 1}`;
                    }

                    // Update video caption text if it's a video
                    const videoItem = document.querySelector(`[data-gallery-item][data-media-id="${mediaId}"][data-media-type="video"]`);
                    if (videoItem) {
                        const captionEl = videoItem.querySelector('.memorial-video-player + div p, .memorial-video-player .text-xs');
                        if (captionEl) captionEl.textContent = caption;
                    }

                    closeCaptionEditor();
                    $toast('success', 'Caption updated.');
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

    // --- Quill editors ---
    // Most visitors here are reading a memorial someone shared with them and will never
    // open an editor, so Quill is fetched the first time one is actually needed rather
    // than blocking the first paint of every visit.
    let chapterQuill, tributeQuill, biographyQuill;
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
     * The three page-level composers (chapter, tribute note, biography). Resolves once they
     * exist, so callers can await it instead of assuming the variables are already set.
     */
    function initComposerEditors() {
        if (composerEditorsPromise) return composerEditorsPromise;

        const mounts = ['chapter-editor', 'tribute-editor', 'biography-editor']
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
                });
            }
            if (!tributeQuill && document.getElementById('tribute-editor')) {
                tributeQuill = new Quill('#tribute-editor', quillOpts);
                tributeQuill.on('text-change', () => {
                    const el = document.getElementById('tribute-note-message');
                    if (el) el.value = tributeQuill.root.innerHTML;
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

    // --- Add story (tribute post) - any authenticated user can add ---
    const addStoryForm = document.getElementById('add-story-form');
    const addStoryBtnTop = document.getElementById('add-story-btn-top');
    const cancelStoryBtn = document.getElementById('cancel-story-btn');
    const tributePostForm = document.getElementById('tribute-post-form');
    const chapterFormAnchor = document.getElementById('chapter-form-anchor');

    addStoryBtnTop?.addEventListener('click', () => {
        switchToTab('life');
        setTimeout(() => {
            const target = addStoryForm || chapterFormAnchor;
            target?.scrollIntoView({ behavior: 'smooth', block: 'start' });
            const titleInput = addStoryForm?.querySelector('input[name="title"]');
            if (titleInput) {
                titleInput.focus();
            } else {
                // Focus once the editor exists — switchToTab only starts the fetch.
                initComposerEditors().then(() => {
                    document.querySelector('#chapter-editor .ql-editor')?.focus();
                });
            }
        }, 150);
    });

    if (addStoryForm) {
        let chapterFormSubmitting = false;

        cancelStoryBtn?.addEventListener('click', () => {
            if (chapterQuill) chapterQuill.setText('');
            tributePostForm?.reset();
        });

        tributePostForm?.addEventListener('submit', (e) => {
            e.preventDefault();
            e.stopImmediatePropagation();
            if (chapterFormSubmitting) return;
            chapterFormSubmitting = true;

            const form = e.target;
            const submitBtn = form.querySelector('button[type="submit"]');
            submitBtn.disabled = true;
            const originalText = submitBtn.textContent;
            submitBtn.textContent = 'Posting...';
            form.style.pointerEvents = 'none';

            const resetButton = () => {
                chapterFormSubmitting = false;
                submitBtn.disabled = false;
                submitBtn.textContent = originalText;
                form.style.pointerEvents = '';
            };

            const fd = new FormData();
            fd.append('idempotency_key', crypto.randomUUID?.() || Date.now().toString(36) + Math.random().toString(36).slice(2));
            fd.append('title', form.title?.value || '');
            fd.append('content', chapterQuill ? chapterQuill.root.innerHTML : (form.content?.value || ''));
            fd.append('_token', csrf);
            if (!isAuthenticated) {
                const guestName = document.getElementById('chapter-guest-name')?.value?.trim();
                const guestEmail = document.getElementById('chapter-guest-email')?.value?.trim();
                if (!guestName || !guestEmail) {
                    $toast('warning', 'Please enter your name and email to add your chapter.');
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
                ? 'Uploading your chapter and media…'
                : 'Publishing your chapter…';
            postFormDataWithUploadProgress(`${baseUrl}/tribute-post`, fd, { label: uploadLabel })
                .then(data => {
                    if (data.success && data.post) {
                        const feed = document.getElementById('life-feed');
                        if (feed) {
                            const p = data.post;
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
                            article.innerHTML = `
                                <div class="p-4">
                                    <div class="flex items-center gap-3">
                                        ${avatarHtml(p.author_photo, p.author)}
                                        <div>
                                            <p class="font-medium text-gray-900 dark:text-white/90">${escapeHtml(p.author)}</p>
                                            <p class="text-xs text-gray-500 dark:text-gray-400">${p.created_at_iso ? `<span class="time-ago" data-created-at="${p.created_at_iso}">${p.created_at}</span>` : p.created_at} · ${escapeHtml(p.chapter || 'Life')}</p>
                                        </div>
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
                                        <button type="button" data-comment-toggle data-post-id="${p.id}" class="inline-flex items-center gap-1.5 text-gray-600 dark:text-gray-400 hover:text-gray-800 dark:hover:text-gray-200">
                                            <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"/></svg>
                                            <span data-post-id="${p.id}" data-comment-count class="text-sm text-gray-600 dark:text-gray-400">0</span>
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
                                <div data-comment-section="${p.id}" class="hidden border-t border-gray-100 dark:border-gray-800">
                                    <div class="flex items-center gap-2 px-4 py-3">
                                        <div class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-brand-100 dark:bg-brand-500/25 text-brand-600 dark:text-brand-400 text-xs font-semibold">${escapeHtml(document.querySelector('[data-user-initial]')?.dataset.userInitial || 'G')}</div>
                                        <input type="text" data-comment-input="${p.id}" placeholder="Add a comment..." class="h-9 flex-1 rounded-full border border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-white/[0.03] px-3.5 text-sm placeholder:text-gray-400 focus:border-brand-300 focus:outline-none focus:ring-2 focus:ring-brand-500/20" />
                                        <button type="button" data-comment-submit data-post-id="${p.id}" class="btn btn-primary btn-sm rounded-full shrink-0 active:scale-95">Post</button>
                                    </div>
                                    <div class="px-4 pb-3 space-y-0" data-comments-list="${p.id}"></div>
                                    <p data-comments-empty="${p.id}" class="px-4 pb-4 text-center text-xs text-gray-400 dark:text-gray-500">No comments yet. Be the first to comment.</p>
                                </div>
                            `;
                            feed.prepend(article);
                            if (typeof Alpine !== 'undefined') Alpine.initTree(article);
                            article.querySelector('[data-reaction-btn]')?.addEventListener('click', function() {
                                const payload = { reactionable_type: 'post', reactionable_id: p.id, type: 'like' };
                                const doR = (name, email) => {
                                    fetch(`${baseUrl}/reaction`, fetchOpts('POST', { ...payload, guest_name: name, guest_email: email }))
                                        .then(r => r.json())
                                        .then(d => {
                                            if (d.success) {
                                                document.querySelectorAll(`[data-reaction-container="${p.id}"] [data-reaction-count]`).forEach(el => {
                                                    el.textContent = d.count;
                                                });
                                            }
                                        });
                                };
                                isAuthenticated ? doR() : showGuestModal({ type: 'reaction', payload, callback: doR });
                            });
                            // comment toggle + submit are handled by delegated listeners
                        }
                        if (chapterQuill) chapterQuill.setText('');
                        form.reset();
                    } else if (data.error) {
                        $toast('error', data.error);
                    }
                    resetButton();
                })
                .catch(err => {
                    $toast('error', err.message || 'Something went wrong. Please try again.');
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

    // --- Tribute inline editing ---
    const tributeQuillInstances = {};

    function initTributeEditor(tributeId) {
        if (tributeQuillInstances[tributeId]) return Promise.resolve(tributeQuillInstances[tributeId]);
        const editorEl = document.getElementById(`tribute-editor-${tributeId}`);
        if (!editorEl) return Promise.resolve(null);

        return loadQuill().then(() => {
            if (tributeQuillInstances[tributeId]) return tributeQuillInstances[tributeId];
            const q = new Quill(`#tribute-editor-${tributeId}`, {
                theme: 'snow',
                placeholder: 'Write your tribute message...',
                modules: {
                    toolbar: [
                        ['bold', 'italic', 'underline'],
                        [{ 'color': [] }],
                        ['link'],
                        ['clean']
                    ]
                }
            });
            tributeQuillInstances[tributeId] = q;
            return q;
        }).catch(() => {
            $toast('error', 'The editor could not be loaded. Check your connection and try again.');
            return null;
        });
    }

    // Open tribute inline editor
    document.addEventListener('click', (e) => {
        const trigger = e.target.closest('[data-tribute-edit-trigger]');
        if (!trigger) return;
        e.stopPropagation();
        const tributeId = trigger.dataset.tributeEditTrigger;
        const wrapper = document.querySelector(`#tribute-${tributeId}`);
        if (!wrapper) return;

        const displayEl = wrapper.querySelector(`[data-tribute-display="${tributeId}"]`);
        const editEl = wrapper.querySelector(`[data-tribute-edit="${tributeId}"]`);
        if (!displayEl || !editEl) return;

        displayEl.classList.add('hidden');
        editEl.classList.remove('hidden');

        const proseEl = displayEl.querySelector('.prose');
        const html = proseEl?.innerHTML?.trim() || '';
        initTributeEditor(tributeId).then(quill => {
            if (!quill) return;
            quill.setContents([]);
            if (html) {
                quill.clipboard.dangerouslyPasteHTML(0, html);
            }
            requestAnimationFrame(() => quill.focus());
        });
    });

    // Save tribute inline edit
    document.addEventListener('click', (e) => {
        const saveBtn = e.target.closest('[data-tribute-save]');
        if (!saveBtn) return;
        e.stopPropagation();
        const tributeId = saveBtn.dataset.tributeSave;
        const wrapper = document.querySelector(`#tribute-${tributeId}`);
        if (!wrapper) return;

        const displayEl = wrapper.querySelector(`[data-tribute-display="${tributeId}"]`);
        const editEl = wrapper.querySelector(`[data-tribute-edit="${tributeId}"]`);
        const typeRadio = wrapper.querySelector(`input[name="tribute-type-${tributeId}"]:checked`);
        const quill = tributeQuillInstances[tributeId];

        const newType = typeRadio?.value || null;
        const newMessage = quill ? quill.root.innerHTML?.trim() : null;
        const isEmpty = !newMessage || newMessage === '<p><br></p>';

        saveBtn.disabled = true;
        saveBtn.textContent = 'Saving...';

        fetch(`${baseUrl}/tributes/${tributeId}`, fetchOpts('PATCH', {
            type: newType,
            message: isEmpty ? '' : newMessage,
        }))
            .then(r => r.json())
            .then(data => {
                if (data.success && data.tribute) {
                    if (displayEl) displayEl.classList.remove('hidden');
                    if (editEl) editEl.classList.add('hidden');
                    const oldType = wrapper.dataset.tributeType;
                    syncTributeCardAfterSave(wrapper, data.tribute);
                    if (oldType && data.tribute.type && oldType !== data.tribute.type) {
                        updateTributeFilterCounts(oldType, -1);
                        updateTributeFilterCounts(data.tribute.type, 1);
                    }
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

    // Cancel tribute inline edit
    document.addEventListener('click', (e) => {
        const cancelBtn = e.target.closest('[data-tribute-cancel]');
        if (!cancelBtn) return;
        e.stopPropagation();
        const tributeId = cancelBtn.dataset.tributeCancel;
        const wrapper = document.querySelector(`#tribute-${tributeId}`);
        if (!wrapper) return;

        const displayEl = wrapper.querySelector(`[data-tribute-display="${tributeId}"]`);
        const editEl = wrapper.querySelector(`[data-tribute-edit="${tributeId}"]`);
        if (displayEl) displayEl.classList.remove('hidden');
        if (editEl) editEl.classList.add('hidden');
    });

    // Delete tribute
    document.addEventListener('click', async (e) => {
        const deleteBtn = e.target.closest('[data-tribute-delete]');
        if (!deleteBtn) return;
        e.stopPropagation();
        const tributeId = deleteBtn.dataset.tributeDelete;
        if (!await $confirm('This cannot be undone.', { title: 'Delete this tribute?', confirmText: 'Delete tribute' })) return;
        deleteBtn.disabled = true;
        fetch(`${baseUrl}/tributes/${tributeId}`, fetchOpts('DELETE'))
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    document.querySelector(`#tribute-${tributeId}`)?.remove();
                    document.querySelector(`#tribute-preview-${tributeId}`)?.remove();
                    const countEl = document.querySelector('[data-tribute-count]');
                    if (countEl) countEl.textContent = Math.max(0, parseInt(countEl.textContent || 0) - 1);
                    const list = document.querySelector('[data-tributes-list]');
                    if (list && !list.children.length) {
                        const emptyEl = document.querySelector('[data-tributes-empty]');
                        if (emptyEl) emptyEl.classList.remove('hidden');
                    }
                } else if (data.error) {
                    $toast('error', data.error);
                }
            })
            .catch(() => { $toast('error', 'Something went wrong.'); deleteBtn.disabled = false; });
    });

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

        if (pendingAction?.type === 'tribute') {
            // The quick-tribute cards pass their own callback so they can burst and bump
            // their counter once the guest's details are in; the compose form has none and
            // keeps the original clear-the-editor behaviour.
            if (pendingAction.callback) {
                pendingAction.callback(name, email);
            } else {
                submitTribute(pendingAction.payload, name, email).then((res) => {
                    if (res.ok) clearTributeEditor();
                });
            }
        } else if (pendingAction?.type === 'reaction') {
            pendingAction.callback?.(name, email) ?? submitReaction(pendingAction.payload, name, email);
        } else if (pendingAction?.type === 'comment') {
            pendingAction.callback?.(name, email);
        }
        hideGuestModal();
    });

    // --- Tribute (flower, candle, note) ---
    // Resolves to true only when the tribute was accepted, so callers clear
    // the editor on success and keep the visitor's text on any failure.
    /**
     * Did somebody actually write something, or is this a bare tap?
     *
     * Mirrors Tribute::scopeWithMessage() on the server. The tags have to come off first:
     * an untouched rich-text editor submits markup, not an empty string.
     */
    function tributeHasWords(tribute) {
        return (tribute?.message || '')
            .replace(/<[^>]*>/g, '')
            .replace(/&nbsp;/g, ' ')
            .trim().length > 0;
    }

    function submitTribute(payload, guestName, guestEmail, { revealTab = true } = {}) {
        const body = { ...payload };
        if (guestName) body.guest_name = guestName;
        if (guestEmail) body.guest_email = guestEmail;
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
                    // tribute this person already left. Nothing is appended and no tally
                    // moves, so the only thing that happens is the burst the caller played.
                    if (data.duplicate) {
                        // A repeat tap changes nothing. But a message written on top of an
                        // earlier bare tap has just turned that reaction into a post, and
                        // there is no entry in the feed for it to update, so add one.
                        if (data.promoted && tributeHasWords(data.tribute)) {
                            appendTribute(data.tribute, { revealTab });
                            updateTributeCount();
                        }
                        return { ok: true, duplicate: true };
                    }

                    // Every tribute moves the tally under its card, written or not — that
                    // tally counts taps, and is the whole point of the one-tap cards.
                    updateTributeActionCount(data.tribute?.type || body.type, 1);

                    // Only the ones carrying words become posts. A tap with nothing written
                    // is a reaction: it leaves nothing in the feed, the way a like does.
                    // appendTribute moves the filter pills, so they stay in step with what
                    // is actually listed.
                    if (tributeHasWords(data.tribute)) {
                        appendTribute(data.tribute, { revealTab });
                        updateTributeCount();
                    }
                    return { ok: true, duplicate: false };
                } else if (data.requires_login) {
                    hideGuestModal();
                    $toast('warning', (data.error || 'Please sign in to continue.') + ' Taking you to sign in…');
                    setTimeout(() => { window.location.href = window.location.origin + '/login/code'; }, 1800);
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

    document.getElementById('invite-share-btn')?.addEventListener('click', () => {
        const dropdown = document.getElementById('invite-share-dropdown');
        dropdown?.classList.toggle('hidden');
    });
    document.querySelector('[data-share="invite"]')?.addEventListener('click', (e) => {
        e.preventDefault();
        const url = document.getElementById('invite-share-btn')?.dataset?.shareUrl || window.location.href;
        trackShare('invite');
        navigator.clipboard.writeText(url).then(() => {
            const btn = e.target;
            const orig = btn.textContent;
            btn.textContent = 'Copied!';
            setTimeout(() => { btn.textContent = orig; }, 1500);
        });
        document.getElementById('invite-share-dropdown')?.classList.add('hidden');
    });

    document.getElementById('add-tribute-btn')?.addEventListener('click', () => {
        switchToTab('tributes');
        document.getElementById('tribute-form-anchor')?.scrollIntoView({ behavior: 'smooth', block: 'start' });
        // Focus once the editor exists — switchToTab only starts the fetch.
        initComposerEditors().then(() => {
            document.querySelector('#tribute-editor .ql-editor')?.focus();
        });
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
    // fall are the same violets the bouquet on the card is made of.
    //
    // The artwork's own lightest tints are left out. Each petal carries a white highlight
    // overlay, and on a tint that pale the highlight takes the whole shape to near-white —
    // which in a field of violet reads as a different object rather than as a petal
    // catching the light.
    const PETAL_COLOURS = ['#a060c0', '#b070d0', '#9050b0', '#c080d0', '#8040a0', '#b070c0'];

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
            //
            // Guests are short-circuited here too, deliberately: sending someone through
            // sign-up for something that will not be recorded is worse than doing nothing.
            if (card.closest('[data-tribute-quota-reached]')) {
                burstFrom(x, y, type, artSrc);
                return;
            }

            // The tallies are moved by submitTribute now, which is the only place that knows
            // whether what came back is a written tribute or a bare tap.
            if (isAuthenticated) {
                // Fire immediately — waiting on the round trip is what makes a tap feel dead.
                // It plays on every tap, including repeats: the burst confirms the tap landed,
                // while the count only moves the first time. Same contract as double-tapping
                // a post you have already liked.
                burstFrom(x, y, type, artSrc);
                submitTribute({ type }, undefined, undefined, { revealTab: false });
                return;
            }

            // Guests have to identify themselves first. Celebrating before that would be
            // celebrating something that has not happened yet, so the burst waits.
            showGuestModal({
                type: 'tribute',
                payload: { type },
                callback: (name, email) => {
                    submitTribute({ type }, name, email, { revealTab: false }).then(res => {
                        if (!res.ok) return;
                        burstFrom(rect.left + rect.width / 2, rect.top + rect.height / 2, type, artSrc);
                    });
                },
            });
        });
    });

    function clearTributeEditor() {
        if (tributeQuill) tributeQuill.setText('');
        const msgEl = document.getElementById('tribute-note-message');
        if (msgEl) msgEl.value = '';
    }

    document.getElementById('tribute-note-submit')?.addEventListener('click', (e) => {
        const submitBtn = e.currentTarget;
        if (submitBtn.disabled) return;
        const name = document.getElementById('tribute-note-name')?.value?.trim();
        const email = document.getElementById('tribute-note-email')?.value?.trim();
        const typeEl = document.querySelector('input[name="tribute-type"]:checked');
        const type = typeEl?.value || 'prayer';
        const message = tributeQuill ? tributeQuill.root.innerHTML : (document.getElementById('tribute-note-message')?.value?.trim() || '');
        if (!message || message === '<p><br></p>') {
            $toast('error', 'Write a message first — even a sentence is enough.');
            return;
        }

        if (!isAuthenticated && !(name && email)) {
            showGuestModal({ type: 'tribute', payload: { type, message } });
            return;
        }

        const originalLabel = submitBtn.textContent;
        submitBtn.disabled = true;
        submitBtn.textContent = 'Posting…';
        submitTribute({ type, message }, ...(isAuthenticated ? [] : [name, email])).then((res) => {
            submitBtn.disabled = false;
            submitBtn.textContent = originalLabel;
            if (! res.ok) return;
            clearTributeEditor();
            // Their words were attached to the tribute of this kind they already had, so
            // say where it went rather than leaving the form looking like it did nothing.
            if (res.duplicate) $toast('success', 'Your message was added to the tribute you already left.');
        });
    });

    const tributeCardConfig = {
        flower: {
            card: 'border-violet-200/60 dark:border-violet-800/40 bg-violet-50/40 dark:bg-violet-950/20',
            avatar: 'bg-violet-200/70 dark:bg-violet-800/40 text-violet-700 dark:text-violet-300',
            inner: 'bg-violet-100/50 dark:bg-violet-900/20 border border-violet-200/40 dark:border-violet-800/30',
            border: 'border-violet-200/40 dark:border-violet-800/30',
            fallbackArt: '<svg class="h-full w-full text-violet-400 tribute-icon-sway" viewBox="0 0 24 24" fill="currentColor"><path d="M12 2C9.5 2 7.5 4.5 7.5 7c0 1.8 1 3.4 2.5 4.2V22h4V11.2c1.5-.8 2.5-2.4 2.5-4.2 0-2.5-2-5-4.5-5zm-2 7c-1.1 0-2-.9-2-2s.9-2 2-2 2 .9 2 2-.9 2-2 2zm4 0c-1.1 0-2-.9-2-2s.9-2 2-2 2 .9 2 2-.9 2-2 2z"/></svg>',
        },
        candle: {
            card: 'border-amber-200/60 dark:border-amber-800/40 bg-amber-50/40 dark:bg-amber-950/20',
            avatar: 'bg-amber-200/70 dark:bg-amber-800/40 text-amber-700 dark:text-amber-300',
            inner: 'bg-amber-100/50 dark:bg-amber-900/20 border border-amber-200/40 dark:border-amber-800/30',
            border: 'border-amber-200/40 dark:border-amber-800/30',
            fallbackArt: '<svg class="h-full w-full text-amber-400 tribute-icon-flicker" viewBox="0 0 24 24" fill="currentColor"><path d="M12 2c-.5 0-1 .19-1.41.59l-1.3 1.3C8.78 4.4 8.5 5.13 8.5 5.91c0 1.97 1.6 3.59 3.5 3.59s3.5-1.62 3.5-3.59c0-.78-.28-1.51-.79-2.02l-1.3-1.3C13 2.19 12.5 2 12 2zm-1 8.5V22h2V10.5h-2z"/></svg>',
        },
        prayer: {
            card: 'border-sky-200/60 dark:border-sky-800/40 bg-sky-50/40 dark:bg-sky-950/20',
            avatar: 'bg-sky-200/70 dark:bg-sky-800/40 text-sky-700 dark:text-sky-300',
            inner: 'bg-sky-100/50 dark:bg-sky-900/20 border border-sky-200/40 dark:border-sky-800/30',
            border: 'border-sky-200/40 dark:border-sky-800/30',
            fallbackArt: '<svg class="h-full w-full text-sky-400 tribute-icon-uplift" viewBox="0 0 24 24" fill="currentColor"><path d="M11.4 1.9c-1.1 1-1.95 2.3-2.55 3.85C8 7.9 7.55 10.15 7.55 12.5v2.8c0 1.45.9 2.75 2.25 3.25l1.6.6z"/><path d="M11.4 16.5v3.25l-2.2 1.35a2.4 2.4 0 0 1-3.3-.8 2.4 2.4 0 0 1 .8-3.3l2.7-1.65z"/><g transform="translate(24,0) scale(-1,1)"><path d="M11.4 1.9c-1.1 1-1.95 2.3-2.55 3.85C8 7.9 7.55 10.15 7.55 12.5v2.8c0 1.45.9 2.75 2.25 3.25l1.6.6z"/><path d="M11.4 16.5v3.25l-2.2 1.35a2.4 2.4 0 0 1-3.3-.8 2.4 2.4 0 0 1 .8-3.3l2.7-1.65z"/></g></svg>',
        },
    };


    /**
     * The header artwork for a tribute type, matching what the tribute-art partial renders
     * server-side so a card built here is indistinguishable from one built in Blade.
     *
     * The source is lifted off the one-tap card already on the page rather than assembled
     * from a hardcoded path: that way it follows whatever the app's asset URL happens to be
     * — subdirectory installs, a CDN, a reseller domain — without this file knowing any of
     * it. When there is no card to read from (the tribute quota is used up, so the cards
     * are replaced by a notice) or the card is itself drawing SVG, the inline motif stands
     * in.
     */
    function tributeArtHtml(type) {
        const cfg = tributeCardConfig[type] || tributeCardConfig.prayer;
        const src = document.querySelector(`[data-tribute-action="${type}"] .memorial-tribute-action__art img`)?.src;
        const art = src
            ? `<img src="${escapeHtml(src)}" alt="" class="h-full w-full object-contain" />`
            : cfg.fallbackArt;

        return `<span data-tribute-art class="pointer-events-none block h-9 w-9 shrink-0" aria-hidden="true">${art}</span>`;
    }

    /**
     * All of them, not the first of them. The total now appears twice — on the Tributes
     * sub-tab and on the All filter pill — and singling one out would leave the other
     * showing a number that was correct when the page loaded and never again.
     */
    function updateTributeFilterCounts(type, delta) {
        const bump = (el) => {
            el.textContent = parseInt(el.textContent || '0', 10) + delta;
        };
        document.querySelectorAll(`[data-count-${type}]`).forEach(bump);
        document.querySelectorAll('[data-count-all]').forEach(bump);
    }

    function getInitials(name) {
        return name.split(/\s+/).map(w => w.charAt(0).toUpperCase()).slice(0, 2).join('');
    }

    function avatarHtml(photo, name, size = 'h-10 w-10', fallbackClasses = 'bg-brand-100 dark:bg-brand-500/30 text-brand-600 dark:text-brand-400 text-sm font-semibold') {
        if (photo) {
            return `<img src="${escapeHtml(photo)}" alt="${escapeHtml(name || '')}" class="${size} shrink-0 rounded-full object-cover" />`;
        }
        const initial = (name || '?').charAt(0).toUpperCase();
        return `<div class="flex ${size} shrink-0 items-center justify-center rounded-full ${fallbackClasses}">${escapeHtml(initial)}</div>`;
    }

    function syncTributeCardAfterSave(wrapper, t) {
        const id = wrapper.dataset.tributeId;
        const type = t.type || 'prayer';
        const cfg = tributeCardConfig[type] || tributeCardConfig.prayer;
        wrapper.dataset.tributeType = type;
        wrapper.className = `group rounded-xl border p-4 transition ${cfg.card}`;
        const body = wrapper.querySelector('[data-tribute-body]');
        if (body) {
            body.className = `mt-3 rounded-lg p-3 ${cfg.inner}`;
        }
        const display = id ? wrapper.querySelector(`[data-tribute-display="${id}"]`) : null;
        if (display) {
            display.innerHTML = `<div class="text-sm text-gray-700 dark:text-gray-300 prose prose-sm dark:prose-invert max-w-none">${t.message || ''}</div>`;
        }
        const footer = wrapper.querySelector('[data-tribute-footer]');
        if (footer) {
            footer.className = `relative z-10 mt-3 border-t pt-3 ${cfg.border}`;
        }
        const avFallback = wrapper.querySelector('[data-tribute-avatar-fallback]');
        if (avFallback) {
            avFallback.className = `flex h-10 w-10 shrink-0 items-center justify-center rounded-full text-sm font-semibold ${cfg.avatar}`;
        }
        const iconsWrap = wrapper.querySelector('[data-tribute-header-icons]');
        if (iconsWrap) {
            const editBtn = iconsWrap.querySelector('[data-tribute-edit-trigger]');
            const editHtml = editBtn ? editBtn.outerHTML : '';
            iconsWrap.innerHTML = editHtml + tributeArtHtml(type);
        }
    }

    function buildTributeCommentTopHtml(c, tributeId) {
        const tcAvatar = avatarHtml(c.author_photo, c.author, 'h-6 w-6', 'bg-gray-200 dark:bg-gray-700 text-[10px] font-semibold text-gray-500 dark:text-gray-400');
        const del = canEdit ? `<button type="button" data-delete-tribute-comment data-comment-id="${c.id}" data-tribute-id="${tributeId}" class="text-xs font-medium text-gray-400 dark:text-gray-500 hover:text-red-500 dark:hover:text-red-400">Delete</button>` : '';
        return `<div class="mb-3 last:mb-0 rounded-lg bg-gray-50 dark:bg-white/[0.02] px-3 py-2" data-tribute-comment-id="${c.id}"><div class="flex items-center gap-2 mb-1">${tcAvatar}<p class="text-sm font-medium text-gray-900 dark:text-white/90">${escapeHtml(c.author)}</p></div><p class="text-sm text-gray-700 dark:text-gray-300 break-words whitespace-pre-wrap">${escapeHtml(c.content)}</p><div class="flex flex-wrap items-center gap-2 mt-1"><p class="text-xs text-gray-500 dark:text-gray-400">${escapeHtml(c.created_at)}</p><button type="button" data-tribute-reply-to data-comment-id="${c.id}" data-tribute-id="${tributeId}" class="text-xs text-brand-500 hover:text-brand-600 dark:hover:text-brand-400">Reply</button>${del}</div><div data-tribute-reply-form="${c.id}" class="mt-2 hidden"><div class="flex flex-wrap items-center gap-2"><input type="text" data-tribute-reply-input="${c.id}" placeholder="Write a reply..." class="h-9 min-w-0 flex-1 basis-36 rounded-full border border-gray-300 bg-gray-50 px-3 text-sm placeholder:text-gray-400 focus:border-brand-300 focus:outline-none focus:ring-2 focus:ring-brand-500/20 dark:border-gray-600 dark:bg-gray-900 dark:text-white" /><button type="button" data-tribute-reply-submit data-comment-id="${c.id}" data-tribute-id="${tributeId}" class="btn btn-primary btn-sm rounded-full shrink-0 active:scale-95">Post</button></div></div></div>`;
    }

    function buildTributeReplyHtml(c, tributeId) {
        const trAvatar = avatarHtml(c.author_photo, c.author, 'h-6 w-6', 'bg-gray-200 dark:bg-gray-700 text-[10px] font-semibold text-gray-500 dark:text-gray-400');
        const del = canEdit ? `<button type="button" data-delete-tribute-comment data-comment-id="${c.id}" data-tribute-id="${tributeId}" class="text-xs font-medium text-gray-400 dark:text-gray-500 hover:text-red-500 dark:hover:text-red-400">Delete</button>` : '';
        return `<div class="mb-3 last:mb-0 rounded-lg bg-gray-50 dark:bg-white/[0.02] px-3 py-2 ml-3 sm:ml-4 border-l-2 border-gray-200 dark:border-gray-700" data-tribute-comment-id="${c.id}"><div class="flex items-center gap-2 mb-1">${trAvatar}<p class="text-sm font-medium text-gray-900 dark:text-white/90">${escapeHtml(c.author)}</p></div><p class="text-sm text-gray-700 dark:text-gray-300 break-words whitespace-pre-wrap">${escapeHtml(c.content)}</p><div class="flex flex-wrap items-center gap-2 mt-1"><p class="text-xs text-gray-500 dark:text-gray-400">${escapeHtml(c.created_at)}</p>${del}</div></div>`;
    }

    function appendTribute(t, { revealTab = true } = {}) {
        const list = document.querySelector('[data-tributes-list]');
        if (!list) return;
        // The quick-tribute cards leave this false: yanking the visitor to another tab
        // mid-burst throws away the feedback the burst exists to give.
        if (revealTab) {
            // Through switchToTab rather than clicking the button directly, so the panel
            // also lands on the Tributes pane — a new tribute revealed behind the Stories
            // pane is a tribute the visitor cannot see.
            switchToTab('tributes');
        }

        const cfg = tributeCardConfig[t.type] || tributeCardConfig.prayer;
        const shareUrl = t.share_id ? `${window.location.origin}/${memorialSlug}/tribute/${t.share_id}` : `${window.location.origin}/${memorialSlug}/tribute/${t.id || 'new'}`;
        const timeEl = t.created_at_iso ? `<p class="text-xs text-gray-500 dark:text-gray-400 time-ago" data-created-at="${t.created_at_iso}">${escapeHtml(t.created_at)}</p>` : `<p class="text-xs text-gray-500 dark:text-gray-400">${escapeHtml(t.created_at)}</p>`;
        const initials = getInitials(t.author || 'A');
        const tributeAvatarEl = t.author_photo
            ? `<img src="${escapeHtml(t.author_photo)}" alt="${escapeHtml(t.author || '')}" class="h-10 w-10 shrink-0 rounded-full object-cover" />`
            : `<div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full text-sm font-semibold ${cfg.avatar}">${escapeHtml(initials)}</div>`;
        // Just the message, in a tinted block. A tribute with nothing written is a reaction
        // and gets no body at all — the same shape the Blade partial renders.
        const contentBlock = t.message
            ? `<div data-tribute-body class="mt-3 rounded-lg p-3 ${cfg.inner}">
                   <div data-tribute-display="${t.id || 'new'}">
                       <div class="text-sm text-gray-700 dark:text-gray-300 prose prose-sm dark:prose-invert max-w-none">${t.message}</div>
                   </div>
               </div>`
            : '';

        const div = document.createElement('div');
        div.id = 'tribute-' + (t.id || 'new');
        div.dataset.tributeId = t.id || 'new';
        div.dataset.tributeType = t.type;
        div.className = `rounded-xl border p-4 transition ${cfg.card}`;
        div.innerHTML = `
            <div class="flex items-start gap-3">
                ${tributeAvatarEl}
                <div class="min-w-0 flex-1">
                    <p class="font-semibold text-gray-900 dark:text-white/90 truncate">${escapeHtml(t.author)}</p>
                    ${timeEl}
                </div>
                <div data-tribute-header-icons class="flex items-center gap-1 shrink-0">${tributeArtHtml(t.type)}</div>
            </div>
            ${contentBlock}
            <div class="mt-3 flex items-center justify-between border-t pt-3 ${cfg.border}">
                <div class="flex items-center gap-4">
                    <button type="button" class="inline-flex items-center gap-1.5 text-sm text-gray-500 dark:text-gray-400 hover:text-red-500 dark:hover:text-red-400 transition">
                        <svg class="h-4.5 w-4.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z"/></svg>
                        <span>0</span>
                    </button>
                </div>
                <div class="relative" data-share-container data-tribute-id="${t.id || 'new'}">
                    <button type="button" data-share-toggle data-share-url="${shareUrl}" class="inline-flex items-center gap-1.5 text-sm text-gray-500 dark:text-gray-400 hover:text-brand-500 dark:hover:text-brand-400 transition">
                        <svg class="h-4.5 w-4.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.684 13.342C8.886 12.938 9 12.482 9 12c0-.482-.114-.938-.316-1.342m0 2.684a3 3 0 110-2.684m0 2.684l6.632 3.316m-6.632-6l6.632-3.316m0 0a3 3 0 105.367-2.684 3 3 0 00-5.367 2.684zm0 9.316a3 3 0 105.368 2.684 3 3 0 00-5.368-2.684z"/></svg>
                        Reply
                    </button>
                    <div data-share-dropdown-tribute class="absolute right-0 top-full z-[9999] mt-1 hidden w-52 rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 shadow-xl p-1.5">
                        ${shareDropdownHtml(shareUrl)}
                    </div>
                </div>
            </div>
        `;
        list.prepend(div);
        const emptyEl = document.querySelector('[data-tributes-empty]');
        if (emptyEl) emptyEl.classList.add('hidden');
        updateTributeFilterCounts(t.type, 1);
        div.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
        div.querySelectorAll('[data-share]').forEach(b => {
            b.addEventListener('click', (e) => {
                e.preventDefault();
                const url = b.dataset.shareUrl;
                const encoded = encodeURIComponent(url);
                const shareType = ['whatsapp', 'facebook', 'linkedin', 'copy'].includes(b.dataset.share) ? b.dataset.share : 'copy';
                trackShare(shareType);
                if (b.dataset.share === 'copy') {
                    navigator.clipboard.writeText(url).then(() => { const orig = b.textContent; b.textContent = 'Copied'; setTimeout(() => b.textContent = orig, 1500); });
                } else if (b.dataset.share === 'whatsapp') {
                    window.open(`https://wa.me/?text=${encodeURIComponent(document.title)}%20${encoded}`, '_blank');
                } else if (b.dataset.share === 'facebook') {
                    window.open(`https://www.facebook.com/sharer/sharer.php?u=${encoded}`, '_blank');
                } else if (b.dataset.share === 'linkedin') {
                    window.open(`https://www.linkedin.com/sharing/share-offsite/?url=${encoded}`, '_blank');
                }
            });
        });
    }

    function updateTributeCount() {
        const el = document.querySelector('[data-tribute-count]');
        if (el) el.textContent = parseInt(el.textContent || 0) + 1;
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
                        if (data.success) {
                            document.querySelectorAll(`[data-reaction-container="${payload.reactionable_id}"] [data-reaction-count]`).forEach(el => {
                                el.textContent = data.count;
                            });
                        }
                    });
    }

    document.querySelectorAll('[data-reaction-btn]').forEach(btn => {
        btn.addEventListener('click', () => {
            const payload = {
                reactionable_type: btn.dataset.reactionableType,
                reactionable_id: parseInt(btn.dataset.reactionableId),
                type: btn.dataset.reactionType || 'like',
            };

            const doReaction = (name, email) => {
                const body = { ...payload };
                if (name) body.guest_name = name;
                if (email) body.guest_email = email;
                fetch(`${baseUrl}/reaction`, fetchOpts('POST', body))
                    .then(r => r.json())
                    .then(data => {
                        if (data.success) {
                            document.querySelectorAll(`[data-reaction-container="${payload.reactionable_id}"] [data-reaction-count]`).forEach(el => {
                                el.textContent = data.count;
                            });
                        } else if (data.requires_login) {
                            $toast('warning', (data.error || 'Please sign in.') + ' ' + window.location.origin + '/login/code');
                        } else if (data.error) {
                            $toast('error', data.error);
                        }
                    });
            };

            if (isAuthenticated) {
                doReaction();
            } else {
                showGuestModal({ type: 'reaction', payload, callback: (name, email) => doReaction(name, email) });
            }
        });
    });

    document.addEventListener('click', (e) => {
        const reactBtn = e.target.closest('[data-tribute-react]');
        if (!reactBtn) return;
        e.preventDefault();
        e.stopPropagation();
        const tributeId = parseInt(reactBtn.dataset.tributeReact, 10);
        if (!tributeId) return;
        const payload = { reactionable_type: 'tribute', reactionable_id: tributeId, type: 'like' };
        const doReaction = (name, email) => {
            const body = { ...payload };
            if (name) body.guest_name = name;
            if (email) body.guest_email = email;
            fetch(`${baseUrl}/reaction`, fetchOpts('POST', body))
                .then(r => r.json())
                .then(data => {
                    if (data.success) {
                        document.querySelectorAll(`[data-tribute-reaction-count="${tributeId}"]`).forEach(el => { el.textContent = data.count; });
                    } else if (data.requires_guest_info) {
                        showGuestModal({ type: 'reaction', payload, callback: (n, em) => doReaction(n, em) });
                    } else if (data.requires_login) {
                        $toast('warning', (data.error || 'Please sign in.') + ' ' + window.location.origin + '/login/code');
                    } else if (data.error) {
                        $toast('error', data.error);
                    }
                })
                .catch(() => $toast('error', 'Something went wrong.'));
        };
        if (isAuthenticated) {
            doReaction();
        } else {
            showGuestModal({ type: 'reaction', payload, callback: (name, email) => doReaction(name, email) });
        }
    });

    // --- Comment toggle (inline section) ---
    document.addEventListener('click', (e) => {
        const toggleBtn = e.target.closest('[data-comment-toggle]');
        if (!toggleBtn) return;
        e.stopPropagation();
        const postId = toggleBtn.dataset.postId;
        const article = toggleBtn.closest('article.life-feed-post');
        const section = article?.querySelector(`[data-comment-section="${postId}"]`) || document.querySelector(`#life-feed [data-comment-section="${postId}"]`);
        if (section) {
            section.classList.toggle('hidden');
            if (!section.classList.contains('hidden')) {
                const input = section.querySelector(`[data-comment-input="${postId}"]`);
                if (input) setTimeout(() => input.focus(), 50);
            }
        }
    });

    // --- Biography preview: open Life tab + comments for this post ---
    document.addEventListener('click', (e) => {
        const btn = e.target.closest('[data-open-life-comments]');
        if (!btn) return;
        e.preventDefault();
        e.stopPropagation();
        const postId = btn.dataset.openLifeComments;
        switchToTab('life');
        const openSection = () => {
            const section = document.querySelector(`#life-feed [data-comment-section="${postId}"]`);
            const anchor = document.getElementById('chapter-' + postId);
            anchor?.scrollIntoView({ behavior: 'smooth', block: 'start' });
            section?.classList.remove('hidden');
            const input = section?.querySelector(`[data-comment-input="${postId}"]`);
            if (input) setTimeout(() => input.focus(), 200);
        };
        requestAnimationFrame(() => requestAnimationFrame(openSection));
    });

    // --- Biography preview: open Tributes tab + scroll to tribute ---
    document.addEventListener('click', (e) => {
        const btn = e.target.closest('[data-open-tributes-tribute]');
        if (!btn) return;
        e.preventDefault();
        e.stopPropagation();
        const tributeId = btn.dataset.openTributesTribute;
        switchToTab('tributes');
        const scrollTo = () => {
            document.getElementById('tribute-' + tributeId)?.scrollIntoView({ behavior: 'smooth', block: 'start' });
        };
        requestAnimationFrame(() => requestAnimationFrame(scrollTo));
    });

    // --- Enter to submit comment/reply ---
    document.addEventListener('keydown', (e) => {
        if (e.key !== 'Enter') return;
        const commentInput = e.target.closest('[data-comment-input]');
        const tributeCommentInput = e.target.closest('[data-tribute-comment-input]');
        const replyInput = e.target.closest('[data-reply-input]');
        if (commentInput) {
            e.preventDefault();
            const section = commentInput.closest('[data-comment-section]');
            section?.querySelector('[data-comment-submit]')?.click();
        } else if (tributeCommentInput) {
            e.preventDefault();
            const tributeId = tributeCommentInput.dataset.tributeCommentInput;
            const panel = tributeCommentInput.closest('[data-tribute-comment-dropdown]');
            panel?.querySelector(`[data-tribute-comment-submit][data-tribute-id="${tributeId}"]`)?.click();
        } else if (e.target.closest('[data-tribute-reply-input]')) {
            e.preventDefault();
            const inp = e.target.closest('[data-tribute-reply-input]');
            inp.closest('[data-tribute-reply-form]')?.querySelector('[data-tribute-reply-submit]')?.click();
        } else if (replyInput) {
            e.preventDefault();
            const form = replyInput.closest('[data-reply-form]');
            form?.querySelector('[data-reply-submit]')?.click();
        }
    });

    // --- Share toggle (posts and tributes) ---
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
            document.querySelectorAll('[data-share-dropdown], [data-share-dropdown-tribute]').forEach(d => { if (d !== dropdown) d.classList.add('hidden'); });
            dropdown?.classList.toggle('hidden');
            return;
        }
    });

    // --- Tribute comment toggle ---
    document.querySelectorAll('[data-tribute-comment-toggle]').forEach(btn => {
        btn.addEventListener('click', (e) => {
            e.stopPropagation();
            const tributeId = btn.dataset.tributeId;
            const dropdown = document.querySelector(`[data-tribute-comment-dropdown="${tributeId}"]`);
            document.querySelectorAll('[data-tribute-comment-dropdown]').forEach(d => { if (d !== dropdown) d.classList.add('hidden'); });
            // legacy dropdown reference removed; comments are inline now
            dropdown?.classList.toggle('hidden');
        });
    });

    // --- Tribute comment submit ---
    document.querySelectorAll('[data-tribute-comment-submit]').forEach(btn => {
        btn.addEventListener('click', function() {
            const tributeId = parseInt(this.dataset.tributeId);
            const panel = this.closest('[data-tribute-comment-dropdown]');
            const input = panel?.querySelector(`[data-tribute-comment-input="${tributeId}"]`) ?? document.querySelector(`[data-tribute-comment-input="${tributeId}"]`);
            const content = input?.value?.trim();
            if (!content) return;
            if (this.disabled) return;
            this.disabled = true;
            const origText = this.textContent;
            this.textContent = 'Posting...';
            const resetBtn = () => { this.disabled = false; this.textContent = origText; };
            const doSubmit = (guestName, guestEmail) => {
                const body = { content };
                if (guestName) body.guest_name = guestName;
                if (guestEmail) body.guest_email = guestEmail;
                fetch(`${baseUrl}/tributes/${tributeId}/comments`, fetchOpts('POST', body))
                    .then(r => r.json())
                    .then(data => {
                        if (data.success && data.comment) {
                            const list = document.querySelector(`[data-tribute-comments-list="${tributeId}"]`);
                            const empty = document.querySelector(`[data-tribute-comments-empty="${tributeId}"]`);
                            if (list) {
                                const wrap = document.createElement('div');
                                wrap.innerHTML = buildTributeCommentTopHtml(data.comment, tributeId);
                                list.appendChild(wrap.firstElementChild);
                            }
                            if (empty) empty.classList.add('hidden');
                            bumpTributeCommentCount(tributeId, 1);
                            input.value = '';
                        } else if (data.error) $toast('error', data.error);
                        resetBtn();
                    })
                    .catch(() => { $toast('error', 'Something went wrong.'); resetBtn(); });
            };
            if (isAuthenticated) doSubmit();
            else showGuestModal({ type: 'comment', payload: { content }, callback: (name, email) => doSubmit(name, email) });
        });
    });

    // --- Tribute reply toggle and submit ---
    document.addEventListener('click', (e) => {
        const replyBtn = e.target.closest('[data-tribute-reply-to]');
        if (replyBtn) {
            e.stopPropagation();
            const commentId = replyBtn.dataset.commentId;
            const form = document.querySelector(`[data-tribute-reply-form="${commentId}"]`);
            document.querySelectorAll('[data-tribute-reply-form]').forEach(f => { if (f !== form) f.classList.add('hidden'); });
            form?.classList.toggle('hidden');
            const input = document.querySelector(`[data-tribute-reply-input="${commentId}"]`);
            if (form?.classList.contains('hidden') === false && input) input.focus();
        }
    });

    document.addEventListener('click', (e) => {
        const submitBtn = e.target.closest('[data-tribute-reply-submit]');
        if (submitBtn) {
            e.stopPropagation();
            const tributeId = parseInt(submitBtn.dataset.tributeId);
            const parentId = parseInt(submitBtn.dataset.commentId);
            const replyFormEl = submitBtn.closest('[data-tribute-reply-form]');
            const input = replyFormEl?.querySelector(`[data-tribute-reply-input="${parentId}"]`) ?? document.querySelector(`[data-tribute-reply-input="${parentId}"]`);
            const content = input?.value?.trim();
            if (!content) return;
            if (submitBtn.disabled) return;
            submitBtn.disabled = true;
            const origText = submitBtn.textContent;
            submitBtn.textContent = 'Posting...';
            const resetBtn = () => { submitBtn.disabled = false; submitBtn.textContent = origText; };
            const doSubmit = (guestName, guestEmail) => {
                const body = { content, parent_id: parentId };
                if (guestName) body.guest_name = guestName;
                if (guestEmail) body.guest_email = guestEmail;
                fetch(`${baseUrl}/tributes/${tributeId}/comments`, fetchOpts('POST', body))
                    .then(r => r.json())
                    .then(data => {
                        if (data.success && data.comment) {
                            const repliesList = document.querySelector(`[data-tribute-replies-list="${parentId}"]`);
                            const replyForm = document.querySelector(`[data-tribute-reply-form="${parentId}"]`);
                            const appendReply = (listEl) => {
                                const wrap = document.createElement('div');
                                wrap.innerHTML = buildTributeReplyHtml(data.comment, tributeId);
                                listEl.appendChild(wrap.firstElementChild);
                            };
                            if (repliesList) {
                                appendReply(repliesList);
                            } else {
                                const parentComment = document.querySelector(`[data-tribute-comment-id="${parentId}"]`);
                                if (parentComment) {
                                    let list = parentComment.querySelector(`[data-tribute-replies-list="${parentId}"]`);
                                    if (!list) {
                                        list = document.createElement('div');
                                        list.className = 'mt-2 space-y-2';
                                        list.setAttribute('data-tribute-replies-list', String(parentId));
                                        parentComment.appendChild(list);
                                    }
                                    appendReply(list);
                                }
                            }
                            bumpTributeCommentCount(tributeId, 1);
                            input.value = '';
                            replyForm?.classList.add('hidden');
                        } else if (data.error) $toast('error', data.error);
                        resetBtn();
                    })
                    .catch(() => { $toast('error', 'Something went wrong.'); resetBtn(); });
            };
            if (isAuthenticated) doSubmit();
            else showGuestModal({ type: 'comment', payload: { content }, callback: (name, email) => doSubmit(name, email) });
        }
    });

    // --- Click outside to close dropdowns (share/tribute only, comments are inline now) ---
    document.addEventListener('click', (e) => {
        if (e.target.closest('[data-share-container], [data-tribute-comment-container], [data-tribute-comment-dropdown], #invite-share-btn, #invite-share-dropdown')) return;
        document.querySelectorAll('[data-share-dropdown], [data-share-dropdown-tribute], [data-tribute-comment-dropdown]').forEach(d => d.classList.add('hidden'));
        document.getElementById('invite-share-dropdown')?.classList.add('hidden');
    });

    // --- Reply toggle and submit (threaded) ---
    document.addEventListener('click', (e) => {
        const replyBtn = e.target.closest('[data-reply-to]');
        if (replyBtn) {
            e.stopPropagation();
            const commentId = replyBtn.dataset.commentId;
            const form = document.querySelector(`[data-reply-form="${commentId}"]`);
            document.querySelectorAll('[data-reply-form]').forEach(f => { if (f !== form) f.classList.add('hidden'); });
            form?.classList.toggle('hidden');
            const input = document.querySelector(`[data-reply-input="${commentId}"]`);
            if (form && !form.classList.contains('hidden') && input) input.focus();
        }
    });

    document.addEventListener('click', (e) => {
        const submitBtn = e.target.closest('[data-reply-submit]');
        if (submitBtn) {
            e.stopPropagation();
            const postId = parseInt(submitBtn.dataset.postId);
            const parentId = parseInt(submitBtn.dataset.commentId);
            const input = document.querySelector(`[data-reply-input="${parentId}"]`);
            const content = input?.value?.trim();
            if (!content) return;
            if (submitBtn.disabled) return;
            submitBtn.disabled = true;
            const origText = submitBtn.textContent;
            submitBtn.textContent = '...';
            const resetBtn = () => { submitBtn.disabled = false; submitBtn.textContent = origText; };
            const doSubmit = (guestName, guestEmail) => {
                const body = { content, parent_id: parentId };
                if (guestName) body.guest_name = guestName;
                if (guestEmail) body.guest_email = guestEmail;
                fetch(`${baseUrl}/posts/${postId}/comments`, fetchOpts('POST', body))
                    .then(r => r.json())
                    .then(data => {
                        if (data.success && data.comment) {
                            const parentComment = document.querySelector(`[data-comment-id="${parentId}"]`);
                            if (parentComment) {
                                const contentWrap = parentComment.querySelector(':scope > .min-w-0');
                                let repliesList = contentWrap?.querySelector(`[data-replies-list="${parentId}"]`);
                                if (!repliesList) {
                                    repliesList = document.createElement('div');
                                    repliesList.className = 'mt-1 space-y-0';
                                    repliesList.dataset.repliesList = parentId;
                                    contentWrap?.appendChild(repliesList);
                                }
                                const replyAvatar = avatarHtml(data.comment.author_photo, data.comment.author, 'h-7 w-7 sm:h-8 sm:w-8', 'bg-gray-200 dark:bg-gray-700 text-gray-500 dark:text-gray-400 text-[11px] sm:text-xs font-semibold');
                                const deleteHtml = canEdit ? `<button type="button" data-delete-comment data-comment-id="${data.comment.id}" data-post-id="${postId}" class="text-xs font-medium text-gray-400 dark:text-gray-500 hover:text-red-500 dark:hover:text-red-400 transition">Delete</button>` : '';
                                const replyEl = document.createElement('div');
                                replyEl.className = 'relative flex gap-2 sm:gap-3 ml-6 sm:ml-10';
                                replyEl.dataset.commentId = data.comment.id;
                                replyEl.innerHTML = `<div class="flex flex-col items-center shrink-0">${replyAvatar}</div><div class="min-w-0 flex-1 pb-3"><div class="flex flex-wrap items-baseline gap-x-2 gap-y-0.5"><span class="truncate text-sm font-semibold text-gray-900 dark:text-white/90">${escapeHtml(data.comment.author)}</span><span class="shrink-0 text-xs text-gray-400 dark:text-gray-500">${escapeHtml(data.comment.created_at)}</span></div><p class="mt-0.5 text-sm text-gray-700 dark:text-gray-300 break-words whitespace-pre-wrap">${escapeHtml(data.comment.content)}</p><div class="mt-1.5 flex items-center gap-3">${deleteHtml}</div></div>`;
                                repliesList.appendChild(replyEl);

                                const avatarCol = parentComment.querySelector(':scope > .flex.flex-col');
                                if (avatarCol && !avatarCol.querySelector('.w-px')) {
                                    const line = document.createElement('div');
                                    line.className = 'mt-1 w-px flex-1 bg-gray-200 dark:bg-gray-700';
                                    avatarCol.appendChild(line);
                                }
                            }
                            const countEls = document.querySelectorAll(`[data-comment-container="${postId}"] [data-comment-count]`);
                            const nextCount = parseInt((countEls[0]?.textContent || '0').replace(/\D/g, '') || 0) + 1;
                            countEls.forEach(el => { el.textContent = nextCount; });
                            input.value = '';
                            document.querySelector(`[data-reply-form="${parentId}"]`)?.classList.add('hidden');
                        } else if (data.error) $toast('error', data.error);
                        resetBtn();
                    })
                    .catch(() => { $toast('error', 'Something went wrong.'); resetBtn(); });
            };
            if (isAuthenticated) doSubmit();
            else showGuestModal({ type: 'comment', payload: { content }, callback: (name, email) => doSubmit(name, email) });
        }
    });

    // --- Comment submit (delegated for static + dynamic posts) ---
    document.addEventListener('click', (e) => {
        const btn = e.target.closest('[data-comment-submit]');
        if (!btn || btn.closest('[data-reply-form]')) return;
        const postId = parseInt(btn.dataset.postId);
        const commentSection = btn.closest('[data-comment-section]');
        const input = commentSection?.querySelector(`[data-comment-input="${postId}"]`) ?? document.querySelector(`[data-comment-input="${postId}"]`);
        const content = input?.value?.trim();
        if (!content) return;
        if (btn.disabled) return;
        btn.disabled = true;
        const origText = btn.textContent;
        btn.textContent = '...';
        const resetBtn = () => { btn.disabled = false; btn.textContent = origText; };
        const doSubmit = (guestName, guestEmail) => {
            const body = { content };
            if (guestName) body.guest_name = guestName;
            if (guestEmail) body.guest_email = guestEmail;
            fetch(`${baseUrl}/posts/${postId}/comments`, fetchOpts('POST', body))
                .then(r => r.json())
                .then(data => {
                    if (data.success && data.comment) {
                        const list = commentSection?.querySelector(`[data-comments-list="${postId}"]`) ?? document.querySelector(`[data-comments-list="${postId}"]`);
                        const empty = commentSection?.querySelector(`[data-comments-empty="${postId}"]`) ?? document.querySelector(`[data-comments-empty="${postId}"]`);
                        if (list) {
                            const commentAvatar = avatarHtml(data.comment.author_photo, data.comment.author, 'h-7 w-7 sm:h-8 sm:w-8', 'bg-brand-100 dark:bg-brand-500/25 text-brand-600 dark:text-brand-400 text-[11px] sm:text-xs font-semibold');
                            const deleteHtml = canEdit ? `<button type="button" data-delete-comment data-comment-id="${data.comment.id}" data-post-id="${postId}" class="text-xs font-medium text-gray-400 dark:text-gray-500 hover:text-red-500 dark:hover:text-red-400 transition">Delete</button>` : '';
                            const el = document.createElement('div');
                            el.className = 'relative flex gap-2 sm:gap-3';
                            el.dataset.commentId = data.comment.id;
                            el.innerHTML = `<div class="flex flex-col items-center shrink-0">${commentAvatar}</div><div class="min-w-0 flex-1 pb-3"><div class="flex flex-wrap items-baseline gap-x-2 gap-y-0.5"><span class="truncate text-sm font-semibold text-gray-900 dark:text-white/90">${escapeHtml(data.comment.author)}</span><span class="shrink-0 text-xs text-gray-400 dark:text-gray-500">${escapeHtml(data.comment.created_at)}</span></div><p class="mt-0.5 text-sm text-gray-700 dark:text-gray-300 break-words whitespace-pre-wrap">${escapeHtml(data.comment.content)}</p><div class="mt-1.5 flex items-center gap-3"><button type="button" data-reply-to data-comment-id="${data.comment.id}" data-post-id="${postId}" class="text-xs font-medium text-gray-500 dark:text-gray-400 hover:text-brand-500 dark:hover:text-brand-400 transition">Reply</button>${deleteHtml}</div><div data-reply-form="${data.comment.id}" class="hidden mt-2"><div class="flex flex-wrap items-center gap-2"><div class="flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-gray-200 dark:bg-gray-700 text-gray-500 dark:text-gray-400"><svg class="h-3 w-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h10a8 8 0 018 8v2M3 10l6 6m-6-6l6-6"/></svg></div><input type="text" data-reply-input="${data.comment.id}" placeholder="Write a reply..." class="h-9 min-w-0 flex-1 basis-40 rounded-full border border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-white/[0.03] px-3 text-sm placeholder:text-gray-400 focus:border-brand-300 focus:outline-none focus:ring-2 focus:ring-brand-500/20" /><button type="button" data-reply-submit data-comment-id="${data.comment.id}" data-post-id="${postId}" class="btn btn-primary btn-sm rounded-full shrink-0 active:scale-95">Reply</button></div></div></div>`;
                            list.appendChild(el);
                        }
                        if (empty) empty.classList.add('hidden');
                        const countEls = document.querySelectorAll(`[data-comment-container="${postId}"] [data-comment-count]`);
                        const nextCount = parseInt((countEls[0]?.textContent || '0').replace(/\D/g, '') || 0) + 1;
                        countEls.forEach(el => { el.textContent = nextCount; });
                        input.value = '';
                    } else if (data.error) $toast('error', data.error);
                    resetBtn();
                })
                .catch(() => { $toast('error', 'Something went wrong.'); resetBtn(); });
        };
        if (isAuthenticated) doSubmit();
        else showGuestModal({ type: 'comment', payload: { content }, callback: (name, email) => doSubmit(name, email) });
    });

    // --- Delete comment ---
    document.addEventListener('click', async (e) => {
        const btn = e.target.closest('[data-delete-comment]');
        if (!btn) return;
        e.stopPropagation();
        const commentId = parseInt(btn.dataset.commentId);
        const postId = parseInt(btn.dataset.postId);
        if (!await $confirm('This comment will be permanently removed.', { title: 'Delete this comment?', confirmText: 'Delete comment' })) return;
        btn.disabled = true;
        btn.textContent = '...';
        fetch(`${baseUrl}/comments/${commentId}`, fetchOpts('DELETE'))
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    const commentEl = btn.closest('[data-comment-id]');
                    const commentSection = commentEl?.closest('[data-comment-section]');
                    const deletedCount = data.deleted_count || 1;
                    commentEl?.remove();
                    const countEls = document.querySelectorAll(`[data-comment-container="${postId}"] [data-comment-count]`);
                    const nextCount = Math.max(0, parseInt((countEls[0]?.textContent || '0').replace(/\D/g, '') || 0) - deletedCount);
                    countEls.forEach(el => { el.textContent = nextCount; });
                    const list = commentSection?.querySelector(`[data-comments-list="${postId}"]`) ?? document.querySelector(`[data-comments-list="${postId}"]`);
                    if (list && list.children.length === 0) {
                        const empty = commentSection?.querySelector(`[data-comments-empty="${postId}"]`) ?? document.querySelector(`[data-comments-empty="${postId}"]`);
                        if (empty) empty.classList.remove('hidden');
                    }
                } else if (data.error) {
                    $toast('error', data.error);
                    btn.disabled = false;
                    btn.textContent = 'Delete';
                }
            })
            .catch(() => { $toast('error', 'Something went wrong.'); btn.disabled = false; btn.textContent = 'Delete'; });
    });

    document.addEventListener('click', async (e) => {
        const btn = e.target.closest('[data-delete-tribute-comment]');
        if (!btn) return;
        e.stopPropagation();
        const commentId = parseInt(btn.dataset.commentId, 10);
        const tributeId = parseInt(btn.dataset.tributeId, 10);
        if (!await $confirm('This comment will be permanently removed.', { title: 'Delete this comment?', confirmText: 'Delete comment' })) return;
        btn.disabled = true;
        const prevText = btn.textContent;
        btn.textContent = '...';
        fetch(`${baseUrl}/tribute-comments/${commentId}`, fetchOpts('DELETE'))
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    const commentEl = btn.closest('[data-tribute-comment-id]');
                    commentEl?.remove();
                    const deletedCount = data.deleted_count || 1;
                    bumpTributeCommentCount(tributeId, -deletedCount);
                    const list = document.querySelector(`[data-tribute-comments-list="${tributeId}"]`);
                    if (list && list.children.length === 0) {
                        document.querySelector(`[data-tribute-comments-empty="${tributeId}"]`)?.classList.remove('hidden');
                    }
                } else if (data.error) {
                    $toast('error', data.error);
                    btn.disabled = false;
                    btn.textContent = prevText;
                }
            })
            .catch(() => { $toast('error', 'Something went wrong.'); btn.disabled = false; btn.textContent = prevText; });
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

    // --- Scroll to tribute or chapter on deep link load ---
    if (scrollToTributeId) {
        switchToTab('tributes');
        setTimeout(() => {
            const el = document.getElementById('tribute-' + scrollToTributeId);
            el?.scrollIntoView({ behavior: 'smooth', block: 'center' });
        }, 200);
    } else if (scrollToChapterId) {
        switchToTab('life');
        setTimeout(() => {
            const el = document.getElementById('chapter-' + scrollToChapterId);
            el?.scrollIntoView({ behavior: 'smooth', block: 'center' });
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
            document.querySelectorAll('[data-share-dropdown], [data-share-dropdown-tribute]').forEach(d => d.classList.add('hidden'));
        });
    });

    // Refresh stats shortly after load so the view the visitor just caused is reflected
    setTimeout(() => {
        fetch(`${baseUrl}/stats`, { headers: { 'Accept': 'application/json' } })
            .then(r => r.json())
            .then(applyStats)
            .catch(() => {});
    }, 1500);
});
