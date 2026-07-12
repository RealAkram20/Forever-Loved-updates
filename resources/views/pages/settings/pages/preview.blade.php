@extends('layouts.visitor')

@push('head')
<style>
    /* Editor preview chrome */
    #app-preloader { display: none !important; }
    html { scroll-behavior: auto !important; }
    [data-pb-id] { position: relative; }
    [data-pb-id].pb-hover {
        outline: 2px dashed rgba(59, 130, 246, .65);
        outline-offset: -2px;
        cursor: pointer;
    }
    [data-pb-id].pb-selected {
        outline: 2px solid rgb(59, 130, 246);
        outline-offset: -2px;
    }
    [data-pb-id][data-pb-hidden] { opacity: .45; }
    #pb-context-menu {
        position: fixed;
        z-index: 100;
        min-width: 180px;
        padding: 6px;
        border-radius: 12px;
        background: #ffffff;
        border: 1px solid #e4e7ec;
        box-shadow: 0 12px 32px rgba(16, 24, 40, .16);
        font-family: Outfit, system-ui, sans-serif;
        display: none;
    }
    #pb-context-menu button {
        display: flex;
        align-items: center;
        gap: 8px;
        width: 100%;
        padding: 8px 10px;
        border: 0;
        border-radius: 8px;
        background: transparent;
        color: #344054;
        font-size: 13px;
        font-weight: 500;
        text-align: left;
        cursor: pointer;
    }
    #pb-context-menu button:hover { background: #f2f4f7; }
    #pb-context-menu button.pb-danger { color: #d92d20; }
    #pb-context-menu button.pb-danger:hover { background: #fef3f2; }
    #pb-context-menu button.pb-confirming {
        background: #d92d20;
        color: #fff;
        font-weight: 600;
    }
    #pb-context-menu .pb-menu-label {
        padding: 4px 10px 6px;
        font-size: 11px;
        font-weight: 600;
        color: #98a2b3;
        text-transform: uppercase;
        letter-spacing: .04em;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
        max-width: 220px;
    }
    #pb-drop-line {
        position: absolute;
        left: 8px;
        right: 8px;
        height: 4px;
        border-radius: 9999px;
        background: rgb(59, 130, 246);
        box-shadow: 0 0 0 3px rgba(59, 130, 246, .25);
        z-index: 60;
        pointer-events: none;
        display: none;
    }
    [data-pb-id][data-pb-hidden]::before {
        content: 'Hidden on live site';
        position: absolute;
        top: 8px;
        right: 8px;
        z-index: 40;
        padding: 2px 8px;
        border-radius: 9999px;
        background: rgba(17, 24, 39, .8);
        color: #fff;
        font-size: 11px;
        font-weight: 600;
    }
</style>
@endpush

@section('page')
    <x-page-layout.renderer :widgets="$widgets" :context="$layoutContext ?? []" :editable="true" />
    @if (empty($widgets))
        <div class="flex min-h-[50vh] items-center justify-center px-6 text-center">
            <p class="max-w-sm text-sm text-gray-400 dark:text-gray-500">
                This page has no sections yet. Add a widget from the panel on the left and it will appear here instantly.
            </p>
        </div>
    @endif
@endsection

