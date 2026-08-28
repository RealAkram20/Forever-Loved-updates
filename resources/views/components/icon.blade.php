{{--
    <x-icon name="phone" class="h-5 w-5" />

    Renders nothing at all for an unknown name rather than a placeholder box — an icon is
    decoration beside a label, and a missing one should cost the reader nothing.

    @param string      $name
    @param string|null $stroke  Override Lucide's default stroke of 2.
--}}
@props(['name', 'stroke' => null])

{!! \App\Support\Icon::svg($name, $attributes->get('class', 'h-5 w-5'), $stroke) !!}
