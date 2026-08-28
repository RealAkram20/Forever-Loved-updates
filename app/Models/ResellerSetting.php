<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

/**
 * A reseller's own value for one of the platform's appearance settings.
 *
 * Mirrors SystemSetting's read path — one cached array per tenant rather than a query per
 * key — because BrandingHelper::brandColorCss() alone reads about forty keys to build a
 * single <style> block, on every page load.
 *
 * Resolution (reseller override, then platform default) lives in App\Support\ThemeSetting,
 * not here. This model only answers "what has this reseller set", which is why absence and
 * emptiness are kept distinguishable: an empty value is a real choice ("use the theme
 * default font"), while an absent key means "inherit whatever the platform uses".
 */
class ResellerSetting extends Model
{
    protected $fillable = ['reseller_id', 'key', 'value', 'type'];

    private static function cacheKey(int $resellerId): string
    {
        return "reseller_settings.{$resellerId}";
    }

    /**
     * Every override this reseller has, keyed by setting key.
     *
     * @return array<string, array{value: ?string, type: string}>
     */
    public static function allFor(int $resellerId): array
    {
        return Cache::remember(self::cacheKey($resellerId), 3600, function () use ($resellerId) {
            return static::where('reseller_id', $resellerId)
                ->get()
                ->keyBy('key')
                ->map(fn ($s) => ['value' => $s->value, 'type' => $s->type])
                ->toArray();
        });
    }

    /** Whether this reseller has expressed any opinion about this key, empty included. */
    public static function has(int $resellerId, string $key): bool
    {
        return array_key_exists($key, self::allFor($resellerId));
    }

    public static function set(int $resellerId, string $key, mixed $value, ?string $type = null): void
    {
        static::updateOrCreate(
            ['reseller_id' => $resellerId, 'key' => $key],
            [
                'value' => (string) $value,
                // Falls back to the platform's declared type for the same key, so a reseller's
                // integer stays an integer without restating the type registry.
                'type' => $type ?? (SystemSetting::getDefaults()[$key]['type'] ?? 'string'),
            ]
        );

        self::clearCache($resellerId);
    }

    /** Drop one override so the platform value is inherited again. */
    public static function forget(int $resellerId, string $key): void
    {
        static::where('reseller_id', $resellerId)->where('key', $key)->delete();

        self::clearCache($resellerId);
    }

    /** Drop every override — the "reset to platform defaults" action. */
    public static function forgetAll(int $resellerId): void
    {
        static::where('reseller_id', $resellerId)->delete();

        self::clearCache($resellerId);
    }

    /**
     * Drop only the named settings, leaving everything else this tenant has saved.
     *
     * Exists because forgetAll() is almost never what a caller means. Every reseller setting
     * shares one table — colours and fonts alongside the business's phone numbers, address,
     * opening hours, map and social links — so "reset the appearance" run through forgetAll()
     * deleted a funeral home's contact details, which no part of that button claimed to touch
     * and which nothing could give back.
     *
     * @param  array<int, string>  $keys
     */
    public static function forgetKeys(int $resellerId, array $keys): void
    {
        if ($keys === []) {
            return;
        }

        static::where('reseller_id', $resellerId)->whereIn('key', $keys)->delete();

        self::clearCache($resellerId);
    }

    public static function clearCache(int $resellerId): void
    {
        Cache::forget(self::cacheKey($resellerId));
    }
}
