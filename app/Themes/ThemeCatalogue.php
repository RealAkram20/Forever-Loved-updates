<?php

namespace App\Themes;

use App\Models\Theme;
use Illuminate\Support\Str;

/**
 * Keeps the platform catalogue in step with the templates on disk.
 *
 * A template is only *selectable* once there is a row pointing at it, and that row has to
 * appear without anyone remembering to create it — a theme that ships in a deploy and then
 * sits invisible until someone runs a command is a theme nobody uses. So both the
 * `themes:sync` command and the deploy migration call this, and it is safe to run repeatedly.
 *
 * The manifest is the source of truth for a *platform* row: name, description and tokens are
 * overwritten from the file on every sync, because the file is what shipped and the row is a
 * projection of it. Tenants' own saved themes are never touched — they have a reseller_id,
 * and nothing here looks at those.
 */
class ThemeCatalogue
{
    /**
     * @return array{created: array<int, string>, updated: array<int, string>, orphaned: array<int, string>}
     */
    public static function sync(): array
    {
        $registry = app(ThemeRegistry::class);
        $created = [];
        $updated = [];

        foreach ($registry->all() as $template => $manifest) {
            $existing = Theme::whereNull('reseller_id')->where('slug', $template)->first();

            $attributes = [
                'name' => $manifest->name,
                'template' => $template,
                'tokens' => $manifest->tokens,
            ];

            if ($existing) {
                $existing->fill($attributes)->save();
                $updated[] = $template;

                continue;
            }

            Theme::create($attributes + [
                'reseller_id' => null,
                'slug' => $template,
                'is_published' => true,
            ]);
            $created[] = $template;
        }

        // A catalogue row whose directory is gone still renders — as the base template, by
        // ActiveTheme::use(). Reported rather than deleted: the row may be the only record of
        // which resellers were on that theme, and deleting it would silently move their sites.
        $onDisk = array_keys($registry->all());
        $orphaned = Theme::whereNull('reseller_id')
            ->when($onDisk !== [], fn ($q) => $q->whereNotIn('template', $onDisk))
            ->pluck('slug')
            ->all();

        return ['created' => $created, 'updated' => $updated, 'orphaned' => $orphaned];
    }

    /**
     * The catalogue row for the base template — what a reseller who has chosen nothing is
     * actually running, and what the gallery marks as active for them.
     */
    public static function base(): ?Theme
    {
        return Theme::whereNull('reseller_id')->where('template', ThemeRegistry::BASE)->first();
    }

    /** A slug for a reseller's own saved theme that will not collide with their existing ones. */
    public static function uniqueSlugFor(int $resellerId, string $name): string
    {
        $base = Str::slug($name) ?: 'theme';
        $slug = $base;
        $n = 2;

        while (Theme::where('reseller_id', $resellerId)->where('slug', $slug)->exists()) {
            $slug = $base.'-'.$n++;
        }

        return $slug;
    }
}
