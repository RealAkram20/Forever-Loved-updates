<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class GeminiBioGeneratorService
{
    public function __construct(
        protected TemplateBioGeneratorService $templateGenerator
    ) {}

    public function generate(array $structuredData, ?int $memorialId = null, bool $noCache = false): array
    {
        $cacheKey = (!$noCache && $memorialId && config('services.gemini.cache_ttl'))
            ? 'memorial_bio_' . $memorialId . '_' . md5(json_encode($structuredData))
            : null;

        if ($cacheKey) {
            $cached = Cache::get($cacheKey);
            if ($cached !== null) {
                return $cached;
            }
        }

        if (!config('services.gemini.enabled') || !config('services.gemini.api_key')) {
            return $this->templateGenerator->generate($structuredData);
        }

        try {
            $result = $this->callGemini($structuredData);
            if ($cacheKey && config('services.gemini.cache_ttl')) {
                Cache::put($cacheKey, $result, config('services.gemini.cache_ttl'));
            }
            return $result;
        } catch (\Throwable $e) {
            Log::warning('Gemini bio generation failed, using template fallback', [
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    protected function callGemini(array $structuredData): array
    {
        $models = array_filter(array_unique([
            config('services.gemini.model', 'gemini-2.5-flash'),
            'gemini-2.5-flash',
            'gemini-2.0-flash',
        ]));
        $apiKey = config('services.gemini.api_key');
        $lastError = null;

        $jsonData = json_encode($structuredData, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        $systemInstruction = BioGeneratorPromptHelper::getSystemPrompt();
        $userPrompt = BioGeneratorPromptHelper::getUserPrompt($jsonData);

        $payload = [
            'contents' => [
                [
                    'parts' => [
                        ['text' => $userPrompt],
                    ],
                ],
            ],
            'generationConfig' => [
                'temperature' => 0.9,
                'topK' => 40,
                'topP' => 0.95,
                'maxOutputTokens' => 8192,
                'responseMimeType' => 'application/json',
            ],
            'systemInstruction' => [
                'parts' => [
                    ['text' => $systemInstruction],
                ],
            ],
        ];

        foreach ($models as $model) {
            $url = "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent?key={$apiKey}";
            $response = Http::timeout(30)->post($url, $payload);

            if (!$response->successful()) {
                $lastError = $response->body();
                $body = $response->json() ?? [];
                $errorMsg = $body['error']['message'] ?? $lastError;
                $status = $response->status();

                if ($status === 404 && str_contains($lastError, 'not found')) {
                    continue;
                }
                if ($status === 401 || $status === 403) {
                    throw new \RuntimeException('AI_AUTH_ERROR: Invalid Gemini API key. Please check your GEMINI_API_KEY in .env');
                }
                if ($status === 429) {
                    throw new \RuntimeException('AI_RATE_LIMIT: Gemini API rate limit exceeded. Please wait a moment and try again.');
                }

                throw new \RuntimeException("AI_API_ERROR: Gemini error: {$errorMsg}");
            }

            $body = $response->json();
            $text = $body['candidates'][0]['content']['parts'][0]['text'] ?? null;

            if (!$text) {
                throw new \RuntimeException('AI_API_ERROR: Gemini returned no content');
            }

            $text = $this->extractJsonFromResponse($text);
            $decoded = json_decode($text, true);
            if (!is_array($decoded) || !isset($decoded['option_1'], $decoded['option_2'], $decoded['option_3'])) {
                throw new \RuntimeException('AI_API_ERROR: Gemini returned an unexpected response format');
            }

            return [
                'option_1' => trim((string) ($decoded['option_1'] ?? '')),
                'option_2' => trim((string) ($decoded['option_2'] ?? '')),
                'option_3' => trim((string) ($decoded['option_3'] ?? '')),
            ];
        }

        throw new \RuntimeException("AI_MODEL_ERROR: No Gemini model available. Last error: {$lastError}");
    }

    protected function extractJsonFromResponse(string $text): string
    {
        $text = trim($text);
        if (preg_match('/```(?:json)?\s*([\s\S]*?)```/', $text, $m)) {
            return trim($m[1]);
        }
        return $text;
    }

    public static function buildStructuredDataFromMemorial(\App\Models\Memorial $memorial): array
    {
        $fullName = trim($memorial->full_name ?? '');
        $nationality = trim($memorial->nationality ?? '');

        $birthPlace = self::cleanPlaceName(trim(implode(', ', array_filter([
            $memorial->birth_city,
            $memorial->birth_state,
            $memorial->birth_country,
        ])))) ?: null;

        $deathPlace = self::cleanPlaceName(trim(implode(', ', array_filter([
            $memorial->death_city,
            $memorial->death_state,
            $memorial->death_country,
        ])))) ?: null;

        $ageAtDeath = ($memorial->date_of_birth && $memorial->date_of_passing)
            ? (int) $memorial->date_of_birth->diffInYears($memorial->date_of_passing)
            : null;

        $children = $memorial->children
            ->filter(fn ($c) => $c->child_name && strcasecmp(trim($c->child_name), $fullName) !== 0)
            ->map(fn ($c) => [
                'child_name' => $c->child_name,
                'birth_year' => $c->birth_year,
            ])->values()->toArray();

        $spouses = $memorial->spouses->map(fn ($s) => [
            'spouse_name' => $s->spouse_name,
            'marriage_start_year' => $s->marriage_start_year,
            'marriage_end_year' => $s->marriage_end_year,
        ])->toArray();

        $education = $memorial->education->map(fn ($e) => [
            'institution' => $e->institution_name,
            'start_year' => $e->start_year,
            'end_year' => $e->end_year,
            'degree' => $e->degree,
        ])->toArray();

        $parents = $memorial->parents->map(fn ($p) => [
            'parent_name' => $p->parent_name,
            'relationship_type' => $p->relationship_type,
        ])->toArray();

        $siblings = $memorial->siblings->map(fn ($s) => [
            'sibling_name' => $s->sibling_name,
        ])->toArray();

        $companies = $memorial->notableCompanies->map(fn ($c) => [
            'company_name' => $c->company_name,
        ])->toArray();

        $coFounders = $memorial->coFounders->map(fn ($c) => [
            'name' => $c->name,
        ])->toArray();

        return array_filter([
            'full_name' => $fullName,
            'nationality' => $nationality ?: null,
            'occupation' => trim($memorial->primary_profession ?? $memorial->short_description ?? $memorial->designation ?? '') ?: null,
            'known_for' => trim($memorial->known_for ?? '') ?: null,
            'major_achievements' => trim($memorial->major_achievements ?? '') ?: null,
            'notable_title' => trim($memorial->notable_title ?? '') ?: null,
            'more_details' => trim($memorial->more_details ?? '') ?: null,
            'birth_date' => $memorial->date_of_birth?->format('F j, Y'),
            'death_date' => $memorial->date_of_passing?->format('F j, Y'),
            'age_at_death' => $ageAtDeath,
            'birth_place' => $birthPlace,
            'death_place' => $deathPlace,
            'children' => !empty($children) ? $children : null,
            'spouses' => !empty($spouses) ? $spouses : null,
            'education' => !empty($education) ? $education : null,
            'parents' => !empty($parents) ? $parents : null,
            'siblings' => !empty($siblings) ? $siblings : null,
            'companies' => !empty($companies) ? $companies : null,
            'co_founders' => !empty($coFounders) ? $coFounders : null,
        ], fn ($v) => $v !== null);
    }

    private static function cleanPlaceName(string $place): string
    {
        $place = preg_replace('/\b(\w[\w\s]*),\s*\1\b/i', '$1', $place);
        return trim($place, ', ');
    }
}
