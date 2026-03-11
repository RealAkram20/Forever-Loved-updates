<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class OpenAIBioGeneratorService
{
    public function __construct(
        protected TemplateBioGeneratorService $templateGenerator
    ) {}

    public function generate(array $structuredData, ?int $memorialId = null, bool $noCache = false): array
    {
        $cacheKey = (!$noCache && $memorialId && config('services.openai.cache_ttl'))
            ? 'memorial_bio_openai_' . $memorialId . '_' . md5(json_encode($structuredData))
            : null;

        if ($cacheKey) {
            $cached = Cache::get($cacheKey);
            if ($cached !== null) {
                return $cached;
            }
        }

        if (!config('services.openai.enabled') || !config('services.openai.api_key')) {
            return $this->templateGenerator->generate($structuredData);
        }

        try {
            $result = $this->callOpenAI($structuredData);
            $result = $this->ensureNonEmptyOptions($result, $structuredData);
            if ($cacheKey && config('services.openai.cache_ttl')) {
                Cache::put($cacheKey, $result, config('services.openai.cache_ttl'));
            }
            return $result;
        } catch (\Throwable $e) {
            Log::warning('OpenAI bio generation failed, using template fallback', [
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    protected function callOpenAI(array $structuredData): array
    {
        $apiKey = config('services.openai.api_key');
        $model = config('services.openai.model', 'gpt-4o-mini');

        $jsonData = json_encode($structuredData, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        $systemPrompt = BioGeneratorPromptHelper::getSystemPrompt();
        $userPrompt = BioGeneratorPromptHelper::getUserPrompt($jsonData);

        $response = Http::withToken($apiKey)
            ->timeout(60)
            ->post('https://api.openai.com/v1/chat/completions', [
                'model' => $model,
                'messages' => [
                    ['role' => 'system', 'content' => $systemPrompt],
                    ['role' => 'user', 'content' => $userPrompt],
                ],
                'response_format' => ['type' => 'json_object'],
                'temperature' => 0.9,
                'max_tokens' => 8192,
            ]);

        if (!$response->successful()) {
            $body = $response->json() ?? [];
            $errorCode = $body['error']['code'] ?? '';
            $errorMsg = $body['error']['message'] ?? $response->body();

            if ($response->status() === 401) {
                throw new \RuntimeException('AI_AUTH_ERROR: Invalid OpenAI API key. Please check your OPENAI_API_KEY in .env');
            }
            if ($response->status() === 429 || $errorCode === 'rate_limit_exceeded') {
                throw new \RuntimeException('AI_RATE_LIMIT: API rate limit exceeded. Please wait a moment and try again.');
            }
            if ($errorCode === 'insufficient_quota' || str_contains($errorMsg, 'quota') || str_contains($errorMsg, 'billing')) {
                throw new \RuntimeException('AI_NO_CREDITS: Your OpenAI account has no remaining credits. Please add funds at platform.openai.com/billing');
            }
            if ($response->status() === 404) {
                throw new \RuntimeException("AI_MODEL_ERROR: Model '{$model}' not found. Check OPENAI_MODEL in .env");
            }

            throw new \RuntimeException("AI_API_ERROR: OpenAI error: {$errorMsg}");
        }

        $body = $response->json();
        $text = $body['choices'][0]['message']['content'] ?? null;

        if (!$text) {
            throw new \RuntimeException('AI_API_ERROR: OpenAI returned no content');
        }

        $text = $this->extractJsonFromResponse($text);
        $decoded = json_decode($text, true);
        if (!is_array($decoded) || !isset($decoded['option_1'], $decoded['option_2'], $decoded['option_3'])) {
            throw new \RuntimeException('AI_API_ERROR: OpenAI returned an unexpected response format');
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
