<?php

namespace App\PageBuilder\Support;

/**
 * Builds the wrapper attributes (style, class, id, visibility) for a rendered
 * widget from its reserved props: _spacing, _style, _advanced.
 */
class WidgetChrome
{
    public static function isHidden(array $props): bool
    {
        return (bool) ($props['_advanced']['hidden'] ?? false);
    }

    public static function cssId(array $props): ?string
    {
        $id = $props['_advanced']['css_id'] ?? null;
        if (! is_string($id)) {
            return null;
        }
        $id = preg_replace('/[^A-Za-z0-9_\-]/', '', trim($id));

        return $id !== '' ? $id : null;
    }

    public static function cssClass(array $props): ?string
    {
        $class = $props['_advanced']['css_class'] ?? null;
        if (! is_string($class)) {
            return null;
        }
        $class = trim(preg_replace('/[^A-Za-z0-9_\-\s]/', '', $class) ?? '');

        return $class !== '' ? $class : null;
    }

    /**
     * Inline style string for the wrapper: margin/padding + background/text colour/alignment.
     */
    public static function wrapperStyle(array $props): string
    {
        $parts = self::spacingParts($props);

        $style = $props['_style'] ?? null;
        if (is_array($style)) {
            $bg = self::sanitizeColor($style['background_color'] ?? null);
            if ($bg !== null) {
                $parts[] = 'background-color:'.$bg;
            }
            $color = self::sanitizeColor($style['text_color'] ?? null);
            if ($color !== null) {
                $parts[] = 'color:'.$color;
            }
            $align = $style['text_align'] ?? null;
            if (is_string($align) && in_array($align, ['left', 'center', 'right', 'justify'], true)) {
                $parts[] = 'text-align:'.$align;
            }
        }

        return implode(';', $parts);
    }

    /**
     * @return array<int, string>
     */
    private static function spacingParts(array $props): array
    {
        $spacing = $props['_spacing'] ?? null;
        if (! is_array($spacing)) {
            return [];
        }

        $parts = [];
        foreach (['margin', 'padding'] as $group) {
            $box = $spacing[$group] ?? null;
            if (! is_array($box)) {
                continue;
            }
            $t = self::sanitizeLength($box['top'] ?? '0');
            $r = self::sanitizeLength($box['right'] ?? '0');
            $b = self::sanitizeLength($box['bottom'] ?? '0');
            $l = self::sanitizeLength($box['left'] ?? '0');
            $u = in_array($box['unit'] ?? 'px', ['px', 'em', '%', 'rem'], true) ? $box['unit'] : 'px';
            if ($t === '0' && $r === '0' && $b === '0' && $l === '0') {
                continue;
            }
            $parts[] = $group.':'.$t.$u.' '.$r.$u.' '.$b.$u.' '.$l.$u;
        }

        return $parts;
    }

    private static function sanitizeLength(mixed $value): string
    {
        $value = preg_replace('/[^0-9.\-]/', '', (string) $value) ?? '';

        return $value === '' ? '0' : $value;
    }

    private static function sanitizeColor(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }
        $value = trim($value);
        if ($value === '' || ! preg_match('/^[#a-zA-Z0-9(),.%\s\-]{1,64}$/', $value)) {
            return null;
        }

        return $value;
    }
}
