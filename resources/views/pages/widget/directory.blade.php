@extends('layouts.embed')

@section('title', ($tenant?->name ?: config('app.name')).' — Memorials')

@section('content')
    {{-- The Find a Memorial experience with the chrome stripped for an iframe on
         somebody else's page: no site nav, no page title, the host page provides both.
         Results, search and pagination all go through fetch() against the widget's own
         JSON endpoint, so the embedding page never navigates; the embed layout's
         ResizeObserver reports each new height to the parent. --}}
    <div style="max-width:960px;margin:0 auto;padding:16px;" id="fl-directory"
         data-results-url="{{ route('widget.directory.results') }}"
         data-mode="{{ $mode }}"
         data-slugs="{{ implode(',', $slugs) }}">

        @if ($tenant?->logo_path)
            <img src="{{ \App\Helpers\StorageHelper::publicUrl($tenant->logo_path) }}"
                 alt="{{ $tenant->name }}" class="embed-logo" style="margin-left:0;">
        @endif

        @if ($mode === 'all')
            {{-- A curated set needs no search — its author already chose. --}}
            <div style="display:flex;flex-wrap:wrap;gap:8px;margin-bottom:16px;">
                <input type="search" id="fl-q" placeholder="Search by name, profession, place…"
                       style="flex:1;min-width:200px;padding:9px 12px;border:1px solid #d1d5db;border-radius:8px;font-size:14px;background:#fff;color:#111827;">
                <select id="fl-gender" style="padding:9px 12px;border:1px solid #d1d5db;border-radius:8px;font-size:14px;background:#fff;color:#111827;">
                    <option value="">All</option>
                    <option value="male">Male</option>
                    <option value="female">Female</option>
                </select>
            </div>
        @endif

        <p id="fl-count" style="font-size:13px;color:#6b7280;margin:0 0 12px;"></p>

        <div id="fl-grid" style="display:grid;grid-template-columns:repeat(auto-fill,minmax(160px,1fr));gap:14px;"></div>

        <p id="fl-empty" style="display:none;color:#6b7280;font-size:14px;text-align:center;padding:32px 0;">No memorials found.</p>

        <div id="fl-pager" style="display:none;justify-content:center;align-items:center;gap:14px;margin-top:18px;">
            <button type="button" id="fl-prev" class="embed-cta" style="padding:7px 14px;">&larr; Previous</button>
            <span id="fl-page" style="font-size:13px;color:#6b7280;"></span>
            <button type="button" id="fl-next" class="embed-cta" style="padding:7px 14px;">Next &rarr;</button>
        </div>
    </div>

    <script>
        (function () {
            var root = document.getElementById('fl-directory');
            var state = { page: 1, q: '', gender: '' };
            var meta = { last_page: 1, total: 0 };

            function esc(s) {
                var d = document.createElement('div');
                d.textContent = s == null ? '' : String(s);
                return d.innerHTML;
            }

            function card(item) {
                var photo = item.photo
                    ? '<img src="' + esc(item.photo) + '" alt="' + esc(item.name) + '" loading="lazy" style="width:100%;aspect-ratio:3/4;object-fit:cover;border-radius:10px;display:block;">'
                    : '<div style="width:100%;aspect-ratio:3/4;border-radius:10px;background:#f3f4f6;display:flex;align-items:center;justify-content:center;color:#9ca3af;font-size:28px;">&#10047;</div>';

                return '<a href="' + esc(item.url) + '" target="_top" style="text-decoration:none;display:block;">'
                    + photo
                    + '<p style="margin:8px 0 0;font-size:12px;color:var(--color-btn-primary, #b45309);">' + esc(item.years || '') + '</p>'
                    + '<p style="margin:2px 0 0;font-size:14px;font-weight:600;color:#111827;">' + esc(item.name) + '</p>'
                    + (item.profession ? '<p style="margin:2px 0 0;font-size:12px;color:#6b7280;">' + esc(item.profession) + '</p>' : '')
                    + '</a>';
            }

            function load() {
                var params = new URLSearchParams({ page: state.page, per_page: 12 });
                if (root.dataset.slugs) params.set('slugs', root.dataset.slugs);
                if (state.q) params.set('q', state.q);
                if (state.gender) params.set('gender', state.gender);

                fetch(root.dataset.resultsUrl + '?' + params.toString(), {
                    headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                })
                    .then(function (r) { return r.ok ? r.json() : null; })
                    .then(function (payload) {
                        if (!payload) return;
                        meta = payload.meta;

                        document.getElementById('fl-grid').innerHTML = payload.data.map(card).join('');
                        document.getElementById('fl-empty').style.display = payload.data.length ? 'none' : 'block';
                        document.getElementById('fl-count').textContent =
                            meta.total + (meta.total === 1 ? ' memorial' : ' memorials');

                        var pager = document.getElementById('fl-pager');
                        pager.style.display = meta.last_page > 1 ? 'flex' : 'none';
                        document.getElementById('fl-page').textContent = 'Page ' + meta.current_page + ' of ' + meta.last_page;
                        document.getElementById('fl-prev').style.visibility = meta.current_page > 1 ? 'visible' : 'hidden';
                        document.getElementById('fl-next').style.visibility = meta.current_page < meta.last_page ? 'visible' : 'hidden';
                    });
            }

            document.getElementById('fl-prev').addEventListener('click', function () {
                if (state.page > 1) { state.page--; load(); }
            });
            document.getElementById('fl-next').addEventListener('click', function () {
                if (state.page < meta.last_page) { state.page++; load(); }
            });

            var q = document.getElementById('fl-q');
            if (q) {
                var t = null;
                q.addEventListener('input', function () {
                    clearTimeout(t);
                    t = setTimeout(function () { state.q = q.value.trim(); state.page = 1; load(); }, 300);
                });
            }
            var g = document.getElementById('fl-gender');
            if (g) {
                g.addEventListener('change', function () { state.gender = g.value; state.page = 1; load(); });
            }

            load();
        })();
    </script>
@endsection
