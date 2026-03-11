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

    // --- Tab switching ---
    function switchToTab(panelId) {
        const btn = document.querySelector(`.memorial-tab-btn[data-tab-panel="${panelId}"]`);
        if (btn) btn.click();
    }
    document.querySelectorAll('.memorial-tab-btn').forEach(btn => {
        btn.addEventListener('click', () => {
            const panelId = btn.dataset.tabPanel;
            document.querySelectorAll('.memorial-tab-btn').forEach(b => {
                b.classList.remove('text-brand-600', 'dark:text-brand-400', 'border-brand-500', 'bg-brand-50/50', 'dark:bg-brand-500/10');
                b.classList.add('text-gray-600', 'dark:text-gray-400', 'border-transparent');
            });
            btn.classList.add('text-brand-600', 'dark:text-brand-400', 'border-brand-500', 'bg-brand-50/50', 'dark:bg-brand-500/10');
            btn.classList.remove('text-gray-600', 'dark:text-gray-400', 'border-transparent');

            document.querySelectorAll('.memorial-tab-panel').forEach(p => p.classList.add('hidden'));
            const panel = document.getElementById('tab-' + panelId);
            if (panel) panel.classList.remove('hidden');

            document.querySelectorAll('.memorial-tab').forEach(a => {
                a.classList.remove('text-brand-600', 'dark:text-brand-400');
                a.classList.add('text-gray-600', 'dark:text-gray-400');
                if (a.dataset.tab === panelId) {
                    a.classList.add('text-brand-600', 'dark:text-brand-400');
                    a.classList.remove('text-gray-600', 'dark:text-gray-400');
                }
            });
        });
    });
    document.querySelectorAll('.memorial-tab').forEach(a => {
        a.addEventListener('click', (e) => {
            e.preventDefault();
            switchToTab(a.dataset.tab);
        });
    });

    // --- Chapter filter ---
    document.querySelectorAll('.chapter-filter').forEach(btn => {
        btn.addEventListener('click', () => {
            const chapterId = btn.dataset.chapter || '';
            document.querySelectorAll('.chapter-filter').forEach(b => b.classList.remove('bg-brand-50', 'dark:bg-brand-500/20', 'text-brand-600', 'dark:text-brand-400'));
            document.querySelectorAll('.chapter-filter').forEach(b => b.classList.add('text-gray-600', 'dark:text-gray-400'));
            btn.classList.add('bg-brand-50', 'dark:bg-brand-500/20', 'text-brand-600', 'dark:text-brand-400');
            btn.classList.remove('text-gray-600', 'dark:text-gray-400');

            document.querySelectorAll('#life-feed article').forEach(article => {
                const artChapter = article.dataset.chapterId || '';
                article.style.display = (chapterId === '' || artChapter === chapterId) ? '' : 'none';
            });
        });
    });

    // --- Profile photo upload ---
    if (canEdit) {
        document.getElementById('profile-photo-input')?.addEventListener('change', (e) => {
            const file = e.target.files?.[0];
            if (!file) return;
            const fd = new FormData();
            fd.append('photo', file);
            fd.append('_token', csrf);
            fetch(`${baseUrl}/profile-photo`, formDataOpts(fd))
                .then(r => r.json())
                .then(data => {
                    if (data.success) {
                        const container = document.querySelector('.relative.group.mb-4 > div');
                        if (container) {
                            container.innerHTML = `<img src="${data.url}" alt="" class="h-full w-full object-cover" />`;
                        }
                    }
                });
            e.target.value = '';
        });
    }

    // --- Gallery upload ---
    if (canUpload) {
        document.getElementById('gallery-upload')?.addEventListener('change', (e) => {
            const file = e.target.files?.[0];
            if (!file) return;
            const fd = new FormData();
            fd.append('file', file);
            fd.append('_token', csrf);
            fetch(`${baseUrl}/gallery`, formDataOpts(fd))
                .then(r => r.json())
                .then(data => {
                    if (data.success && data.media) {
                        const grid = document.getElementById('gallery-grid');
                        if (grid) {
                            const isVideo = data.media.type === 'video';
                            const el = isVideo
                                ? `<div class="aspect-square overflow-hidden rounded-lg bg-gray-200 dark:bg-gray-700"><video src="${data.media.url}" controls class="h-full w-full object-cover"></video></div>`
                                : `<a href="${data.media.url}" target="_blank" class="block aspect-square overflow-hidden rounded-lg bg-gray-200 dark:bg-gray-700"><img src="${data.media.url}" alt="" class="h-full w-full object-cover" /></a>`;
                            grid.insertAdjacentHTML('beforeend', el);
                        }
                    } else if (data.error) {
                        alert(data.error);
                    }
                });
            e.target.value = '';
        });
    }

    // --- Quill editors ---
    let chapterQuill, tributeQuill, biographyQuill;
    if (typeof Quill !== 'undefined') {
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
        if (document.getElementById('chapter-editor')) {
            chapterQuill = new Quill('#chapter-editor', quillOpts);
            chapterQuill.on('text-change', () => {
                const el = document.getElementById('chapter-content');
                if (el) el.value = chapterQuill.root.innerHTML;
            });
        }
        if (document.getElementById('tribute-editor')) {
            tributeQuill = new Quill('#tribute-editor', quillOpts);
            tributeQuill.on('text-change', () => {
                const el = document.getElementById('tribute-note-message');
                if (el) el.value = tributeQuill.root.innerHTML;
            });
        }
        if (document.getElementById('biography-editor')) {
            biographyQuill = new Quill('#biography-editor', quillOpts);
            biographyQuill.on('text-change', () => {
                const el = document.getElementById('biography-content');
                if (el) el.value = biographyQuill.root.innerHTML;
            });
        }
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
            setTimeout(() => {
                const titleInput = addStoryForm?.querySelector('input[name="title"]');
                const editor = document.querySelector('#chapter-editor .ql-editor');
                if (titleInput) titleInput.focus();
                else if (editor) editor.focus();
            }, 300);
        }, 150);
    });

    if (addStoryForm) {
        cancelStoryBtn?.addEventListener('click', () => {
            if (chapterQuill) chapterQuill.setText('');
            tributePostForm?.reset();
        });

        tributePostForm?.addEventListener('submit', (e) => {
            e.preventDefault();
            const form = e.target;
            const fd = new FormData();
            fd.append('title', form.title?.value || '');
            fd.append('content', chapterQuill ? chapterQuill.root.innerHTML : (form.content?.value || ''));
            fd.append('_token', csrf);
            if (!isAuthenticated) {
                const guestName = document.getElementById('chapter-guest-name')?.value?.trim();
                const guestEmail = document.getElementById('chapter-guest-email')?.value?.trim();
                if (!guestName || !guestEmail) {
                    alert('Please enter your name and email to add your chapter.');
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
            fetch(`${baseUrl}/tribute-post`, formDataOpts(fd))
                .then(r => r.json())
                .then(data => {
                    if (data.success && data.post) {
                        const feed = document.getElementById('life-feed');
                        if (feed) {
                            const p = data.post;
                            const mediaHtml = (p.media || []).map(m => {
                                if (m.type === 'photo') return `<img src="${m.url}" alt="" class="max-w-full rounded-lg mt-2" />`;
                                if (m.type === 'video') return `<video src="${m.url}" controls class="max-w-full rounded-lg mt-2"></video>`;
                                if (m.type === 'music') return `<audio src="${m.url}" controls class="w-full mt-2"></audio>`;
                                return '';
                            }).join('');
                            const article = document.createElement('article');
                            article.id = 'chapter-' + p.id;
                            article.className = 'relative overflow-visible rounded-xl border border-gray-200 dark:border-gray-800 bg-white dark:bg-white/[0.03]';
                            article.dataset.postId = p.id;
                            article.dataset.chapterId = '';
                            const initial = (p.author || '?').charAt(0).toUpperCase();
                            article.innerHTML = `
                                <div class="p-4">
                                    <div class="flex items-center gap-3">
                                        <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-brand-100 dark:bg-brand-500/30 text-brand-600 dark:text-brand-400 text-sm font-semibold">${escapeHtml(initial)}</div>
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
                                    <div class="relative flex items-center gap-1" data-comment-container="${p.id}">
                                        <button type="button" data-comment-toggle data-post-id="${p.id}" class="inline-flex items-center gap-1.5 text-gray-600 dark:text-gray-400 hover:text-gray-800 dark:hover:text-gray-200">
                                            <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"/></svg>
                                            <span data-post-id="${p.id}" data-comment-count class="text-sm text-gray-600 dark:text-gray-400">0</span>
                                        </button>
                                        <div data-comment-dropdown="${p.id}" class="absolute left-0 top-full z-[9999] mt-1 hidden w-full min-w-[320px] max-w-md rounded-lg border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 shadow-xl">
                                            <div class="border-b border-gray-100 dark:border-gray-700 p-3">
                                                <p class="text-xs font-medium text-gray-500 dark:text-gray-400 mb-2">Add your comment</p>
                                                <div class="flex gap-2">
                                                    <input type="text" data-comment-input="${p.id}" placeholder="Write a comment..." class="flex-1 rounded-lg border border-gray-300 dark:border-gray-600 px-3 py-2 text-sm" />
                                                    <button type="button" data-comment-submit data-post-id="${p.id}" class="rounded-lg bg-brand-500 px-4 py-2 text-sm font-medium text-white hover:bg-brand-600">Post</button>
                                                </div>
                                            </div>
                                            <div class="max-h-48 overflow-y-auto p-3" data-comments-list="${p.id}"></div>
                                            <p data-comments-empty="${p.id}" class="px-3 py-4 text-center text-sm text-gray-500">No comments yet. Add a comment.</p>
                                        </div>
                                    </div>
                                    <div class="relative ml-auto" data-share-container="${p.id}">
                                        <button type="button" data-share-toggle data-share-url="${(p.share_id ? `${window.location.origin}/${memorialSlug}/chapter/${p.share_id}` : `${window.location.origin}/${memorialSlug}/chapter/${p.id}`)}" data-post-id="${p.id}" class="inline-flex items-center gap-1.5 text-gray-600 dark:text-gray-400 hover:text-gray-800 dark:hover:text-gray-200">
                                            <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.684 13.342C8.886 12.938 9 12.482 9 12c0-.482-.114-.938-.316-1.342m0 2.684a3 3 0 110-2.684m0 2.684l6.632 3.316m-6.632-6l6.632-3.316m0 0a3 3 0 105.367-2.684 3 3 0 00-5.367 2.684zm0 9.316a3 3 0 105.368 2.684 3 3 0 00-5.368-2.684z"/></svg>
                                            Share
                                        </button>
                                        <div data-share-dropdown="${p.id}" class="absolute right-0 top-full z-[9999] mt-1 hidden w-48 rounded-lg border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 shadow-xl py-1">
                                            <a href="#" data-share="whatsapp" data-share-url="${p.share_id ? `${window.location.origin}/${memorialSlug}/chapter/${p.share_id}` : `${window.location.origin}/${memorialSlug}/chapter/${p.id}`}" class="flex items-center gap-2 px-3 py-2 text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700">WhatsApp</a>
                                            <a href="#" data-share="facebook" data-share-url="${p.share_id ? `${window.location.origin}/${memorialSlug}/chapter/${p.share_id}` : `${window.location.origin}/${memorialSlug}/chapter/${p.id}`}" class="flex items-center gap-2 px-3 py-2 text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700">Facebook</a>
                                            <a href="#" data-share="linkedin" data-share-url="${p.share_id ? `${window.location.origin}/${memorialSlug}/chapter/${p.share_id}` : `${window.location.origin}/${memorialSlug}/chapter/${p.id}`}" class="flex items-center gap-2 px-3 py-2 text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700">LinkedIn</a>
                                            <button type="button" data-share="copy" data-share-url="${p.share_id ? `${window.location.origin}/${memorialSlug}/chapter/${p.share_id}` : `${window.location.origin}/${memorialSlug}/chapter/${p.id}`}" class="flex w-full items-center gap-2 px-3 py-2 text-left text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700">Link</button>
                                        </div>
                                    </div>
                                </div>
                            `;
                            feed.prepend(article);
                            article.querySelector('[data-reaction-btn]')?.addEventListener('click', function() {
                                const payload = { reactionable_type: 'post', reactionable_id: p.id, type: 'like' };
                                const doR = (name, email) => {
                                    fetch(`${baseUrl}/reaction`, fetchOpts('POST', { ...payload, guest_name: name, guest_email: email }))
                                        .then(r => r.json())
                                        .then(d => { if (d.success) { const el = article.querySelector(`[data-reaction-container="${p.id}"] [data-reaction-count]`); if (el) el.textContent = d.count; } });
                                };
                                isAuthenticated ? doR() : showGuestModal({ type: 'reaction', payload, callback: doR });
                            });
                            article.querySelector('[data-comment-toggle]')?.addEventListener('click', () => {
                                article.querySelector(`[data-post-comments="${p.id}"]`)?.classList.toggle('hidden');
                            });
                            article.querySelector('[data-comment-submit]')?.addEventListener('click', () => {
                                const input = article.querySelector(`[data-comment-input="${p.id}"]`);
                                const content = input?.value?.trim();
                                if (!content) return;
                                const doSubmit = (guestName, guestEmail) => {
                                    const body = { content };
                                    if (guestName) body.guest_name = guestName;
                                    if (guestEmail) body.guest_email = guestEmail;
                                    fetch(`${baseUrl}/posts/${p.id}/comments`, fetchOpts('POST', body))
                                        .then(r => r.json())
                                        .then(data => {
                                            if (data.success && data.comment) {
                                                const list = article.querySelector(`[data-comments-list="${p.id}"]`);
                                                if (list) {
                                                    const div = document.createElement('div');
                                                    div.className = 'rounded-lg bg-gray-50 dark:bg-white/[0.02] px-3 py-2';
                                                    div.innerHTML = `<p class="text-sm font-medium text-gray-900 dark:text-white/90">${escapeHtml(data.comment.author)}</p><p class="text-sm text-gray-700 dark:text-gray-300">${escapeHtml(data.comment.content)}</p><p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">${escapeHtml(data.comment.created_at)}</p>`;
                                                    list.appendChild(div);
                                                }
                                                const countEl = article.querySelector(`[data-comment-container="${p.id}"] [data-comment-count]`);
                                                if (countEl) countEl.textContent = parseInt(countEl.textContent || 0) + 1;
                                                input.value = '';
                                            }
                                        });
                                };
                                isAuthenticated ? doSubmit() : showGuestModal({ type: 'comment', payload: { content }, callback: (name, email) => doSubmit(name, email) });
                            });
                        }
                        if (chapterQuill) chapterQuill.setText('');
                        form.reset();
                    } else if (data.error) {
                        alert(data.error);
                    }
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
                if (section === 'biography' && biographyQuill) {
                    // Use display content as source (what's shown on page) - most reliable
                    let initial = '';
                    const placeholder = 'Add biography...';
                    if (displayEl.textContent.trim() !== placeholder) {
                        initial = displayEl.innerHTML.trim();
                    }
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
                        }
                    })
                    .catch(() => {})
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

    window.showGuestModal = (action) => {
        pendingAction = action;
        guestModal?.classList.remove('hidden');
    };

    window.hideGuestModal = () => {
        guestModal?.classList.add('hidden');
        pendingAction = null;
    };

    guestForm?.addEventListener('submit', (e) => {
        e.preventDefault();
        const name = document.getElementById('guest-name')?.value?.trim();
        const email = document.getElementById('guest-email')?.value?.trim();
        if (!name || !email) return;

        if (pendingAction?.type === 'tribute') {
            submitTribute(pendingAction.payload, name, email);
            const msgEl = document.getElementById('tribute-note-message');
            if (msgEl) msgEl.value = '';
        } else if (pendingAction?.type === 'reaction') {
            pendingAction.callback?.(name, email) ?? submitReaction(pendingAction.payload, name, email);
        } else if (pendingAction?.type === 'comment') {
            pendingAction.callback?.(name, email);
        }
        hideGuestModal();
    });

    // --- Tribute (flower, candle, note) ---
    function submitTribute(payload, guestName, guestEmail) {
        const body = { ...payload };
        if (guestName) body.guest_name = guestName;
        if (guestEmail) body.guest_email = guestEmail;
        const url = tributeUrl || `${baseUrl}/tribute`;
        fetch(url, fetchOpts('POST', body))
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
                    appendTribute(data.tribute);
                    updateTributeCount();
                } else if (data.requires_login) {
                    hideGuestModal();
                    alert(data.error + ' You can sign in at: ' + window.location.origin + '/login/code');
                } else if (data.error) {
                    alert(data.error);
                }
            })
            .catch(err => {
                console.error('Tribute error:', err);
                alert(err.message || 'Could not submit tribute. Please try again.');
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
        setTimeout(() => {
            document.getElementById('tribute-form-anchor')?.scrollIntoView({ behavior: 'smooth', block: 'start' });
            const editor = document.querySelector('#tribute-editor .ql-editor');
            if (editor) editor.focus();
        }, 100);
    });

    document.querySelectorAll('[data-tribute-btn]').forEach(btn => {
        btn.addEventListener('click', (e) => {
            e.preventDefault();
            const type = btn.dataset.tributeBtn;
            if (type === 'note') {
                document.querySelectorAll('.memorial-tab-btn').forEach(b => {
                    if (b.dataset.tabPanel === 'tributes') b.click();
                });
                return;
            }

            if (isAuthenticated) {
                submitTribute({ type });
            } else {
                showGuestModal({ type: 'tribute', payload: { type } });
            }
        });
    });

    document.getElementById('tribute-note-submit')?.addEventListener('click', () => {
        const name = document.getElementById('tribute-note-name')?.value?.trim();
        const email = document.getElementById('tribute-note-email')?.value?.trim();
        const typeEl = document.querySelector('input[name="tribute-type"]:checked');
        const type = typeEl?.value || 'note';
        const message = tributeQuill ? tributeQuill.root.innerHTML : (document.getElementById('tribute-note-message')?.value?.trim() || '');
        if (!message || message === '<p><br></p>') return;

        if (isAuthenticated) {
            submitTribute({ type, message });
        } else if (name && email) {
            submitTribute({ type, message }, name, email);
        } else {
            showGuestModal({ type: 'tribute', payload: { type, message } });
            return;
        }
        if (tributeQuill) tributeQuill.setText('');
    });

    function appendTribute(t) {
        const container = document.querySelector('[data-tributes-list]');
        if (!container) return;
        document.querySelectorAll('.memorial-tab-btn').forEach(b => {
            if (b.dataset.tabPanel === 'tributes') {
                b.click();
            }
        });
        const div = document.createElement('div');
        div.className = 'border-b border-gray-100 dark:border-gray-800 pb-4 last:border-0 last:pb-0';
        div.id = 'tribute-' + (t.id || 'new');
        const typeClass = t.type === 'flower' ? 'bg-pink-100 dark:bg-pink-500/20 text-pink-800 dark:text-pink-400' : t.type === 'candle' ? 'bg-amber-100 dark:bg-amber-500/20 text-amber-800 dark:text-amber-400' : 'bg-gray-100 dark:bg-gray-500/20 text-gray-800 dark:text-gray-300';
        const shareUrl = t.share_id ? `${window.location.origin}/${memorialSlug}/tribute/${t.share_id}` : `${window.location.origin}/${memorialSlug}/tribute/${t.id || 'new'}`;
        const timeEl = t.created_at_iso ? `<span class="text-xs text-gray-500 dark:text-gray-400 time-ago" data-created-at="${t.created_at_iso}">${t.created_at}</span>` : `<span class="text-xs text-gray-500 dark:text-gray-400">${t.created_at}</span>`;
        div.innerHTML = `
            <div class="flex items-center gap-2">
                <span class="font-medium text-gray-900 dark:text-white/90">${escapeHtml(t.author)}</span>
                ${timeEl}
                <span class="inline-flex rounded-full px-2 py-0.5 text-xs font-medium ${typeClass}">${escapeHtml(t.type)}</span>
                <div class="relative ml-auto" data-share-container data-tribute-id="${t.id || 'new'}">
                    <button type="button" data-share-toggle data-share-url="${shareUrl}" class="inline-flex items-center gap-1.5 text-gray-600 dark:text-gray-400 hover:text-gray-800 dark:hover:text-gray-200">
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.684 13.342C8.886 12.938 9 12.482 9 12c0-.482-.114-.938-.316-1.342m0 2.684a3 3 0 110-2.684m0 2.684l6.632 3.316m-6.632-6l6.632-3.316m0 0a3 3 0 105.367-2.684 3 3 0 00-5.367 2.684zm0 9.316a3 3 0 105.368 2.684 3 3 0 00-5.368-2.684z"/></svg>
                        Share
                    </button>
                    <div data-share-dropdown-tribute class="absolute right-0 top-full z-[9999] mt-1 hidden w-48 rounded-lg border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 shadow-xl py-1">
                        <a href="#" data-share="whatsapp" data-share-url="${shareUrl}" class="flex items-center gap-2 px-3 py-2 text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700">WhatsApp</a>
                        <a href="#" data-share="facebook" data-share-url="${shareUrl}" class="flex items-center gap-2 px-3 py-2 text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700">Facebook</a>
                        <a href="#" data-share="linkedin" data-share-url="${shareUrl}" class="flex items-center gap-2 px-3 py-2 text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700">LinkedIn</a>
                        <button type="button" data-share="copy" data-share-url="${shareUrl}" class="flex w-full items-center gap-2 px-3 py-2 text-left text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700">Link</button>
                    </div>
                </div>
            </div>
            ${t.message ? `<div class="mt-1 text-gray-700 dark:text-gray-300 prose prose-sm max-w-none">${t.message}</div>` : ''}
        `;
        container.prepend(div);
        const emptyEl = document.querySelector('[data-tributes-empty]');
        if (emptyEl) emptyEl.classList.add('hidden');
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
                            const countEl = document.querySelector(`[data-reaction-container="${payload.reactionable_id}"] [data-reaction-count]`);
                            if (countEl) countEl.textContent = data.count;
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
                            const countEl = document.querySelector(`[data-reaction-container="${payload.reactionable_id}"] [data-reaction-count]`);
                            if (countEl) countEl.textContent = data.count;
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

    // --- Comment toggle (dropdown) ---
    document.querySelectorAll('[data-comment-toggle]').forEach(btn => {
        btn.addEventListener('click', (e) => {
            e.stopPropagation();
            const postId = btn.dataset.postId;
            const dropdown = document.querySelector(`[data-comment-dropdown="${postId}"]`);
            document.querySelectorAll('[data-comment-dropdown]').forEach(d => { if (d !== dropdown) d.classList.add('hidden'); });
            dropdown?.classList.toggle('hidden');
        });
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
            document.querySelectorAll('[data-comment-dropdown]').forEach(d => d.classList.add('hidden'));
            dropdown?.classList.toggle('hidden');
        });
    });

    // --- Tribute comment submit ---
    document.querySelectorAll('[data-tribute-comment-submit]').forEach(btn => {
        btn.addEventListener('click', () => {
            const tributeId = parseInt(btn.dataset.tributeId);
            const input = document.querySelector(`[data-tribute-comment-input="${tributeId}"]`);
            const content = input?.value?.trim();
            if (!content) return;

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
                                const div = document.createElement('div');
                                div.className = 'mb-3 last:mb-0 rounded-lg bg-gray-50 dark:bg-white/[0.02] px-3 py-2';
                                div.innerHTML = `<p class="text-sm font-medium text-gray-900 dark:text-white/90">${escapeHtml(data.comment.author)}</p><p class="text-sm text-gray-700 dark:text-gray-300">${escapeHtml(data.comment.content)}</p><p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">${escapeHtml(data.comment.created_at)}</p>`;
                                list.appendChild(div);
                            }
                            if (empty) empty.classList.add('hidden');
                            const countEl = document.querySelector(`[data-tribute-comment-container="${tributeId}"] [data-tribute-comment-count]`);
                            if (countEl) countEl.textContent = parseInt((countEl.textContent || '0').replace(/\D/g, '') || 0) + 1;
                            input.value = '';
                        } else if (data.error) {
                            alert(data.error);
                        }
                    });
            };

            if (isAuthenticated) {
                doSubmit();
            } else {
                showGuestModal({ type: 'comment', payload: { content }, callback: (name, email) => doSubmit(name, email) });
            }
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
            const input = document.querySelector(`[data-tribute-reply-input="${parentId}"]`);
            const content = input?.value?.trim();
            if (!content) return;

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
                            if (repliesList) {
                                const div = document.createElement('div');
                                div.className = 'mb-3 last:mb-0 rounded-lg bg-gray-50 dark:bg-white/[0.02] px-3 py-2 ml-4 border-l-2 border-gray-200 dark:border-gray-700';
                                div.innerHTML = `<p class="text-sm font-medium text-gray-900 dark:text-white/90">${escapeHtml(data.comment.author)}</p><p class="text-sm text-gray-700 dark:text-gray-300">${escapeHtml(data.comment.content)}</p><p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">${escapeHtml(data.comment.created_at)}</p>`;
                                repliesList.appendChild(div);
                            }
                            if (!repliesList) {
                                const parentComment = document.querySelector(`[data-tribute-comment-id="${parentId}"]`);
                                if (parentComment) {
                                    let list = parentComment.querySelector(`[data-tribute-replies-list="${parentId}"]`);
                                    if (!list) {
                                        list = document.createElement('div');
                                        list.className = 'mt-2 space-y-2';
                                        list.dataset.tributeRepliesList = parentId;
                                        parentComment.appendChild(list);
                                    }
                                    const div = document.createElement('div');
                                    div.className = 'mb-3 last:mb-0 rounded-lg bg-gray-50 dark:bg-white/[0.02] px-3 py-2 ml-4 border-l-2 border-gray-200 dark:border-gray-700';
                                    div.innerHTML = `<p class="text-sm font-medium text-gray-900 dark:text-white/90">${escapeHtml(data.comment.author)}</p><p class="text-sm text-gray-700 dark:text-gray-300">${escapeHtml(data.comment.content)}</p><p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">${escapeHtml(data.comment.created_at)}</p>`;
                                    list.appendChild(div);
                                }
                            }
                            const countEl = document.querySelector(`[data-tribute-comment-container="${tributeId}"] [data-tribute-comment-count]`);
                            if (countEl) countEl.textContent = parseInt((countEl.textContent || '0').replace(/\D/g, '') || 0) + 1;
                            input.value = '';
                            replyForm?.classList.add('hidden');
                        } else if (data.error) {
                            alert(data.error);
                        }
                    });
            };

            if (isAuthenticated) {
                doSubmit();
            } else {
                showGuestModal({ type: 'comment', payload: { content }, callback: (name, email) => doSubmit(name, email) });
            }
        }
    });

    // --- Click outside to close dropdowns ---
    document.addEventListener('click', (e) => {
        if (e.target.closest('[data-comment-container], [data-share-container], [data-tribute-comment-container], #invite-share-btn, #invite-share-dropdown')) return;
        document.querySelectorAll('[data-comment-dropdown], [data-share-dropdown], [data-share-dropdown-tribute], [data-tribute-comment-dropdown]').forEach(d => d.classList.add('hidden'));
        document.getElementById('invite-share-dropdown')?.classList.add('hidden');
    });

    // --- Reply toggle and submit ---
    document.addEventListener('click', (e) => {
        const replyBtn = e.target.closest('[data-reply-to]');
        if (replyBtn) {
            e.stopPropagation();
            const commentId = replyBtn.dataset.commentId;
            const form = document.querySelector(`[data-reply-form="${commentId}"]`);
            document.querySelectorAll('[data-reply-form]').forEach(f => { if (f !== form) f.classList.add('hidden'); });
            form?.classList.toggle('hidden');
            const input = document.querySelector(`[data-reply-input="${commentId}"]`);
            if (form?.classList.contains('hidden') === false && input) input.focus();
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

            const doSubmit = (guestName, guestEmail) => {
                const body = { content, parent_id: parentId };
                if (guestName) body.guest_name = guestName;
                if (guestEmail) body.guest_email = guestEmail;
                fetch(`${baseUrl}/posts/${postId}/comments`, fetchOpts('POST', body))
                    .then(r => r.json())
                    .then(data => {
                        if (data.success && data.comment) {
                            const repliesList = document.querySelector(`[data-replies-list="${parentId}"]`);
                            const replyForm = document.querySelector(`[data-reply-form="${parentId}"]`);
                            if (repliesList) {
                                const div = document.createElement('div');
                                div.className = 'mb-3 last:mb-0 rounded-lg bg-gray-50 dark:bg-white/[0.02] px-3 py-2 ml-4 border-l-2 border-gray-200 dark:border-gray-700';
                                div.innerHTML = `<p class="text-sm font-medium text-gray-900 dark:text-white/90">${escapeHtml(data.comment.author)}</p><p class="text-sm text-gray-700 dark:text-gray-300">${escapeHtml(data.comment.content)}</p><p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">${escapeHtml(data.comment.created_at)}</p>`;
                                repliesList.appendChild(div);
                            }
                            if (!repliesList) {
                                const parentComment = document.querySelector(`[data-comment-id="${parentId}"]`);
                                if (parentComment) {
                                    let list = parentComment.querySelector(`[data-replies-list="${parentId}"]`);
                                    if (!list) {
                                        list = document.createElement('div');
                                        list.className = 'mt-2 space-y-2';
                                        list.dataset.repliesList = parentId;
                                        parentComment.appendChild(list);
                                    }
                                    const div = document.createElement('div');
                                    div.className = 'mb-3 last:mb-0 rounded-lg bg-gray-50 dark:bg-white/[0.02] px-3 py-2 ml-4 border-l-2 border-gray-200 dark:border-gray-700';
                                    div.innerHTML = `<p class="text-sm font-medium text-gray-900 dark:text-white/90">${escapeHtml(data.comment.author)}</p><p class="text-sm text-gray-700 dark:text-gray-300">${escapeHtml(data.comment.content)}</p><p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">${escapeHtml(data.comment.created_at)}</p>`;
                                    list.appendChild(div);
                                }
                            }
                            const countEl = document.querySelector(`[data-comment-container="${postId}"] [data-comment-count]`);
                            if (countEl) countEl.textContent = parseInt((countEl.textContent || '0').replace(/\D/g, '') || 0) + 1;
                            input.value = '';
                            replyForm?.classList.add('hidden');
                        } else if (data.error) {
                            alert(data.error);
                        }
                    });
            };

            if (isAuthenticated) {
                doSubmit();
            } else {
                showGuestModal({ type: 'comment', payload: { content }, callback: (name, email) => doSubmit(name, email) });
            }
        }
    });

    // --- Comment submit ---
    document.querySelectorAll('[data-comment-submit]').forEach(btn => {
        btn.addEventListener('click', () => {
            const postId = parseInt(btn.dataset.postId);
            const input = document.querySelector(`[data-comment-input="${postId}"]`);
            const content = input?.value?.trim();
            if (!content) return;

            const doSubmit = (guestName, guestEmail) => {
                const body = { content };
                if (guestName) body.guest_name = guestName;
                if (guestEmail) body.guest_email = guestEmail;
                fetch(`${baseUrl}/posts/${postId}/comments`, fetchOpts('POST', body))
                    .then(r => r.json())
                    .then(data => {
                        if (data.success && data.comment) {
                            const list = document.querySelector(`[data-comments-list="${postId}"]`);
                            const empty = document.querySelector(`[data-comments-empty="${postId}"]`);
                            if (list) {
                                const div = document.createElement('div');
                                div.className = 'mb-3 last:mb-0 rounded-lg bg-gray-50 dark:bg-white/[0.02] px-3 py-2';
                                div.innerHTML = `
                                    <p class="text-sm font-medium text-gray-900 dark:text-white/90">${escapeHtml(data.comment.author)}</p>
                                    <p class="text-sm text-gray-700 dark:text-gray-300">${escapeHtml(data.comment.content)}</p>
                                    <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">${escapeHtml(data.comment.created_at)}</p>
                                `;
                                list.appendChild(div);
                            }
                            if (empty) empty.classList.add('hidden');
                            const countEl = document.querySelector(`[data-comment-container="${postId}"] [data-comment-count]`);
                            if (countEl) countEl.textContent = parseInt((countEl.textContent || '0').replace(/\D/g, '') || 0) + 1;
                            input.value = '';
                        } else if (data.error) {
                            alert(data.error);
                        }
                    });
            };

            if (isAuthenticated) {
                doSubmit();
            } else {
                showGuestModal({ type: 'comment', payload: { content }, callback: (name, email) => doSubmit(name, email) });
            }
        });
    });

    function escapeHtml(s) {
        if (!s) return '';
        const div = document.createElement('div');
        div.textContent = s;
        return div.innerHTML;
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
    function trackShare(shareType) {
        fetch(`${baseUrl}/track-share`, fetchOpts('POST', { share_type: shareType })).catch(() => {});
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
});
