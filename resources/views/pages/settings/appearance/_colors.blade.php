{{--
    Every colour control on the Appearance page: mode tabs, brand/accent, page background,
    text colours, per-element typography roles, buttons, CTA banner.

    Shared by the platform admin page (pages/settings/appearance.blade.php) and the reseller
    page (pages/reseller/appearance.blade.php). Both write the same setting keys — the admin's
    land in system_settings, the reseller's in reseller_settings — so keeping one copy is what
    stops a colour added to one form from being unvalidated and unreachable in the other.

    Expects, from the including page:
      $settings   — key => current value, used by the colour partials to resolve each field
      $inputClass — shared text-input classes
      an enclosing <form x-data="{ mode: 'light' }"> whose `mode` these tabs drive
--}}
                {{-- Global Light / Dark tabs --}}
                <div class="mb-8 inline-flex rounded-xl bg-gray-100 p-1 dark:bg-gray-800" role="tablist" aria-label="Color mode">
                    <button type="button" @click="mode = 'light'" role="tab" :aria-selected="mode === 'light'"
                        class="inline-flex items-center gap-2 rounded-lg px-4 py-2 text-sm font-medium transition"
                        :class="mode === 'light'
                            ? 'bg-white text-gray-900 shadow-sm dark:bg-gray-700 dark:text-white'
                            : 'text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200'">
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M12 3v1m0 16v1m9-9h-1M4 12H3m15.364 6.364l-.707-.707M6.343 6.343l-.707-.707m12.728 0l-.707.707M6.343 17.657l-.707.707M16 12a4 4 0 11-8 0 4 4 0 018 0z" />
                        </svg>
                        Light Mode
                    </button>
                    <button type="button" @click="mode = 'dark'" role="tab" :aria-selected="mode === 'dark'"
                        class="inline-flex items-center gap-2 rounded-lg px-4 py-2 text-sm font-medium transition"
                        :class="mode === 'dark'
                            ? 'bg-white text-gray-900 shadow-sm dark:bg-gray-700 dark:text-white'
                            : 'text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200'">
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M20.354 15.354A9 9 0 018.646 3.646 9.003 9.003 0 0012 21a9.003 9.003 0 008.354-5.646z" />
                        </svg>
                        Dark Mode
                    </button>
                </div>

                {{-- Brand & accent --}}
                <div class="mb-8">
                    <h4 class="mb-1 text-sm font-semibold text-gray-900 dark:text-white/90">Brand &amp; Accent</h4>
                    <p class="mb-4 text-xs text-gray-500 dark:text-gray-400">Brand colors used across the application, plus highlight colors for badges, icons and emphasis.</p>
                    <div x-show="mode === 'light'" class="grid grid-cols-1 gap-5 sm:grid-cols-2 lg:grid-cols-4">
                        @include('pages.settings.partials.color-field', ['label' => 'Primary', 'name' => 'branding[primary_color]', 'dotName' => 'branding.primary_color', 'default' => '#465fff'])
                        @include('pages.settings.partials.color-field', ['label' => 'Primary Light', 'name' => 'branding[primary_light]', 'dotName' => 'branding.primary_light', 'default' => '#465fff'])
                        @include('pages.settings.partials.color-field', ['label' => 'Accent', 'name' => 'branding[accent_color]', 'dotName' => 'branding.accent_color', 'default' => '#f59e0b'])
                        @include('pages.settings.partials.color-field', ['label' => 'Accent Light', 'name' => 'branding[accent_light]', 'dotName' => 'branding.accent_light', 'default' => '#f59e0b'])
                    </div>
                    <div x-show="mode === 'dark'" x-cloak class="grid grid-cols-1 gap-5 sm:grid-cols-2 lg:grid-cols-4">
                        @include('pages.settings.partials.color-field', ['label' => 'Primary', 'name' => 'branding[secondary_color]', 'dotName' => 'branding.secondary_color', 'default' => '#1e3a5f'])
                        @include('pages.settings.partials.color-field', ['label' => 'Primary Dark', 'name' => 'branding[primary_dark]', 'dotName' => 'branding.primary_dark', 'default' => '#1e3a5f'])
                        @include('pages.settings.partials.color-field', ['label' => 'Accent', 'name' => 'branding[accent_dark]', 'dotName' => 'branding.accent_dark', 'default' => '#f59e0b'])
                    </div>
                </div>

                <hr class="border-gray-200 dark:border-gray-700 mb-8" />

                {{-- Background --}}
                <div class="mb-8">
                    <h4 class="mb-1 text-sm font-semibold text-gray-900 dark:text-white/90">Page Background</h4>
                    <p class="mb-4 text-xs text-gray-500 dark:text-gray-400">The base page background for the selected mode.</p>
                    <div x-show="mode === 'light'" class="grid grid-cols-1 gap-5 sm:grid-cols-2 lg:grid-cols-4">
                        @include('pages.settings.partials.color-field', ['label' => 'Background', 'name' => 'branding[bg_light]', 'dotName' => 'branding.bg_light', 'default' => '#f9fafb'])
                    </div>
                    <div x-show="mode === 'dark'" x-cloak class="grid grid-cols-1 gap-5 sm:grid-cols-2 lg:grid-cols-4">
                        @include('pages.settings.partials.color-field', ['label' => 'Background', 'name' => 'branding[bg_dark]', 'dotName' => 'branding.bg_dark', 'default' => '#101828'])
                    </div>
                </div>

                <hr class="border-gray-200 dark:border-gray-700 mb-8" />

                {{-- Text colors --}}
                <div class="mb-8">
                    <h4 class="mb-1 text-sm font-semibold text-gray-900 dark:text-white/90">Text Colors</h4>
                    <p class="mb-4 text-xs text-gray-500 dark:text-gray-400">Applied to the visitor-facing site (home, memorial pages, signup). Leave a color empty to keep the theme default.</p>
                    @foreach (['light', 'dark'] as $tcMode)
                        <div x-show="mode === '{{ $tcMode }}'" @if ($tcMode === 'dark') x-cloak @endif class="grid grid-cols-1 gap-5 sm:grid-cols-3">
                            @foreach ([['role' => 'heading', 'label' => 'Headings', 'hint' => 'Titles and section headings.'],
                                       ['role' => 'body', 'label' => 'Body text', 'hint' => 'Paragraphs and standard text.'],
                                       ['role' => 'muted', 'label' => 'Secondary text', 'hint' => 'Captions, timestamps, helper text.']] as $group)
                                @php
                                    $key = "appearance.text_{$group['role']}_{$tcMode}";
                                    $value = old($key, $settings[$key] ?? '');
                                @endphp
                                <div x-data="{ hex: '{{ $value }}' }">
                                    <label class="mb-2 block text-xs font-medium text-gray-600 dark:text-gray-400" for="{{ $key }}">{{ $group['label'] }}</label>
                                    <div class="flex items-center gap-2">
                                        <input type="color" :value="hex || '#888888'" @input="hex = $event.target.value"
                                            class="h-11 w-12 shrink-0 cursor-pointer rounded-lg border border-gray-300 dark:border-gray-700 bg-transparent p-1"
                                            aria-label="{{ $group['label'] }} ({{ $tcMode }} mode) color picker" />
                                        <input type="text" id="{{ $key }}" name="appearance[text_{{ $group['role'] }}_{{ $tcMode }}]"
                                            x-model="hex" placeholder="Theme default" maxlength="7"
                                            class="{{ $inputClass }}" />
                                        <button type="button" x-show="hex" @click="hex = ''"
                                            class="shrink-0 rounded-lg p-2 text-gray-400 hover:text-gray-600 dark:hover:text-gray-200"
                                            aria-label="Clear {{ $group['label'] }} {{ $tcMode }} mode color">
                                            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                                        </button>
                                    </div>
                                    <p class="mt-1.5 text-xs text-gray-500 dark:text-gray-400">{{ $group['hint'] }}</p>
                                </div>
                            @endforeach
                        </div>
                    @endforeach
                </div>

                <hr class="border-gray-200 dark:border-gray-700 mb-8" />

                {{-- Per-element typography roles --}}
                <div class="mb-8">
                    <h4 class="mb-1 text-sm font-semibold text-gray-900 dark:text-white/90">Text Elements</h4>
                    <p class="mb-4 text-xs text-gray-500 dark:text-gray-400">
                        Fine-grained control over specific text across the site: pick a font per element,
                        and a color per element for the mode selected in the tabs above. Empty = theme default.
                    </p>
                    <div class="divide-y divide-gray-100 dark:divide-gray-800 rounded-lg border border-gray-100 dark:border-gray-800">
                        @foreach ([
                            ['role' => 'title', 'label' => 'Main heading', 'hint' => 'Big page and section headings, e.g. “Honor Your Loved Ones.”'],
                            ['role' => 'accent', 'label' => 'Heading accent line', 'hint' => 'The highlighted heading line, e.g. “Forever Remembered.”'],
                            ['role' => 'lead', 'label' => 'Lead paragraph', 'hint' => 'The intro text under headings.'],
                            ['role' => 'eyebrow', 'label' => 'Eyebrow label', 'hint' => 'The small uppercase label above headings, e.g. “CELEBRATE LIVES THAT MATTER”.'],
                            ['role' => 'cta_title', 'label' => 'CTA banner heading', 'hint' => 'The heading on the call-to-action banner.'],
                            ['role' => 'cta_body', 'label' => 'CTA banner text', 'hint' => 'The supporting text on the call-to-action banner.'],
                        ] as $tr)
                            <div class="grid grid-cols-1 gap-4 p-4 lg:grid-cols-[1.2fr_1fr_1fr] lg:items-end">
                                <div>
                                    <p class="text-sm font-medium text-gray-800 dark:text-white/90">{{ $tr['label'] }}</p>
                                    <p class="mt-0.5 text-xs text-gray-500 dark:text-gray-400">{{ $tr['hint'] }}</p>
                                </div>
                                <div>
                                    <label class="mb-1.5 block text-xs font-medium text-gray-600 dark:text-gray-400" for="role_{{ $tr['role'] }}_font">Font</label>
                                    @include('pages.settings.partials.font-select', [
                                        'id' => 'role_'.$tr['role'].'_font',
                                        'name' => 'appearance[role_'.$tr['role'].'_font]',
                                        'current' => old('appearance.role_'.$tr['role'].'_font', $settings['appearance.role_'.$tr['role'].'_font'] ?? ''),
                                    ])
                                </div>
                                @foreach (['light', 'dark'] as $trMode)
                                    @php
                                        $key = "appearance.role_{$tr['role']}_color_{$trMode}";
                                        $value = old($key, $settings[$key] ?? '');
                                    @endphp
                                    <div x-show="mode === '{{ $trMode }}'" @if ($trMode === 'dark') x-cloak @endif x-data="{ hex: '{{ $value }}' }">
                                        <label class="mb-1.5 block text-xs font-medium text-gray-600 dark:text-gray-400" for="{{ $key }}">
                                            Color — <span x-text="mode === 'dark' ? 'Dark Mode' : 'Light Mode'"></span>
                                        </label>
                                        <div class="flex items-center gap-2">
                                            <input type="color" :value="hex || '#888888'" @input="hex = $event.target.value"
                                                class="h-11 w-12 shrink-0 cursor-pointer rounded-lg border border-gray-300 dark:border-gray-700 bg-transparent p-1"
                                                aria-label="{{ $tr['label'] }} ({{ $trMode }} mode) color picker" />
                                            <input type="text" id="{{ $key }}" name="appearance[role_{{ $tr['role'] }}_color_{{ $trMode }}]"
                                                x-model="hex" placeholder="Theme default" maxlength="7"
                                                class="{{ $inputClass }}" />
                                            <button type="button" x-show="hex" @click="hex = ''"
                                                class="shrink-0 rounded-lg p-2 text-gray-400 hover:text-gray-600 dark:hover:text-gray-200"
                                                aria-label="Clear {{ $tr['label'] }} {{ $trMode }} mode color">
                                                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                                            </button>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        @endforeach
                    </div>
                </div>

                <hr class="border-gray-200 dark:border-gray-700 mb-8" />

                {{-- Buttons --}}
                <div class="mb-8">
                    <h4 class="mb-1 text-sm font-semibold text-gray-900 dark:text-white/90">Buttons</h4>
                    <p class="mb-5 text-xs text-gray-500 dark:text-gray-400">
                        Every button across the site uses these colors. The glow under each button is derived
                        automatically from its background, so it always matches.
                    </p>

                    @include('pages.settings.partials.button-preview', [
                        'buttons' => [
                            ['label' => 'Primary Button', 'class' => 'btn-primary', 'size' => 'btn-lg', 'bg' => 'branding[button1_color]', 'text' => 'branding[button1_text_color]', 'bg_dark' => 'branding[button1_color_dark]', 'text_dark' => 'branding[button1_text_color_dark]'],
                            ['label' => 'Secondary', 'class' => 'btn-secondary', 'size' => 'btn-lg', 'bg' => 'branding[button2_color]', 'text' => 'branding[button2_text_color]', 'bg_dark' => 'branding[button2_color_dark]', 'text_dark' => 'branding[button2_text_color_dark]'],
                            ['label' => 'Medium', 'class' => 'btn-primary', 'size' => 'btn-md', 'bg' => 'branding[button1_color]', 'text' => 'branding[button1_text_color]', 'bg_dark' => 'branding[button1_color_dark]', 'text_dark' => 'branding[button1_text_color_dark]'],
                            ['label' => 'Small', 'class' => 'btn-secondary', 'size' => 'btn-sm', 'bg' => 'branding[button2_color]', 'text' => 'branding[button2_text_color]', 'bg_dark' => 'branding[button2_color_dark]', 'text_dark' => 'branding[button2_text_color_dark]'],
                        ],
                        'surface' => null,
                    ])

                    <div x-show="mode === 'light'" class="mt-6">
                        <div class="grid grid-cols-1 gap-5 sm:grid-cols-2 lg:grid-cols-4">
                            @include('pages.settings.partials.color-field', ['label' => 'Primary Background', 'name' => 'branding[button1_color]', 'dotName' => 'branding.button1_color', 'default' => '#465fff'])
                            @include('pages.settings.partials.color-field', ['label' => 'Primary Text', 'name' => 'branding[button1_text_color]', 'dotName' => 'branding.button1_text_color', 'default' => '#ffffff'])
                            @include('pages.settings.partials.color-field', ['label' => 'Secondary Background', 'name' => 'branding[button2_color]', 'dotName' => 'branding.button2_color', 'default' => '#ffffff'])
                            @include('pages.settings.partials.color-field', ['label' => 'Secondary Text', 'name' => 'branding[button2_text_color]', 'dotName' => 'branding.button2_text_color', 'default' => '#374151'])
                        </div>
                    </div>

                    <div x-show="mode === 'dark'" x-cloak class="mt-6">
                        <div class="grid grid-cols-1 gap-5 sm:grid-cols-2 lg:grid-cols-4">
                            @include('pages.settings.partials.color-field', ['label' => 'Primary Background', 'name' => 'branding[button1_color_dark]', 'dotName' => 'branding.button1_color_dark', 'default' => '#465fff'])
                            @include('pages.settings.partials.color-field', ['label' => 'Primary Text', 'name' => 'branding[button1_text_color_dark]', 'dotName' => 'branding.button1_text_color_dark', 'default' => '#ffffff'])
                            @include('pages.settings.partials.color-field', ['label' => 'Secondary Background', 'name' => 'branding[button2_color_dark]', 'dotName' => 'branding.button2_color_dark', 'default' => '#1f2937'])
                            @include('pages.settings.partials.color-field', ['label' => 'Secondary Text', 'name' => 'branding[button2_text_color_dark]', 'dotName' => 'branding.button2_text_color_dark', 'default' => '#d1d5db'])
                        </div>
                    </div>
                </div>

                <hr class="border-gray-200 dark:border-gray-700 mb-8" />

                {{-- CTA Banner --}}
                <div>
                    <h4 class="mb-1 text-sm font-semibold text-gray-900 dark:text-white/90">CTA Banner</h4>
                    <p class="mb-5 text-xs text-gray-500 dark:text-gray-400">
                        The call-to-action banner sits on its own colored background, so its two buttons are
                        styled independently of the site-wide buttons above.
                    </p>

                    @include('pages.settings.partials.button-preview', [
                        'buttons' => [
                            ['label' => 'Get Started Free', 'class' => 'btn-cta-primary', 'size' => 'btn-lg', 'bg' => 'branding[cta_btn1_color]', 'text' => 'branding[cta_btn1_text_color]', 'bg_dark' => 'branding[cta_btn1_color_dark]', 'text_dark' => 'branding[cta_btn1_text_color_dark]'],
                            ['label' => 'View Plans', 'class' => 'btn-cta-secondary', 'size' => 'btn-lg', 'bg' => 'branding[cta_btn2_color]', 'text' => 'branding[cta_btn2_text_color]', 'bg_dark' => 'branding[cta_btn2_color_dark]', 'text_dark' => 'branding[cta_btn2_text_color_dark]'],
                        ],
                        'surface' => ['light' => 'branding[cta_bg_light]', 'dark' => 'branding[cta_bg_dark]'],
                    ])

                    <div x-show="mode === 'light'" class="mt-6 space-y-6">
                        <div>
                            <p class="mb-3 text-xs font-semibold uppercase tracking-wider text-gray-400 dark:text-gray-500">Banner Background</p>
                            <div class="grid grid-cols-1 gap-5 sm:grid-cols-2 lg:grid-cols-4">
                                @include('pages.settings.partials.color-field', ['label' => 'Banner Background', 'name' => 'branding[cta_bg_light]', 'dotName' => 'branding.cta_bg_light', 'default' => '#465fff'])
                                @include('pages.settings.partials.range-field', [
                                    'label' => 'Text Legibility',
                                    'name' => 'branding[cta_overlay_light]',
                                    'dotName' => 'branding.cta_overlay_light',
                                    'default' => 0,
                                    'help' => 'Fades the banner background over its artwork, behind the headline. Raise it until the text reads clearly; 0 shows the artwork untouched.',
                                ])
                            </div>
                        </div>
                        <div>
                            <p class="mb-3 text-xs font-semibold uppercase tracking-wider text-gray-400 dark:text-gray-500">Button 1 &mdash; &ldquo;Get Started Free&rdquo;</p>
                            <div class="grid grid-cols-1 gap-5 sm:grid-cols-2 lg:grid-cols-4">
                                @include('pages.settings.partials.color-field', ['label' => 'Background', 'name' => 'branding[cta_btn1_color]', 'dotName' => 'branding.cta_btn1_color', 'default' => '#ffffff'])
                                @include('pages.settings.partials.color-field', ['label' => 'Text', 'name' => 'branding[cta_btn1_text_color]', 'dotName' => 'branding.cta_btn1_text_color', 'default' => '#465fff'])
                            </div>
                        </div>
                        <div>
                            <p class="mb-3 text-xs font-semibold uppercase tracking-wider text-gray-400 dark:text-gray-500">Button 2 &mdash; &ldquo;View Plans&rdquo;</p>
                            <div class="grid grid-cols-1 gap-5 sm:grid-cols-2 lg:grid-cols-4">
                                @include('pages.settings.partials.color-field', ['label' => 'Background', 'name' => 'branding[cta_btn2_color]', 'dotName' => 'branding.cta_btn2_color', 'default' => '#3641f5'])
                                @include('pages.settings.partials.color-field', ['label' => 'Text', 'name' => 'branding[cta_btn2_text_color]', 'dotName' => 'branding.cta_btn2_text_color', 'default' => '#ffffff'])
                            </div>
                        </div>
                    </div>

                    <div x-show="mode === 'dark'" x-cloak class="mt-6 space-y-6">
                        <div>
                            <p class="mb-3 text-xs font-semibold uppercase tracking-wider text-gray-400 dark:text-gray-500">Banner Background</p>
                            <div class="grid grid-cols-1 gap-5 sm:grid-cols-2 lg:grid-cols-4">
                                @include('pages.settings.partials.color-field', ['label' => 'Banner Background', 'name' => 'branding[cta_bg_dark]', 'dotName' => 'branding.cta_bg_dark', 'default' => '#3641f5'])
                                @include('pages.settings.partials.range-field', [
                                    'label' => 'Text Legibility',
                                    'name' => 'branding[cta_overlay_dark]',
                                    'dotName' => 'branding.cta_overlay_dark',
                                    'default' => 55,
                                    'help' => 'Fades the banner background over its artwork, behind the headline. Raise it until the text reads clearly; 0 shows the artwork untouched.',
                                ])
                            </div>
                        </div>
                        <div>
                            <p class="mb-3 text-xs font-semibold uppercase tracking-wider text-gray-400 dark:text-gray-500">Button 1 &mdash; &ldquo;Get Started Free&rdquo;</p>
                            <div class="grid grid-cols-1 gap-5 sm:grid-cols-2 lg:grid-cols-4">
                                @include('pages.settings.partials.color-field', ['label' => 'Background', 'name' => 'branding[cta_btn1_color_dark]', 'dotName' => 'branding.cta_btn1_color_dark', 'default' => '#ffffff'])
                                @include('pages.settings.partials.color-field', ['label' => 'Text', 'name' => 'branding[cta_btn1_text_color_dark]', 'dotName' => 'branding.cta_btn1_text_color_dark', 'default' => '#1e3a5f'])
                            </div>
                        </div>
                        <div>
                            <p class="mb-3 text-xs font-semibold uppercase tracking-wider text-gray-400 dark:text-gray-500">Button 2 &mdash; &ldquo;View Plans&rdquo;</p>
                            <div class="grid grid-cols-1 gap-5 sm:grid-cols-2 lg:grid-cols-4">
                                @include('pages.settings.partials.color-field', ['label' => 'Background', 'name' => 'branding[cta_btn2_color_dark]', 'dotName' => 'branding.cta_btn2_color_dark', 'default' => '#1e3a5f'])
                                @include('pages.settings.partials.color-field', ['label' => 'Text', 'name' => 'branding[cta_btn2_text_color_dark]', 'dotName' => 'branding.cta_btn2_text_color_dark', 'default' => '#ffffff'])
                            </div>
                        </div>
                    </div>
                </div>
