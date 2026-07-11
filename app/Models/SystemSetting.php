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
        'branding.button1_text_color' => ['value' => '#ffffff', 'type' => 'string', 'group' => 'branding'],
        'branding.button1_color_dark' => ['value' => '#465fff', 'type' => 'string', 'group' => 'branding'],
        'branding.button1_text_color_dark' => ['value' => '#ffffff', 'type' => 'string', 'group' => 'branding'],
        'branding.button2_color' => ['value' => '#ffffff', 'type' => 'string', 'group' => 'branding'],
        'branding.button2_text_color' => ['value' => '#374151', 'type' => 'string', 'group' => 'branding'],
        'branding.button2_color_dark' => ['value' => '#1f2937', 'type' => 'string', 'group' => 'branding'],
        'branding.button2_text_color_dark' => ['value' => '#d1d5db', 'type' => 'string', 'group' => 'branding'],
        'branding.cta_bg_light' => ['value' => '#465fff', 'type' => 'string', 'group' => 'branding'],
        'branding.cta_bg_dark' => ['value' => '#3641f5', 'type' => 'string', 'group' => 'branding'],
        // The CTA banner sits on its own colored background, so its two buttons are styled
        // independently of the site-wide primary/secondary pair.
        'branding.cta_btn1_color' => ['value' => '#ffffff', 'type' => 'string', 'group' => 'branding'],
        'branding.cta_btn1_text_color' => ['value' => '#465fff', 'type' => 'string', 'group' => 'branding'],
        'branding.cta_btn1_color_dark' => ['value' => '#ffffff', 'type' => 'string', 'group' => 'branding'],
        'branding.cta_btn1_text_color_dark' => ['value' => '#1e3a5f', 'type' => 'string', 'group' => 'branding'],
        'branding.cta_btn2_color' => ['value' => '#3641f5', 'type' => 'string', 'group' => 'branding'],
        'branding.cta_btn2_text_color' => ['value' => '#ffffff', 'type' => 'string', 'group' => 'branding'],
        'branding.cta_btn2_color_dark' => ['value' => '#1e3a5f', 'type' => 'string', 'group' => 'branding'],
        'branding.cta_btn2_text_color_dark' => ['value' => '#ffffff', 'type' => 'string', 'group' => 'branding'],
        'branding.default_theme' => ['value' => 'light', 'type' => 'string', 'group' => 'branding'],

        // OAuth (Google sign-in — credentials can also be set via .env)
        'oauth.google_enabled' => ['value' => '0', 'type' => 'boolean', 'group' => 'oauth'],
        'oauth.google_client_id' => ['value' => '', 'type' => 'string', 'group' => 'oauth'],
        'oauth.google_client_secret' => ['value' => '', 'type' => 'encrypted', 'group' => 'oauth'],

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
        $resolvedType = $type ?? $defaults['type'] ?? 'string';

        $stored = (string) $value;
        if ($resolvedType === 'encrypted' && $stored !== '') {
            $stored = \Illuminate\Support\Facades\Crypt::encryptString($stored);
        }

        static::updateOrCreate(
            ['key' => $key],
            [
                'value' => $stored,
                'group' => $group ?? $defaults['group'] ?? 'general',
                'type' => $resolvedType,
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
            'boolean' => filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? false,
            'integer' => (int) $value,
            'float' => (float) $value,
            'json' => json_decode($value, true),
            'encrypted' => static::decryptValue($value),
            default => $value,
        };
    }

    /**
     * Secrets are encrypted at rest. Legacy rows saved before encryption are
     * plaintext — return those as-is. Ciphertext that no longer decrypts
     * (APP_KEY changed) is treated as unset so features cleanly disable
     * instead of erroring mid-flow; the admin just re-saves the secret.
     */
    private static function decryptValue(string $value): string
    {
        if ($value === '') {
            return '';
        }

        try {
            return \Illuminate\Support\Facades\Crypt::decryptString($value);
        } catch (\Throwable) {
            $decoded = json_decode(base64_decode($value, true) ?: '', true);
            $looksEncrypted = is_array($decoded) && isset($decoded['iv'], $decoded['value'], $decoded['mac']);

            if ($looksEncrypted) {
                \Illuminate\Support\Facades\Log::warning('System setting secret could not be decrypted (APP_KEY changed?) — treating as unset. Re-save it in admin settings.');

                return '';
            }

            return $value; // legacy plaintext value
        }
    }

    public static function getDefaults(): array
    {
        return static::$defaults;
    }
}
