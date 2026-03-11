<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ClaudeBioGeneratorService
{
    public function __construct(
        protected TemplateBioGeneratorService $templateGenerator
    ) {}

    public function generate(array $structuredData, ?int $memorialId = null, bool $noCache = false): array
    {
        $cacheKey = (!$noCache && $memorialId && config('services.anthropic.cache_ttl'))
            ? 'memorial_bio_claude_' . $memorialId . '_' . md5(json_encode($structuredData))
            : null;

        if ($cacheKey) {
            $cached = Cache::get($cacheKey);
            if ($cached !== null) {
                return $cached;
            }
        }

        if (!config('services.anthropic.enabled') || !config('services.anthropic.api_key')) {
            return $this->templateGenerator->generate($structuredData);
        }

        try {
            $result = $this->callClaude($structuredData);
            $result = $this->ensureNonEmptyOptions($result, $structuredData);
            if ($cacheKey && config('services.anthropic.cache_ttl')) {
                Cache::put($cacheKey, $result, config('services.anthropic.cache_ttl'));
            }
            return $result;
        } catch (\Throwable $e) {
            Log::warning('Claude bio generation failed, using template fallback', [
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    protected function callClaude(array $structuredData): array
    {
        $apiKey = config('services.anthropic.api_key');
        $model = config('services.anthropic.model', 'claude-3-5-sonnet-20241022');

        $jsonData = json_encode($structuredData, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        $systemPrompt = BioGeneratorPromptHelper::getSystemPrompt();
        $userPrompt = BioGeneratorPromptHelper::getUserPrompt($jsonData);

        $response = Http::withHeaders([
            'x-api-key' => $apiKey,
            'anthropic-version' => '2023-06-01',
            'content-type' => 'application/json',
        ])
            ->timeout(60)
            ->post('https://api.anthropic.com/v1/messages', [
                'model' => $model,
                'max_tokens' => 8192,
                'system' => $systemPrompt,
                'messages' => [
                    ['role' => 'user', 'content' => $userPrompt],
                ],
                'temperature' => 0.9,
            ]);

        if (!$response->successful()) {
            $body = $response->json() ?? [];
            $errorType = $body['error']['type'] ?? '';
            $errorMsg = $body['error']['message'] ?? $response->body();

            if ($response->status() === 401 || $errorType === 'authentication_error') {
                throw new \RuntimeException('AI_AUTH_ERROR: Invalid Anthropic API key. Please check your ANTHROPIC_API_KEY in .env');
            }
            if ($response->status() === 429 || $errorType === 'rate_limit_error') {
                throw new \RuntimeException('AI_RATE_LIMIT: API rate limit exceeded. Please wait a moment and try again.');
            }
            if (str_contains($errorMsg, 'credit balance') || str_contains($errorMsg, 'billing') || str_contains($errorMsg, 'quota') || $errorType === 'insufficient_quota') {
                throw new \RuntimeException('AI_NO_CREDITS: Your Anthropic account has no remaining credits. Please go to console.anthropic.com → Plans & Billing to purchase credits.');
            }
            if ($response->status() === 404 || $errorType === 'not_found_error') {
                throw new \RuntimeException("AI_MODEL_ERROR: Model '{$model}' not found. Check ANTHROPIC_MODEL in .env");
            }
            if ($response->status() === 529 || $errorType === 'overloaded_error') {
                throw new \RuntimeException('AI_OVERLOADED: Anthropic API is temporarily overloaded. Please try again in a few seconds.');
            }

            throw new \RuntimeException("AI_API_ERROR: Claude API error: {$errorMsg}");
        }

        $body = $response->json();
        $content = $body['content'] ?? [];
        $text = null;
        foreach ($content as $block) {
            if (($block['type'] ?? '') === 'text' && isset($block['text'])) {
                $text = $block['text'];
                break;
            }
        }

        if (!$text) {
            throw new \RuntimeException('AI_API_ERROR: Claude API returned no content');
        }

        $text = $this->extractJsonFromResponse($text);
        $decoded = json_decode($text, true);
        if (!is_array($decoded) || !isset($decoded['option_1'], $decoded['option_2'], $decoded['option_3'])) {
            throw new \RuntimeException('AI_API_ERROR: Claude returned an unexpected response format');
        }

        return [
            'option_1' => trim((string) ($decoded['option_1'] ?? '')),
            'option_2' => trim((string) ($decoded['option_2'] ?? '')),
            'option_3' => trim((string) ($decoded['option_3'] ?? '')),
        ];
    }

    protected function ensureNonEmptyOptions(array $result, array $structuredData): array
    {
        $o1 = trim($result['option_1'] ?? '');
        $o2 = trim($result['option_2'] ?? '');
        $o3 = trim($result['option_3'] ?? '');

        if ($o1 && $o2 && $o3) {
            return $result;
        }

        $template = $this->templateGenerator->generate($structuredData);
        $base = $template['option_1'] ?? '';

        if (!$base) {
            return $result;
        }

        return [
            'option_1' => $o1 ?: $base,
            'option_2' => $o2 ?: $base,
            'option_3' => $o3 ?: $base,
        ];
    }

    protected function extractJsonFromResponse(string $text): string
    {
        $text = trim($text);
        if (preg_match('/```(?:json)?\s*([\s\S]*?)```/', $text, $m)) {
            return trim($m[1]);
        }
        return $text;
    }
}