@push('scripts')
<script>
(function () {
    function send(msg) {
        try { window.parent.postMessage(Object.assign({ __pb: true }, msg), '*'); } catch (e) {}
    }

    function setSelected(el) {
        document.querySelectorAll('[data-pb-id].pb-selected').forEach(function (x) { x.classList.remove('pb-selected'); });
        if (el) el.classList.add('pb-selected');
    }

    function highlight(id, scroll) {
        var el = null;
        if (id) {
            try { el = document.querySelector('[data-pb-id="' + CSS.escape(id) + '"]'); } catch (e) {}
        }
        setSelected(el);
        if (el && scroll) el.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
    }

    // Click: select the widget in the editor; block real navigation/submits.
    document.addEventListener('click', function (e) {
        var widget = e.target.closest('[data-pb-id]');
        if (e.target.closest('a')) e.preventDefault();
        if (widget) {
            e.preventDefault();
            e.stopPropagation();
            setSelected(widget);
            send({ type: 'select', id: widget.getAttribute('data-pb-id') });
        }
    }, true);
    document.addEventListener('submit', function (e) { e.preventDefault(); }, true);

    // Hover outline
    document.addEventListener('mouseover', function (e) {
        var widget = e.target.closest('[data-pb-id]');
        document.querySelectorAll('[data-pb-id].pb-hover').forEach(function (x) { x.classList.remove('pb-hover'); });
        if (widget) widget.classList.add('pb-hover');
    });

    // Keep the editor informed of the scroll position so refreshes stay in place.
    var scrollTimer = null;
    window.addEventListener('scroll', function () {
        clearTimeout(scrollTimer);
        scrollTimer = setTimeout(function () { send({ type: 'scroll', y: window.scrollY }); }, 120);
    }, { passive: true });

    // ── Right-click context menu (duplicate / delete) ────────────────────
    var ctxMenu = null;
    var ctxTargetId = null;
    var ctxConfirming = false;

    function ensureCtxMenu() {
        if (ctxMenu) return ctxMenu;
        ctxMenu = document.createElement('div');
        ctxMenu.id = 'pb-context-menu';
        ctxMenu.innerHTML =
            '<div class="pb-menu-label" data-role="label"></div>' +
            '<button type="button" data-action="duplicate">' +
                '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect width="14" height="14" x="8" y="8" rx="2"/><path d="M4 16c-1.1 0-2-.9-2-2V4c0-1.1.9-2 2-2h10c1.1 0 2 .9 2 2"/></svg>' +
                'Duplicate element</button>' +
            '<button type="button" data-action="remove" class="pb-danger">' +
                '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 6h18"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6"/><path d="M8 6V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg>' +
                '<span data-role="remove-label">Delete element</span></button>';
        document.body.appendChild(ctxMenu);

        ctxMenu.addEventListener('click', function (e) {
            var btn = e.target.closest('button[data-action]');
            if (!btn || !ctxTargetId) return;
            e.preventDefault();
            e.stopPropagation();
            var action = btn.getAttribute('data-action');
            if (action === 'duplicate') {
                send({ type: 'duplicate', id: ctxTargetId });
                hideCtxMenu();
            } else if (action === 'remove') {
                if (!ctxConfirming) {
                    // Two-step confirm, contained in the menu itself
                    ctxConfirming = true;
                    btn.classList.add('pb-confirming');
                    btn.querySelector('[data-role="remove-label"]').textContent = 'Click again to confirm';
                } else {
                    send({ type: 'remove', id: ctxTargetId });
                    hideCtxMenu();
                }
            }
        });
        return ctxMenu;
    }

    function hideCtxMenu() {
        if (!ctxMenu) return;
        ctxMenu.style.display = 'none';
        ctxTargetId = null;
        ctxConfirming = false;
        var btn = ctxMenu.querySelector('button[data-action="remove"]');
        if (btn) {
            btn.classList.remove('pb-confirming');
            btn.querySelector('[data-role="remove-label"]').textContent = 'Delete element';
        }
    }

    document.addEventListener('contextmenu', function (e) {
        var widget = e.target.closest('[data-pb-id]');
        if (!widget) { hideCtxMenu(); return; }
        e.preventDefault();
        var menu = ensureCtxMenu();
        hideCtxMenu();
        ctxTargetId = widget.getAttribute('data-pb-id');
        setSelected(widget);
        send({ type: 'select', id: ctxTargetId });
        menu.querySelector('[data-role="label"]').textContent = widgetLabel(widget);
        menu.style.display = 'block';
        // Keep the menu inside the viewport
        var mw = menu.offsetWidth, mh = menu.offsetHeight;
        menu.style.left = Math.min(e.clientX, window.innerWidth - mw - 8) + 'px';
        menu.style.top = Math.min(e.clientY, window.innerHeight - mh - 8) + 'px';
    });

    function widgetLabel(el) {
        var heading = el.querySelector('h1, h2, h3');
        var text = heading ? heading.textContent.trim() : '';
        return text ? text.slice(0, 40) : 'This element';
    }

    document.addEventListener('click', function () { hideCtxMenu(); });
    window.addEventListener('scroll', function () { hideCtxMenu(); }, { passive: true });
    window.addEventListener('keydown', function (e) { if (e.key === 'Escape') hideCtxMenu(); });

    // ── Drag & drop from the editor palette ──────────────────────────────
    // The palette sets 'application/x-pb-widget' on dragstart; this document
    // computes the insertion index from the pointer position, shows a drop
    // line, and reports the drop back to the editor.
    var DRAG_MIME = 'application/x-pb-widget';
    var dropLine = null;
    var dropIndex = 0;

    function isPaletteDrag(e) {
        var types = e.dataTransfer && e.dataTransfer.types;
        if (!types) return false;
        return Array.prototype.indexOf.call(types, DRAG_MIME) !== -1;
    }

    function widgetEls() {
        return Array.prototype.slice.call(document.querySelectorAll('[data-pb-id]'));
    }

    function ensureDropLine() {
        if (!dropLine) {
            dropLine = document.createElement('div');
            dropLine.id = 'pb-drop-line';
            document.body.appendChild(dropLine);
        }
        return dropLine;
    }

    function hideDropLine() {
        if (dropLine) dropLine.style.display = 'none';
    }

    function updateDropLine(clientY) {
        var els = widgetEls();
        var line = ensureDropLine();
        dropIndex = els.length;
        var lineY = null;
        for (var i = 0; i < els.length; i++) {
            var r = els[i].getBoundingClientRect();
            if (clientY < r.top + r.height / 2) {
                dropIndex = i;
                lineY = r.top;
                break;
            }
        }
        if (lineY === null) {
            if (els.length) {
                lineY = els[els.length - 1].getBoundingClientRect().bottom;
            } else {
                lineY = 24; // empty page: drop anywhere, insert first
            }
        }
        line.style.top = (window.scrollY + lineY - 2) + 'px';
        line.style.display = 'block';
    }

    document.addEventListener('dragover', function (e) {
        if (!isPaletteDrag(e)) return;
        e.preventDefault();
        e.dataTransfer.dropEffect = 'copy';
        updateDropLine(e.clientY);
    });

    document.addEventListener('dragleave', function (e) {
        // Only hide when the drag actually leaves the document
        if (!e.relatedTarget) hideDropLine();
    });

    document.addEventListener('drop', function (e) {
        if (!isPaletteDrag(e)) return;
        e.preventDefault();
        hideDropLine();
        var type = e.dataTransfer.getData(DRAG_MIME);
        if (type) send({ type: 'drop', widgetType: type, index: dropIndex });
    });

    window.addEventListener('message', function (e) {
        var d = e.data || {};
        if (d.__pb !== true) return;
        if (d.type === 'state') {
            window.scrollTo(0, d.scroll || 0);
            highlight(d.selectedId, false);
        }
        if (d.type === 'highlight') highlight(d.id, true);
    });

    if (document.readyState === 'complete') {
        send({ type: 'ready' });
    } else {
        window.addEventListener('load', function () { send({ type: 'ready' }); });
    }
})();
</script>
@endpush
