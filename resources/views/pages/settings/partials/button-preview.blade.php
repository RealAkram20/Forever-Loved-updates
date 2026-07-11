{{--
    Live preview of a set of buttons. Mirrors the derivation in
    App\Helpers\BrandingHelper::buttonVars() so what is shown here is what gets rendered.

    Expects, from the caller:
      $buttons  — list of ['label', 'class', 'size', 'bg', 'text', 'bg_dark', 'text_dark']
                  where bg/text/... are the form input names of the color pickers.
      $surface  — optional ['light' => inputName, 'dark' => inputName] naming the color pickers
                  for the backdrop to preview against (the CTA banner). Omit for the default
                  page backdrop.

    Reads `mode` ('light'|'dark') from the enclosing tab scope.
--}}
@php
    $surface = $surface ?? null;

    $inputToDot = fn (string $input) => 'branding.'.trim(str_replace(['branding[', ']'], '', $input));

    $previewColors = [];
    foreach ($buttons as $b) {
        foreach (['bg', 'text', 'bg_dark', 'text_dark'] as $slot) {
            $previewColors[$b[$slot]] = $settings[$inputToDot($b[$slot])] ?? '#000000';
        }
    }
    foreach ($surface ?? [] as $input) {
        $previewColors[$input] = $settings[$inputToDot($input)] ?? '#465fff';
    }
@endphp

<div x-data="buttonPreview(@js($previewColors), @js($buttons), @js($surface))"
     @color-picker:change.window="onColorChange($event.detail)"
     class="rounded-xl border p-8 transition-colors"
     :class="surface ? 'border-transparent' : (mode === 'dark' ? 'border-gray-700 bg-gray-900' : 'border-gray-200 bg-gray-50')"
     :style="surface ? 'background-color:' + colors[surface[mode]] : ''">

    <p class="mb-5 text-[10px] font-semibold uppercase tracking-wider"
       :class="surface ? 'text-white/70' : (mode === 'dark' ? 'text-gray-500' : 'text-gray-400')">
        Preview — <span x-text="mode === 'dark' ? 'Dark Mode' : 'Light Mode'"></span>
    </p>

    <div class="flex flex-wrap items-center gap-4">
        <template x-for="b in buttons" :key="b.label">
            {{-- `mode` is passed in explicitly: it lives on the parent tab scope, which Alpine
                 resolves in template expressions but not via `this` inside a method. --}}
            <span class="btn" :class="[b.class, b.size || 'btn-lg']" :style="styleFor(b, mode)" x-text="b.label"></span>
        </template>
    </div>
</div>

@once
    @push('scripts')
        <script>
            document.addEventListener('alpine:init', () => {
                Alpine.data('buttonPreview', (initialColors, buttons, surface) => ({
                    colors: { ...initialColors },
                    buttons: buttons,
                    surface: surface,

                    onColorChange({ name, hex }) {
                        if (name in this.colors) this.colors[name] = hex;
                    },

                    rgb(hex) {
                        let h = (hex || '').replace('#', '');
                        if (h.length === 3) h = h[0] + h[0] + h[1] + h[1] + h[2] + h[2];
                        if (!/^[0-9a-f]{6}$/i.test(h)) return { r: 0, g: 0, b: 0 };
                        return {
                            r: parseInt(h.slice(0, 2), 16),
                            g: parseInt(h.slice(2, 4), 16),
                            b: parseInt(h.slice(4, 6), 16),
                        };
                    },

                    rgba(hex, alpha) {
                        const { r, g, b } = this.rgb(hex);
                        return `rgba(${r}, ${g}, ${b}, ${alpha})`;
                    },

                    luminance(hex) {
                        const { r, g, b } = this.rgb(hex);
                        return 0.299 * r + 0.587 * g + 0.114 * b;
                    },

                    styleFor(b, mode) {
                        const dark = mode === 'dark';
                        const bg = this.colors[dark ? b.bg_dark : b.bg];
                        const text = this.colors[dark ? b.text_dark : b.text];

                        // Same rule as BrandingHelper::buttonVars(): a near-white button borrows
                        // its text color for the glow, otherwise the halo is a grey smudge.
                        const glowSource = this.luminance(bg) > 225 ? text : bg;
                        const alpha = this.luminance(glowSource) > 225 ? 0.14 : 0.35;
                        const glow = this.rgba(glowSource, alpha);

                        return [
                            `background-color:${bg}`,
                            `color:${text}`,
                            `border-color:${this.rgba(text, 0.25)}`,
                            `box-shadow:0 10px 15px -3px ${glow}, 0 4px 6px -4px ${glow}`,
                        ].join(';');
                    },
                }));
            });
        </script>
    @endpush
@endonce
