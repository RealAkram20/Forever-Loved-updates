<?php

namespace App\PageBuilder\Support;

use Illuminate\Support\Str;

class FieldSchema
{
    /**
     * @param  array<string, mixed>  $defaults
     * @return array<int, array<string, mixed>>
     */
    public static function infer(array $defaults): array
    {
        $out = [];
        foreach ($defaults as $name => $value) {
            $label = Str::headline(str_replace('_', ' ', (string) $name));
            if (is_bool($value)) {
                $out[] = ['name' => $name, 'kind' => 'checkbox', 'label' => $label];
            } elseif (is_array($value)) {
                $out[] = ['name' => $name, 'kind' => 'json', 'label' => $label.' (JSON)'];
            } elseif (is_int($value) && str_contains((string) $name, 'level')) {
                $out[] = ['name' => $name, 'kind' => 'select', 'label' => $label, 'options' => [1, 2, 3]];
            } elseif (is_int($value) || is_float($value)) {
                $out[] = ['name' => $name, 'kind' => 'number', 'label' => $label];
            } else {
                $out[] = ['name' => $name, 'kind' => 'text', 'label' => $label];
            }
        }

        return $out;
    }
}
