{{--
    Font-picker live preview, shared by the platform admin Appearance page and the reseller
    one. Both render the same [data-font-select] pickers from the same catalogue, so the
    behaviour belongs in one file rather than being duplicated per page.
--}}
    <script>
        // Live preview: load the picked Google family on demand and render the
        // sample line in it. Uploaded fonts are already available via the
        // site-wide @font-face rules.
        document.querySelectorAll('[data-font-select]').forEach((select) => {
            // Compact pickers have no preview line — the select itself shows the font.
            const preview = select.closest('div').querySelector('[data-font-preview]') || select;
            const loaded = new Set();
            const apply = () => {
                const family = select.value;
                if (!family) {
                    preview.style.fontFamily = '';
                    return;
                }
                const isCustom = !!select.selectedOptions[0]?.hasAttribute('data-custom');
                if (!isCustom && !loaded.has(family)) {
                    loaded.add(family);
                    const link = document.createElement('link');
                    link.rel = 'stylesheet';
                    link.href = 'https://fonts.googleapis.com/css?family=' + encodeURIComponent(family).replace(/%20/g, '+') + ':400,600&display=swap';
                    document.head.appendChild(link);
                }
                preview.style.fontFamily = "'" + family + "', sans-serif";
            };
            select.addEventListener('change', apply);
            apply();
        });

        // Dropdown previews: each <option> carries an inline font-family so the
        // list shows every font in its own face. One combined stylesheet loads
        // all families subsetted (text=) to just the glyphs of the names, so
        // each preview font is a few KB instead of the full family.
        (() => {
            const families = new Set();
            document.querySelectorAll('[data-font-select] option:not([data-custom])').forEach((opt) => {
                if (opt.value) families.add(opt.value);
            });
            if (!families.size) return;
            const link = document.createElement('link');
            link.rel = 'stylesheet';
            link.href = 'https://fonts.googleapis.com/css?family='
                + [...families].map((f) => f.replace(/ /g, '+')).join('|')
                + '&text=' + encodeURIComponent('ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789 ')
                + '&display=swap';
            document.head.appendChild(link);
        })();
    </script>
