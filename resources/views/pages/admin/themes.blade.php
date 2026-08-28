@extends('layouts.app')

{{--
    The platform's view of the theme catalogue.

    Organised around the one thing that can actually be wrong here: disk and database
    disagreeing. A template nobody has synced is invisible to every reseller; a row whose
    template is gone renders those resellers' sites in the base design without telling anyone.
    Both are surfaced before the gallery, because both are silent otherwise.
--}}

@section('content')
    <x-common.page-breadcrumb pageTitle="Themes" />

    @if (session('success'))
        <div class="mb-4 rounded-lg bg-green-50 px-4 py-3 text-sm text-green-700 dark:bg-green-900/20 dark:text-green-400">
            {{ session('success') }}
        </div>
    @endif

    @if ($errors->any())
        <div class="mb-4 rounded-lg bg-red-50 px-4 py-3 dark:bg-red-900/20">
            <ul class="list-inside list-disc text-sm text-red-600 dark:text-red-400">
                @foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach
            </ul>
        </div>
    @endif

    <div class="space-y-6">
        <x-common.component-card
            title="Templates"
            desc="A theme is a template on disk plus the palette it runs with. Resellers pick one from this catalogue on their own Theme page.">

            <div class="flex flex-wrap items-center justify-between gap-3">
                <p class="text-sm text-gray-500 dark:text-gray-400">
                    Templates are read from
                    <span class="font-mono text-xs text-gray-700 dark:text-gray-300">{{ $themesPath }}</span>.
                    A new one becomes selectable once it is synced.
                </p>
                <form action="{{ route('settings.themes.sync') }}" method="POST">
                    @csrf
                    <button type="submit" class="btn btn-secondary btn-sm">Sync from disk</button>
                </form>
            </div>

            {{-- On disk, not in the catalogue: nobody can choose it. Loud, because the symptom
                 is "I deployed a theme and it isn't there", which looks like a bug. --}}
            @php $unsynced = $templates->where('unsynced', true); @endphp
            @if ($unsynced->isNotEmpty())
                <div class="rounded-xl border border-amber-200 bg-amber-50 p-4 dark:border-amber-500/30 dark:bg-amber-500/[0.08]">
                    <p class="text-sm text-amber-800 dark:text-amber-200">
                        {{ $unsynced->count() }} {{ \Illuminate\Support\Str::plural('template', $unsynced->count()) }}
                        on disk {{ $unsynced->count() === 1 ? 'is' : 'are' }} not in the catalogue yet, so no reseller can
                        choose {{ $unsynced->count() === 1 ? 'it' : 'them' }}:
                        <span class="font-mono text-xs">{{ $unsynced->pluck('template')->join(', ') }}</span>.
                        Sync to add {{ $unsynced->count() === 1 ? 'it' : 'them' }}.
                    </p>
                </div>
            @endif

            {{-- The reverse: a row pointing at nothing. These sites are live and rendering in
                 the base design right now. --}}
            @if ($orphans->isNotEmpty())
                <div class="rounded-xl border border-red-200 bg-red-50 p-4 dark:border-red-500/30 dark:bg-red-500/[0.08]">
                    <p class="mb-2 text-sm font-medium text-red-700 dark:text-red-300">Templates not deployed on this server</p>
                    <ul class="space-y-1 text-sm text-red-600 dark:text-red-400">
                        @foreach ($orphans as $orphan)
                            <li>
                                <span class="font-medium">{{ $orphan['theme']->name }}</span>
                                (<span class="font-mono text-xs">{{ $orphan['theme']->template }}</span>) —
                                @if ($orphan['in_use'] > 0)
                                    {{ $orphan['in_use'] }} {{ \Illuminate\Support\Str::plural('site', $orphan['in_use']) }}
                                    using it {{ $orphan['in_use'] === 1 ? 'is' : 'are' }} rendering in the Basic design.
                                @else
                                    not in use by any reseller.
                                @endif
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <div class="grid grid-cols-1 gap-5 sm:grid-cols-2 xl:grid-cols-3">
                @foreach ($templates as $entry)
                    @php
                        $theme = $entry['theme'];
                        $manifest = $entry['manifest'];
                    @endphp

                    <div class="flex flex-col rounded-2xl border border-gray-200 p-4 dark:border-gray-800">
                        @include('partials.theme-preview', ['manifest' => $manifest])

                        <div class="mt-4 flex items-start gap-2">
                            <h4 class="text-sm font-semibold text-gray-800 dark:text-white/90">{{ $manifest->name }}</h4>

                            @if ($entry['unsynced'])
                                <span class="ml-auto shrink-0 rounded-full bg-amber-100 px-2 py-0.5 text-[11px] font-semibold text-amber-700 dark:bg-amber-500/20 dark:text-amber-300">Not synced</span>
                            @elseif (! $theme->is_published)
                                <span class="ml-auto shrink-0 rounded-full bg-gray-100 px-2 py-0.5 text-[11px] font-medium text-gray-600 dark:bg-white/10 dark:text-gray-300">Hidden</span>
                            @else
                                <span class="ml-auto shrink-0 rounded-full bg-green-100 px-2 py-0.5 text-[11px] font-medium text-green-700 dark:bg-green-500/20 dark:text-green-400">Live</span>
                            @endif
                        </div>

                        <p class="mt-1.5 line-clamp-3 text-xs leading-relaxed text-gray-500 dark:text-gray-400">{{ $manifest->description }}</p>

                        <dl class="mt-3 space-y-1 text-xs text-gray-500 dark:text-gray-400">
                            <div class="flex justify-between gap-2">
                                <dt>Folder</dt>
                                <dd class="font-mono text-gray-700 dark:text-gray-300">{{ $entry['template'] }}</dd>
                            </div>
                            <div class="flex justify-between gap-2">
                                <dt>Palette</dt>
                                <dd>{{ count($manifest->tokens) ?: 'inherits' }}</dd>
                            </div>
                            <div class="flex justify-between gap-2">
                                <dt>In use</dt>
                                <dd>{{ $entry['in_use'] }} {{ \Illuminate\Support\Str::plural('reseller', $entry['in_use']) }}</dd>
                            </div>
                        </dl>

                        @if ($theme && ! $entry['unsynced'])
                            {{-- Gating is on applying, and only on applying. Resellers below the
                                 minimum still see this theme and can still preview it — nobody
                                 upgrades for something they have never been shown — and anyone
                                 already running it keeps it if their tier later drops. A site
                                 changing design because a subscription lapsed is not a thing a
                                 funeral home would forgive, and they would hear it from a family. --}}
                            <form action="{{ route('settings.themes.tier', $theme) }}" method="POST" class="mt-4">
                                @csrf
                                <label for="tier-{{ $theme->id }}" class="mb-1.5 block text-xs font-medium text-gray-500 dark:text-gray-400">
                                    Minimum plan to apply
                                </label>
                                <div class="flex items-center gap-2">
                                    <select id="tier-{{ $theme->id }}" name="minimum_tier_id"
                                        class="h-9 flex-1 rounded-lg border border-gray-300 bg-transparent px-2 text-xs text-gray-700 focus:border-brand-500 focus:outline-hidden dark:border-gray-700 dark:text-gray-300">
                                        <option value="">Any plan</option>
                                        @foreach ($tiers as $tier)
                                            <option value="{{ $tier->id }}" @selected($theme->minimum_tier_id === $tier->id)>
                                                {{ $tier->name }} and above
                                            </option>
                                        @endforeach
                                    </select>
                                    <button type="submit" class="btn btn-secondary btn-sm">Save</button>
                                </div>
                                @if ($theme->minimum_tier_id && $entry['below_minimum'] > 0)
                                    <p class="mt-1.5 text-xs text-amber-600 dark:text-amber-400">
                                        {{ $entry['below_minimum'] }}
                                        {{ \Illuminate\Support\Str::plural('reseller', $entry['below_minimum']) }}
                                        already using this {{ $entry['below_minimum'] === 1 ? 'is' : 'are' }}
                                        below that plan. {{ $entry['below_minimum'] === 1 ? 'It keeps' : 'They keep' }}
                                        the theme; only applying it again would be blocked.
                                    </p>
                                @endif
                            </form>
                        @endif

                        <div class="mt-4 flex flex-wrap items-center gap-2 border-t border-gray-100 pt-4 dark:border-gray-800">
                            {{-- "See it" means a real site, not a mockup: there is no way to
                                 preview a template without a tenant yet, so this links to one
                                 actually running it. --}}
                            @if ($entry['example'])
                                <a href="{{ $entry['example']->publicBaseUrl() }}" target="_blank" rel="noopener" class="btn btn-secondary btn-sm">
                                    View live site
                                </a>
                            @else
                                <span class="text-xs text-gray-400 dark:text-gray-500">No site is using this yet</span>
                            @endif

                            @if (! $entry['unsynced'])
                                <form action="{{ route('settings.themes.toggle', $theme) }}" method="POST" class="ml-auto">
                                    @csrf
                                    <button type="submit" class="text-xs font-medium text-gray-500 hover:text-gray-800 dark:text-gray-400 dark:hover:text-white">
                                        {{ $theme->is_published ? 'Hide from gallery' : 'Publish' }}
                                    </button>
                                </form>
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>
        </x-common.component-card>

        {{-- Read-only on purpose: these belong to the tenants who made them. Here so a support
             question about "the look we had before" has an answer. --}}
        <x-common.component-card
            title="Resellers' own saved themes"
            desc="Looks a reseller has saved for themselves — a template plus their colours. Not editable here.">
            @if ($resellerThemes->isEmpty())
                <p class="text-sm text-gray-500 dark:text-gray-400">No reseller has saved a theme of their own yet.</p>
            @else
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="border-b border-gray-100 text-left text-xs uppercase tracking-wider text-gray-400 dark:border-gray-800">
                                <th class="py-2 pr-4 font-medium">Reseller</th>
                                <th class="py-2 pr-4 font-medium">Theme</th>
                                <th class="py-2 pr-4 font-medium">Template</th>
                                <th class="py-2 font-medium">Saved</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                            @foreach ($resellerThemes as $theme)
                                <tr class="text-gray-600 dark:text-gray-300">
                                    <td class="py-2.5 pr-4">
                                        <a href="{{ route('settings.resellers.show', $theme->reseller_id) }}" class="hover:text-brand-500">
                                            {{ $theme->reseller?->name ?? '—' }}
                                        </a>
                                    </td>
                                    <td class="py-2.5 pr-4">{{ $theme->name }}</td>
                                    <td class="py-2.5 pr-4 font-mono text-xs">
                                        {{ $theme->template }}
                                        @if ($theme->templateIsMissing())
                                            <span class="ml-1 text-red-600 dark:text-red-400">(missing)</span>
                                        @endif
                                    </td>
                                    <td class="py-2.5 text-xs text-gray-400">{{ $theme->created_at?->diffForHumans() }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </x-common.component-card>
    </div>
@endsection
