<?php

use App\PageBuilder\WidgetRegistry;
use App\Services\PageLayoutService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;

uses(RefreshDatabase::class);

/**
 * Character limits exist so a reseller editing a themed page cannot stretch a heading past what
 * the design was drawn for. That only works if two numbers agree: the one the property panel
 * enforces while typing, and the one the validator enforces on save.
 *
 * They are declared once (App\PageBuilder\Support\TextLimits) — but nothing stops a future
 * widget from hand-writing a field `max` and forgetting the rule, which would produce a counter
 * that counts to a limit nobody enforces. This asserts across every registered widget rather
 * than trusting each author, so the guarantee survives widgets nobody has written yet.
 */

it('backs every editor character limit with a validation rule of the same size', function () {
    $registry = app(WidgetRegistry::class);
    $problems = [];

    foreach ($registry->typeMap() as $type => $class) {
        $rules = $class::rules();

        foreach ($class::fieldSchema() as $field) {
            if (! isset($field['max'])) {
                continue;
            }

            $name = $field['name'];
            $rule = $rules[$name] ?? null;

            if ($rule === null) {
                $problems[] = "{$type}.{$name}: editor limits to {$field['max']} but there is no rule at all";

                continue;
            }

            $ruleText = is_array($rule) ? implode('|', $rule) : (string) $rule;

            if (! preg_match('/\bmax:(\d+)/', $ruleText, $m)) {
                $problems[] = "{$type}.{$name}: editor limits to {$field['max']}, rule has no max";
            } elseif ((int) $m[1] !== (int) $field['max']) {
                $problems[] = "{$type}.{$name}: editor says {$field['max']}, rule says {$m[1]}";
            }
        }
    }

    expect($problems)->toBe([]);
});

it('refuses to save a value longer than the widget allows', function () {
    $registry = app(WidgetRegistry::class);

    // Find any widget that declares a limit, so this keeps working as widgets come and go.
    $target = null;
    foreach ($registry->typeMap() as $type => $class) {
        foreach ($class::fieldSchema() as $field) {
            if (isset($field['max'])) {
                $target = [$type, $class, $field];
                break 2;
            }
        }
    }

    // Until the themed widgets land, nothing declares a limit and there is nothing to test.
    // Skipping beats a green assertion that proves nothing.
    if ($target === null) {
        test()->markTestSkipped('No widget declares a character limit yet.');
    }

    [$type, $class, $field] = $target;

    $document = ['widgets' => [[
        'type' => $type,
        'props' => [$field['name'] => str_repeat('a', $field['max'] + 1)],
    ]]];

    expect(fn () => app(PageLayoutService::class)->validateDocumentFromArray($document))
        ->toThrow(ValidationException::class);
});

it('accepts a value exactly at the limit', function () {
    $registry = app(WidgetRegistry::class);

    $target = null;
    foreach ($registry->typeMap() as $type => $class) {
        foreach ($class::fieldSchema() as $field) {
            if (isset($field['max'])) {
                $target = [$type, $field];
                break 2;
            }
        }
    }

    if ($target === null) {
        test()->markTestSkipped('No widget declares a character limit yet.');
    }

    [$type, $field] = $target;

    // The boundary is the design's measured wrap point, so it must be usable, not one short.
    $document = ['widgets' => [[
        'type' => $type,
        'props' => [$field['name'] => str_repeat('a', $field['max'])],
    ]]];

    $result = app(PageLayoutService::class)->validateDocumentFromArray($document);

    expect($result['widgets'][0]['props'][$field['name']])->toHaveLength($field['max']);
});

/*
|--------------------------------------------------------------------------
| The theme widgets belong to resellers, not to the platform
|--------------------------------------------------------------------------
*/

it('keeps the reseller theme widgets out of the platform palette', function () {
    $registry = app(WidgetRegistry::class);

    $platform = collect($registry->definitionsForEditor(false))->pluck('type');
    $reseller = collect($registry->definitionsForEditor(true))->pluck('type');

    // The registry discovers every class in app/PageBuilder/Widgets, which is what makes
    // adding a widget a one-file job — and what silently pushed the theme sections into the
    // platform admin's builder. The platform site is not themed, so they would render their
    // plain fallback there and never look like the design they exist for.
    $leaked = $platform->filter(fn ($t) => str_starts_with($t, 'section_'));

    expect($leaked)->toBeEmpty()
        ->and($reseller->count())->toBeGreaterThan($platform->count());

    // Every platform widget is still offered to resellers: the split withholds nothing from
    // them, it only stops the theme's widgets travelling the other way.
    expect($platform->diff($reseller))->toBeEmpty();
});

it('still renders a theme widget wherever a page already carries one', function () {
    // Hiding a widget from a palette is a decision about what to offer next, never about what
    // to do with what already exists. A page carrying one must keep rendering.
    $registry = app(WidgetRegistry::class);

    foreach (['section_banner', 'section_split', 'section_grid', 'section_contact'] as $type) {
        expect($registry->classForType($type))->not->toBeNull("{$type} stopped resolving");
    }
});

/*
|--------------------------------------------------------------------------
| Every field kind a widget declares can actually be rendered
|--------------------------------------------------------------------------
*/

it('has an editor control for every field kind the widgets declare', function () {
    $registry = app(WidgetRegistry::class);

    // section_grid declared `repeater` before anything could render it, so the Cards field
    // showed nothing at all and the six services on a themed home page were uneditable — the
    // widget looked finished from the outside and was not. Asserting the two sides agree costs
    // one test and catches the whole class.
    $renderer = file_get_contents(resource_path('views/pages/settings/pages/partials/field-renderer.blade.php'));

    $declared = [];

    foreach ($registry->definitionsForEditor(true) as $definition) {
        foreach ($definition['fields'] as $field) {
            $declared[$field['kind']] = $definition['type'];

            // Fields nested inside a repeater row need a control too.
            foreach ($field['item_fields'] ?? [] as $item) {
                $declared[$item['kind']] = $definition['type'].' (row)';
            }
        }
    }

    $missing = [];

    foreach ($declared as $kind => $usedBy) {
        if (! str_contains($renderer, "'{$kind}'")) {
            $missing[] = "{$kind} (declared by {$usedBy})";
        }
    }

    expect($missing)->toBe([]);
});
