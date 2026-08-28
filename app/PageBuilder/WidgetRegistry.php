<?php

namespace App\PageBuilder;

use App\PageBuilder\Contracts\PageWidgetContract;
use InvalidArgumentException;

class WidgetRegistry
{
    /** @var array<string, class-string<PageWidgetContract>> */
    private array $map = [];

    private bool $discovered = false;

    /**
     * @return array<string, class-string<PageWidgetContract>>
     */
    public function typeMap(): array
    {
        $this->discover();

        return $this->map;
    }

    public function classForType(string $type): ?string
    {
        return $this->typeMap()[$type] ?? null;
    }

    /**
     * Register a widget class at runtime (e.g. from a service provider).
     *
     * @param class-string<PageWidgetContract> $class
     */
    public function register(string $class): void
    {
        if (! is_subclass_of($class, PageWidgetContract::class)) {
            throw new InvalidArgumentException('Invalid page widget class: '.$class);
        }

        $this->map[$class::type()] = $class;
    }

    /**
     * JSON payload for the page builder UI.
     *
     * @param  bool  $forReseller  true inside a reseller's own builder, where the theme section
     *                             widgets belong. The platform's builder does not offer them:
     *                             the platform site is not themed, so they would render their
     *                             plain fallback and never look like the design they exist for,
     *                             and they duplicate `hero` and `features_grid` for that
     *                             audience anyway.
     * @return array<int, array<string, mixed>>
     */
    public function definitionsForEditor(bool $forReseller = false): array
    {
        $out = [];
        foreach ($this->typeMap() as $class) {
            if (! $forReseller && is_subclass_of($class, \App\PageBuilder\Contracts\ResellerWidget::class)) {
                continue;
            }

            $out[] = [
                'type' => $class::type(),
                'label' => $class::label(),
                'category' => $class::category(),
                'defaultProps' => $class::defaultProps(),
                'fields' => $class::fieldSchema(),
                'previewFields' => $class::previewFields(),
            ];
        }

        return $out;
    }

    /**
     * Auto-discover all classes in app/PageBuilder/Widgets that implement PageWidgetContract.
     */
    private function discover(): void
    {
        if ($this->discovered) {
            return;
        }
        $this->discovered = true;

        $widgetsPath = app_path('PageBuilder/Widgets');
        if (! is_dir($widgetsPath)) {
            return;
        }

        foreach (glob($widgetsPath.'/*.php') as $file) {
            $className = 'App\\PageBuilder\\Widgets\\'.pathinfo($file, PATHINFO_FILENAME);
            if (! class_exists($className)) {
                continue;
            }
            if (! is_subclass_of($className, PageWidgetContract::class)) {
                continue;
            }
            $this->map[$className::type()] = $className;
        }
    }
}
