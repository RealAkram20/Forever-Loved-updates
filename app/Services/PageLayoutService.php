<?php

namespace App\Services;

use App\Models\Page;
use App\PageBuilder\WidgetRegistry;
use App\PageBuilder\Widgets\ParagraphWidget;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

class PageLayoutService
{
    public function __construct(
        private WidgetRegistry $registry
    ) {}

    /**
     * @return array{version: int, widgets: array<int, array{id: string, type: string, order: int, props: array<string, mixed>}>}
     */
    public function validateDocumentFromArray(array $decoded): array
    {
        if (! isset($decoded['widgets']) || ! is_array($decoded['widgets'])) {
            throw new InvalidArgumentException('Missing widgets array.');
        }

        $version = isset($decoded['version']) ? (int) $decoded['version'] : 1;
        $widgetsRaw = $decoded['widgets'];
        $normalized = [];

        foreach ($widgetsRaw as $index => $widget) {
            if (! is_array($widget) || ! isset($widget['type']) || ! is_string($widget['type'])) {
                continue;
            }
            $type = $widget['type'];
            $class = $this->registry->classForType($type);
            if ($class === null) {
                throw ValidationException::withMessages(['widgets' => ['Unknown widget type: '.$type]]);
            }

            $id = isset($widget['id']) && is_string($widget['id']) && preg_match('/^w_[a-zA-Z0-9_-]+$/', $widget['id'])
                ? $widget['id']
                : 'w_'.substr(bin2hex(random_bytes(6)), 0, 12);

            $props = is_array($widget['props'] ?? null) ? $widget['props'] : [];
            $defaults = $class::defaultProps();
            $merged = $defaults;
            foreach ($props as $k => $v) {
                $merged[$k] = $v;
            }

            $rules = $class::rules();
            $prefix = 'props.';
            $prefixed = [];
            foreach ($rules as $key => $rule) {
                $prefixed[$prefix.$key] = $rule;
            }
            Validator::make(['props' => $merged], $prefixed)->validate();

            $normalized[] = [
                'id' => $id,
                'type' => $type,
                'order' => $index,
                'props' => $merged,
            ];
        }

        return [
            'version' => max(1, $version),
            'widgets' => $normalized,
        ];
    }

    /**
     * Best-effort normalization for the live editor preview: merges defaults,
     * skips unknown widget types, never throws on invalid field values so a
     * half-typed document still renders.
     *
     * @return array{version: int, widgets: array<int, array{id: string, type: string, order: int, props: array<string, mixed>}>}
     */
    public function normalizeDocumentLenient(array $decoded): array
    {
        $widgetsRaw = is_array($decoded['widgets'] ?? null) ? $decoded['widgets'] : [];
        $normalized = [];

        foreach ($widgetsRaw as $index => $widget) {
            if (! is_array($widget) || ! is_string($widget['type'] ?? null)) {
                continue;
            }
            $class = $this->registry->classForType($widget['type']);
            if ($class === null) {
                continue;
            }

            $id = isset($widget['id']) && is_string($widget['id']) && preg_match('/^w_[a-zA-Z0-9_-]+$/', $widget['id'])
                ? $widget['id']
                : 'w_'.substr(bin2hex(random_bytes(6)), 0, 12);

            $props = is_array($widget['props'] ?? null) ? $widget['props'] : [];
            $merged = $class::defaultProps();
            foreach ($props as $k => $v) {
                $merged[$k] = $v;
            }

            $normalized[] = [
                'id' => $id,
                'type' => $widget['type'],
                'order' => $index,
                'props' => $merged,
            ];
        }

        return [
            'version' => max(1, (int) ($decoded['version'] ?? 1)),
            'widgets' => $normalized,
        ];
    }

    /**
     * @return array{version: int, widgets: array<int, array{id: string, type: string, order: int, props: array<string, mixed>}>}
     */
    public function validateDocumentFromJson(string $json): array
    {
        $decoded = json_decode($json, true);
        if (! is_array($decoded)) {
            throw new InvalidArgumentException('Invalid JSON.');
        }

        return $this->validateDocumentFromArray($decoded);
    }

    /**
     * Document passed to the page builder: stored layout, or one paragraph widget from legacy HTML body.
     *
     * @return array{version: int, widgets: array<int, mixed>}
     */
    public function initialDocumentForEditor(Page $page): array
    {
        $initial = is_array($page->layout) ? $page->layout : ['version' => 1, 'widgets' => []];
        if (! isset($initial['widgets']) || ! is_array($initial['widgets'])) {
            $initial['widgets'] = [];
        }
        $initial['version'] = $initial['version'] ?? 1;

        if ($initial['widgets'] === [] && is_string($page->content) && trim($page->content) !== '') {
            return $this->validateDocumentFromArray([
                'version' => max(1, (int) $initial['version']),
                'widgets' => [
                    [
                        'id' => 'w_'.substr(bin2hex(random_bytes(6)), 0, 12),
                        'type' => ParagraphWidget::type(),
                        'order' => 0,
                        'props' => [
                            'content' => $page->content,
                            'class' => '',
                        ],
                    ],
                ],
            ]);
        }

        return [
            'version' => max(1, (int) $initial['version']),
            'widgets' => $initial['widgets'],
        ];
    }
}
