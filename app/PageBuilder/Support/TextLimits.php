<?php

namespace App\PageBuilder\Support;

/**
 * One declaration of "how long may this text be", used by both the validator and the editor.
 *
 * A theme's design survives a reseller editing it only if the fields refuse to grow past what
 * the layout was drawn for. `rules()` already enforced that server-side, but the property
 * panel knew nothing about it: you typed four hundred characters into a heading built for
 * forty, watched the preview render them, hit save, and got "The props.heading may not be
 * greater than 40 characters" — naming a field that is not on screen, after the work was done.
 *
 * So a widget declares its limits once, as `slug => characters`, and hands the same array to
 * both sides. The two cannot disagree, and `ThemeWidgetLimitsTest` asserts that across every
 * registered widget rather than trusting each author to remember.
 */
class TextLimits
{
    /**
     * Validation rules for the limited fields.
     *
     * @param  array<string, int>  $limits   prop name => maximum characters
     * @param  array<int, string>  $required prop names that may not be blank
     * @return array<string, string>
     */
    public static function rules(array $limits, array $required = []): array
    {
        $out = [];

        foreach ($limits as $name => $max) {
            $out[$name] = (in_array($name, $required, true) ? 'required' : 'nullable')
                .'|string|max:'.$max;
        }

        return $out;
    }

    /**
     * Stamp the same maximum onto the matching editor fields.
     *
     * Only `text`, `textarea` and `richtext` carry a counter — a limit on a select or a colour
     * would be meaningless, and silently doing nothing is worse than not offering it.
     *
     * @param  array<int, array<string, mixed>>  $fields
     * @param  array<string, int>  $limits
     * @return array<int, array<string, mixed>>
     */
    public static function applyToFields(array $fields, array $limits): array
    {
        $countable = ['text', 'textarea', 'richtext'];

        foreach ($fields as $i => $field) {
            $name = $field['name'] ?? null;

            if ($name === null || ! isset($limits[$name])) {
                continue;
            }

            if (! in_array($field['kind'] ?? '', $countable, true)) {
                continue;
            }

            $fields[$i]['max'] = $limits[$name];
        }

        return $fields;
    }

    /**
     * Fields inside a repeater carry their limits on the item schema instead, since the
     * counter has to attach to each row rather than the widget.
     *
     * @param  array<int, array<string, mixed>>  $itemFields
     * @param  array<string, int>  $limits
     * @return array<int, array<string, mixed>>
     */
    public static function applyToItemFields(array $itemFields, array $limits): array
    {
        return self::applyToFields($itemFields, $limits);
    }
}
