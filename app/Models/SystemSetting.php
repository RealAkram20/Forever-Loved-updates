<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class SystemSetting extends Model
{
    protected $fillable = ['group', 'key', 'value', 'type'];

    private static array $defaults = [
        // Branding
        'branding.app_name' => ['value' => 'Forever Loved', 'type' => 'string', 'group' => 'branding'],
        'branding.tagline' => ['value' => 'Celebrate lives that matter', 'type' => 'string', 'group' => 'branding'],
        'branding.logo_path' => ['value' => '', 'type' => 'string', 'group' => 'branding'],
        'branding.logo_dark_path' => ['value' => '', 'type' => 'string', 'group' => 'branding'],
        'branding.favicon_path' => ['value' => '', 'type' => 'string', 'group' => 'branding'],
        'branding.primary_color' => ['value' => '#465fff', 'type' => 'string', 'group' => 'branding'],
        'branding.secondary_color' => ['value' => '#1e3a5f', 'type' => 'string', 'group' => 'branding'],
        'branding.accent_color' => ['value' => '#f59e0b', 'type' => 'string', 'group' => 'branding'],
        'branding.bg_light' => ['value' => '#f9fafb', 'type' => 'string', 'group' => 'branding'],
        'branding.bg_dark' => ['value' => '#101828', 'type' => 'string', 'group' => 'branding'],
        'branding.primary_light' => ['value' => '#465fff', 'type' => 'string', 'group' => 'branding'],
        'branding.primary_dark' => ['value' => '#1e3a5f', 'type' => 'string', 'group' => 'branding'],
        'branding.accent_light' => ['value' => '#f59e0b', 'type' => 'string', 'group' => 'branding'],
        'branding.accent_dark' => ['value' => '#f59e0b', 'type' => 'string', 'group' => 'branding'],
        'branding.button1_color' => ['value' => '#465fff', 'type' => 'string', 'group' => 'branding'],
        'branding.button2_color' => ['value' => '#ffffff', 'type' => 'string', 'group' => 'branding'],
        'branding.cta_bg_light' => ['value' => '#465fff', 'type' => 'string', 'group' => 'branding'],
        'branding.cta_bg_dark' => ['value' => '#3641f5', 'type' => 'string', 'group' => 'branding'],

        // AI
        'ai.enabled' => ['value' => '0', 'type' => 'boolean', 'group' => 'ai'],
        'ai.provider' => ['value' => 'openai', 'type' => 'string', 'group' => 'ai'],
        'ai.api_key' => ['value' => '', 'type' => 'encrypted', 'group' => 'ai'],
        'ai.model' => ['value' => 'gpt-4o-mini', 'type' => 'string', 'group' => 'ai'],
        'ai.max_requests_per_user_per_day' => ['value' => '10', 'type' => 'integer', 'group' => 'ai'],
        'ai.max_requests_per_user_per_month' => ['value' => '100', 'type' => 'integer', 'group' => 'ai'],
        'ai.max_tokens_per_request' => ['value' => '2000', 'type' => 'integer', 'group' => 'ai'],

        // Payments
        'payments.enabled' => ['value' => '0', 'type' => 'boolean', 'group' => 'payments'],
        'payments.currency' => ['value' => 'USD', 'type' => 'string', 'group' => 'payments'],
        'payments.stripe_enabled' => ['value' => '0', 'type' => 'boolean', 'group' => 'payments'],
        'payments.stripe_public_key' => ['value' => '', 'type' => 'string', 'group' => 'payments'],
        'payments.stripe_secret_key' => ['value' => '', 'type' => 'encrypted', 'group' => 'payments'],
        'payments.pesapal_enabled' => ['value' => '0', 'type' => 'boolean', 'group' => 'payments'],
        'payments.pesapal_consumer_key' => ['value' => '', 'type' => 'string', 'group' => 'payments'],
        'payments.pesapal_consumer_secret' => ['value' => '', 'type' => 'encrypted', 'group' => 'payments'],
        'payments.pesapal_environment' => ['value' => 'sandbox', 'type' => 'string', 'group' => 'payments'],
        'payments.pesapal_ipn_id' => ['value' => '', 'type' => 'string', 'group' => 'payments'],

        // SMTP / Email
        'smtp.enabled' => ['value' => '0', 'type' => 'boolean', 'group' => 'smtp'],
        'smtp.host' => ['value' => '', 'type' => 'string', 'group' => 'smtp'],
        'smtp.port' => ['value' => '587', 'type' => 'integer', 'group' => 'smtp'],
        'smtp.username' => ['value' => '', 'type' => 'string', 'group' => 'smtp'],
        'smtp.password' => ['value' => '', 'type' => 'encrypted', 'group' => 'smtp'],
        'smtp.encryption' => ['value' => 'tls', 'type' => 'string', 'group' => 'smtp'],
        'smtp.from_address' => ['value' => '', 'type' => 'string', 'group' => 'smtp'],
        'smtp.from_name' => ['value' => '', 'type' => 'string', 'group' => 'smtp'],

        // Notifications (enabled by default)
        'notifications.email_enabled' => ['value' => '1', 'type' => 'boolean', 'group' => 'notifications'],
        'notifications.push_enabled' => ['value' => '1', 'type' => 'boolean', 'group' => 'notifications'],
        'notifications.vapid_public_key' => ['value' => '', 'type' => 'string', 'group' => 'notifications'],
        'notifications.vapid_private_key' => ['value' => '', 'type' => 'encrypted', 'group' => 'notifications'],
    ];

    public static function get(string $key, mixed $default = null): mixed
    {
        $all = static::getAllCached();

        if (isset($all[$key])) {
            return static::castValue($all[$key]['value'], $all[$key]['type']);
        }

        if (isset(static::$defaults[$key])) {
            return static::castValue(static::$defaults[$key]['value'], static::$defaults[$key]['type']);
        }

        return $default;
    }

    public static function set(string $key, mixed $value, ?string $group = null, ?string $type = null): void
    {
        $defaults = static::$defaults[$key] ?? null;

        static::updateOrCreate(
            ['key' => $key],
            [
                'value' => (string) $value,
                'group' => $group ?? $defaults['group'] ?? 'general',
                'type' => $type ?? $defaults['type'] ?? 'string',
            ]
        );

        Cache::forget('system_settings');
    }

    public static function getByGroup(string $group): array
    {
        $all = static::getAllCached();
        $result = [];

        foreach (static::$defaults as $key => $meta) {
            if ($meta['group'] === $group) {
                $value = $all[$key]['value'] ?? $meta['value'];
                $castType = $all[$key]['type'] ?? $meta['type'];
                $result[$key] = static::castValue($value, $castType);
            }
        }

        return $result;
    }

    public static function getAllCached(): array
    {
        return Cache::remember('system_settings', 3600, function () {
            return static::all()->keyBy('key')->map(fn ($s) => [
                'value' => $s->value,
                'type' => $s->type,
                'group' => $s->group,
            ])->toArray();
        });
    }

    public static function clearCache(): void
    {
        Cache::forget('system_settings');
    }

    private static function castValue(string $value, string $type): mixed
    {
        return match ($type) {
            'boolean' => (bool) $value,
            'integer' => (int) $value,
            'float' => (float) $value,
            'json' => json_decode($value, true),
            'encrypted' => $value,
            default => $value,
        };
    }

    public static function getDefaults(): array
    {
        return static::$defaults;
    }
}
