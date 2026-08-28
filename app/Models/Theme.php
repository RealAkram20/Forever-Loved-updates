<?php

namespace App\Models;

use App\Helpers\AppearanceKeys;
use App\Themes\ThemeManifest;
use App\Themes\ThemeRegistry;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A theme a reseller can choose: a template on disk, plus the palette it runs with.
 *
 * See the create_themes_table migration for why those two halves are separate. The short
 * version: templates are code and ship with a deploy, rows are choices and are made in the
 * UI, and a reseller composes the second without ever authoring the first.
 */
class Theme extends Model
{
    protected $fillable = [
        'reseller_id',
        'name',
        'slug',
        'template',
        'tokens',
        'is_published',
        'minimum_tier_id',
        'created_by_user_id',
    ];

    protected function casts(): array
    {
        return [
            'tokens' => 'array',
            'is_published' => 'boolean',
        ];
    }

    public function reseller(): BelongsTo
    {
        return $this->belongsTo(Reseller::class);
    }

    /** The lowest tier that may apply this theme, or null when it is open to everyone. */
    public function minimumTier(): BelongsTo
    {
        return $this->belongsTo(ResellerTier::class, 'minimum_tier_id');
    }

    /**
     * Whether this reseller may *apply* this theme.
     *
     * Deliberately separate from selectableFor(), which answers what they may see and try.
     * A gated theme stays in their gallery and stays previewable — that is how someone
     * decides an upgrade is worth it, and hiding it would make the plan page argue for
     * something invisible.
     *
     * A reseller on no tier at all is treated as below every tier: "not on a plan" is the
     * most restrictive state, not an exemption from one. Nothing changes for them until an
     * admin actually gates a theme, because ungated is the default and stays it.
     */
    public function isAvailableTo(?Reseller $reseller): bool
    {
        // Their own saved look. Never gated — it is theirs, and it is built from what they
        // were already running.
        if ($this->reseller_id !== null) {
            return $this->reseller_id === $reseller?->id;
        }

        if ($this->minimum_tier_id === null) {
            return true;
        }

        $required = $this->minimumTier?->sort_order;
        $held = $reseller?->tier?->sort_order;

        // A minimum pointing at a tier that no longer exists reads as ungated rather than as
        // locked-to-nobody, matching the migration's nullOnDelete for the same reason.
        if ($required === null) {
            return true;
        }

        return $held !== null && $held >= $required;
    }

    /** Part of the catalogue everyone sees, as opposed to one tenant's own saved look. */
    public function isPlatform(): bool
    {
        return $this->reseller_id === null;
    }

    /**
     * What this reseller may pick from: the published platform catalogue, plus anything they
     * have saved themselves. Never another tenant's.
     */
    public static function selectableFor(?int $resellerId): Collection
    {
        return static::query()
            ->where(function (Builder $q) use ($resellerId) {
                $q->whereNull('reseller_id')->where('is_published', true);

                if ($resellerId !== null) {
                    $q->orWhere('reseller_id', $resellerId);
                }
            })
            ->orderByRaw('reseller_id is null desc')
            ->orderBy('name')
            ->get();
    }

    /**
     * The colours and fonts this theme sets, filtered to keys the appearance vocabulary
     * knows.
     *
     * Filtered on read as well as on write. These values are interpolated into a `<style>`
     * block, and the write side is an admin form and a JSON file — neither of which is a
     * guarantee about what is already in the column.
     *
     * @return array<string, string>
     */
    public function tokenValues(): array
    {
        $tokens = is_array($this->tokens) ? $this->tokens : [];

        if ($tokens === []) {
            return [];
        }

        $allowed = array_flip(AppearanceKeys::resellerWritable());

        return array_filter(
            array_map(fn ($v) => is_scalar($v) ? (string) $v : null, $tokens),
            fn ($value, $key) => $value !== null && isset($allowed[$key]),
            ARRAY_FILTER_USE_BOTH
        );
    }

    /** The template directory this theme renders with, or the base when it has gone missing. */
    public function templateSlug(): string
    {
        return app(ThemeRegistry::class)->exists((string) $this->template)
            ? (string) $this->template
            : ThemeRegistry::BASE;
    }

    public function manifest(): ?ThemeManifest
    {
        return app(ThemeRegistry::class)->manifest($this->templateSlug());
    }

    /**
     * True when the row points at a template directory that is not there — a deploy that
     * dropped a theme, or a hand-edited column. Surfaced in the UI rather than silently
     * corrected, because the site is rendering in a design nobody chose.
     */
    public function templateIsMissing(): bool
    {
        return ! app(ThemeRegistry::class)->exists((string) $this->template);
    }
}
