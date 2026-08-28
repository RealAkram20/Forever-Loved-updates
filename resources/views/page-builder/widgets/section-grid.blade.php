{{-- Card grid — the plain rendering, which every theme inherits until it overrides this. --}}
@php
    use App\PageBuilder\Support\SectionRender as R;

    $items = collect($props['items'] ?? [])
        ->filter(fn ($i) => is_array($i) && (filled($i['title'] ?? '') || filled($i['text'] ?? '')))
        ->values();

    $buttons = R::buttons($props);
    $centred = ($props['alignment'] ?? 'center') === 'center';
    $cols = (int) ($props['columns'] ?? 3);
    $colClass = match ($cols) {
        2 => 'sm:grid-cols-2',
        4 => 'sm:grid-cols-2 lg:grid-cols-4',
        default => 'sm:grid-cols-2 lg:grid-cols-3',
    };
@endphp

<section class="{{ R::background($props) }} {{ R::padding($props) }}">
    <div class="mx-auto {{ R::width($props) }} px-4 sm:px-6 lg:px-8">
        <div class="{{ $centred ? 'text-center' : '' }}">
            @if (filled($props['eyebrow'] ?? ''))
                <p class="ap-eyebrow text-sm font-semibold uppercase tracking-wider text-brand-600 dark:text-brand-400">{{ $props['eyebrow'] }}</p>
            @endif

            @if (filled($props['heading'] ?? ''))
                <h2 class="ap-title mt-2 text-3xl font-bold text-gray-900 dark:text-white sm:text-4xl">{{ $props['heading'] }}</h2>
            @endif

            @if (filled($props['body'] ?? ''))
                <p class="mx-auto mt-4 max-w-2xl text-gray-600 dark:text-gray-300">{{ $props['body'] }}</p>
            @endif
        </div>

        @if ($items->isNotEmpty())
            <div class="mt-10 grid grid-cols-1 gap-5 {{ $colClass }}">
                @foreach ($items as $item)
                    @php $url = filled($item['url'] ?? '') ? R::url($item['url']) : null; @endphp

                    {{-- A card without a link is a <div>, not an <a> going nowhere: a pointer
                         cursor that leads nowhere is a small lie the whole page pays for. --}}
                    <{{ $url ? 'a' : 'div' }} @if ($url) href="{{ $url }}" @endif
                        class="flex flex-col rounded-xl border border-gray-200 bg-white p-6 transition-colors duration-200 ease-out dark:border-gray-800 dark:bg-white/[0.03] {{ $url ? 'hover:border-brand-400' : '' }} {{ $centred ? 'items-center text-center' : '' }}">
                        @if (filled($item['icon'] ?? ''))
                            <span class="mb-4 text-brand-600 dark:text-brand-400">
                                <x-icon :name="$item['icon']" class="h-8 w-8" />
                            </span>
                        @endif

                        @if (filled($item['title'] ?? ''))
                            <h3 class="text-base font-semibold text-gray-900 dark:text-white">{{ $item['title'] }}</h3>
                        @endif

                        @if (filled($item['text'] ?? ''))
                            <p class="mt-2 text-sm leading-relaxed text-gray-600 dark:text-gray-300">{{ $item['text'] }}</p>
                        @endif
                    </{{ $url ? 'a' : 'div' }}>
                @endforeach
            </div>
        @endif

        @if ($buttons)
            <div class="mt-8 flex flex-wrap gap-3 {{ $centred ? 'justify-center' : '' }}">
                @foreach ($buttons as $button)
                    <a href="{{ $button['url'] }}" class="btn {{ $button['primary'] ? 'btn-primary' : 'btn-secondary' }} btn-md">{{ $button['label'] }}</a>
                @endforeach
            </div>
        @endif
    </div>
</section>
