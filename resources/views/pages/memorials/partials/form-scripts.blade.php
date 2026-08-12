{{--
    The Alpine factory backing every repeater in the shared form partials, plus the Quill
    biography editor. Included once per page that renders the form — both /memorials/create
    and the reseller intake screen use exactly this, so a fix to a repeater lands on both.
--}}
@push('head')
<link href="https://cdn.quilljs.com/1.3.7/quill.snow.css" rel="stylesheet">
<script src="https://cdn.quilljs.com/1.3.7/quill.min.js"></script>
@endpush

@push('scripts')
    <script>
        function memorialCreateForm() {
            return {
                addCompanyRow(container) {
                    const idx = container.querySelectorAll('.company-row').length;
                    const div = document.createElement('div');
                    div.className = 'flex gap-2 items-center company-row';
                    div.innerHTML = `<input type="text" name="companies[${idx}][company_name]" placeholder="e.g. Business or organisation name" class="shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 h-11 flex-1 rounded-lg border border-gray-300 dark:border-gray-700 bg-transparent px-4 py-2.5 text-sm text-gray-800 dark:text-white/90 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden" />
                        <button type="button" class="text-red-500 hover:text-red-700 p-2" title="Remove"><svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg></button>`;
                    div.querySelector('button').addEventListener('click', () => div.remove());
                    container.insertBefore(div, container.lastElementChild);
                },
                addCoFounderRow(container) {
                    const idx = container.querySelectorAll('.cofounder-row').length;
                    const div = document.createElement('div');
                    div.className = 'flex gap-2 items-center cofounder-row';
                    div.innerHTML = `<input type="text" name="co_founders[${idx}][name]" placeholder="e.g. Business partner name" class="shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 h-11 flex-1 rounded-lg border border-gray-300 dark:border-gray-700 bg-transparent px-4 py-2.5 text-sm text-gray-800 dark:text-white/90 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden" />
                        <button type="button" class="text-red-500 hover:text-red-700 p-2" title="Remove"><svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg></button>`;
                    div.querySelector('button').addEventListener('click', () => div.remove());
                    container.insertBefore(div, container.lastElementChild);
                },
                addChildRow(container) {
                    const idx = container.querySelectorAll('.child-row').length;
                    const div = document.createElement('div');
                    div.className = 'flex flex-wrap gap-2 items-center child-row';
                    div.innerHTML = `<input type="text" name="children[${idx}][child_name]" placeholder="Name" class="shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 h-11 min-w-0 flex-1 basis-40 rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm" />
                        <input type="number" name="children[${idx}][birth_year]" placeholder="Year" min="1900" max="2100" class="shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 h-11 w-24 rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm" />
                        <button type="button" class="shrink-0 text-red-500 hover:text-red-700 p-2" title="Remove"><svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg></button>`;
                    div.querySelector('button').addEventListener('click', () => div.remove());
                    container.insertBefore(div, container.lastElementChild);
                },
                addSpouseRow(container) {
                    const idx = container.querySelectorAll('.spouse-row').length;
                    const div = document.createElement('div');
                    div.className = 'spouse-row space-y-2 rounded-lg border border-gray-100 bg-gray-50/50 p-2.5 dark:border-gray-700 dark:bg-white/[0.02]';
                    div.innerHTML = `<input type="text" name="spouses[${idx}][spouse_name]" placeholder="Name" class="shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 h-11 w-full rounded-lg border border-gray-300 bg-white px-4 py-2.5 text-sm dark:bg-transparent" />
                        <div class="flex gap-2 items-center"><input type="number" name="spouses[${idx}][marriage_start_year]" placeholder="Start year" min="1900" max="2100" class="shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 h-11 min-w-0 flex-1 rounded-lg border border-gray-300 bg-white px-3 py-2.5 text-sm dark:bg-transparent" /><span class="text-gray-400 shrink-0">&ndash;</span><input type="number" name="spouses[${idx}][marriage_end_year]" placeholder="End year" min="1900" max="2100" class="shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 h-11 min-w-0 flex-1 rounded-lg border border-gray-300 bg-white px-3 py-2.5 text-sm dark:bg-transparent" /><button type="button" class="shrink-0 text-red-500 hover:text-red-700 p-2" title="Remove"><svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg></button></div>`;
                    div.querySelector('button').addEventListener('click', () => div.remove());
                    container.insertBefore(div, container.lastElementChild);
                },
                addParentRow(container) {
                    const idx = container.querySelectorAll('.parent-row').length;
                    const div = document.createElement('div');
                    div.className = 'flex flex-wrap gap-2 items-center parent-row';
                    div.innerHTML = `<input type="text" name="parents[${idx}][parent_name]" placeholder="Name" class="shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 h-11 min-w-0 flex-1 basis-40 rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm" />
                        <select name="parents[${idx}][relationship_type]" class="h-11 shrink-0 rounded-lg border border-gray-300 dark:border-gray-600 bg-transparent dark:bg-gray-900/80 px-3 py-2.5 text-sm text-gray-800 dark:text-gray-100"><option value="biological">Biological</option><option value="adoptive">Adoptive</option></select>
                        <button type="button" class="shrink-0 text-red-500 hover:text-red-700 p-2" title="Remove"><svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg></button>`;
                    div.querySelector('button').addEventListener('click', () => div.remove());
                    container.insertBefore(div, container.lastElementChild);
                },
                addSiblingRow(container) {
                    const idx = container.querySelectorAll('.sibling-row').length;
                    const div = document.createElement('div');
                    div.className = 'flex gap-2 items-center sibling-row';
                    div.innerHTML = `<input type="text" name="siblings[${idx}][sibling_name]" placeholder="Name" class="shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 h-11 min-w-0 flex-1 rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm" />
                        <button type="button" class="shrink-0 text-red-500 hover:text-red-700 p-2" title="Remove"><svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg></button>`;
                    div.querySelector('button').addEventListener('click', () => div.remove());
                    container.insertBefore(div, container.lastElementChild);
                },
                addEducationRow(container) {
                    const idx = container.querySelectorAll('.education-row').length;
                    const div = document.createElement('div');
                    div.className = 'grid grid-cols-1 gap-2 sm:grid-cols-4 items-end education-row';
                    div.innerHTML = `<div class="sm:col-span-2"><label class="mb-1 block text-xs text-gray-500">Institution</label><input type="text" name="education[${idx}][institution_name]" placeholder="e.g. School or university name" class="shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm" /></div>
                        <div><label class="mb-1 block text-xs text-gray-500">Start year</label><input type="number" name="education[${idx}][start_year]" placeholder="1990" min="1900" max="2100" class="shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm" /></div>
                        <div><label class="mb-1 block text-xs text-gray-500">End year</label><input type="number" name="education[${idx}][end_year]" placeholder="1994" min="1900" max="2100" class="shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm" /></div>
                        <div class="sm:col-span-2"><label class="mb-1 block text-xs text-gray-500">Degree (optional)</label><input type="text" name="education[${idx}][degree]" placeholder="e.g. Diploma" class="shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm" /></div>
                        <div class="flex items-end"><button type="button" class="text-red-500 hover:text-red-700 p-2" title="Remove"><svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg></button></div>`;
                    div.querySelector('button').addEventListener('click', () => div.remove());
                    container.insertBefore(div, container.lastElementChild);
                }
            };
        }
    </script>

    <script>
        (function() {
            if (typeof Quill === 'undefined') return;
            const editorEl = document.getElementById('create-biography-editor');
            const hiddenField = document.getElementById('biography-hidden');
            if (!editorEl || !hiddenField) return;

            const q = new Quill('#create-biography-editor', {
                theme: 'snow',
                placeholder: 'Share your memories... (optional)',
                modules: {
                    toolbar: [
                        [{ 'size': ['small', false, 'large', 'huge'] }],
                        ['bold', 'italic', 'underline'],
                        [{ 'color': [] }],
                        ['link', 'blockquote'],
                        [{ 'list': 'ordered' }, { 'list': 'bullet' }],
                        [{ 'align': [] }],
                        ['clean']
                    ]
                }
            });

            const initial = hiddenField.value;
            if (initial && initial.trim()) {
                if (initial.includes('<')) {
                    q.clipboard.dangerouslyPasteHTML(0, initial);
                } else {
                    q.setText(initial);
                }
            }

            q.on('text-change', function() {
                const html = q.root.innerHTML?.trim() ?? '';
                hiddenField.value = (html === '<p><br></p>' || !html) ? '' : html;
            });
        })();
    </script>
@endpush
