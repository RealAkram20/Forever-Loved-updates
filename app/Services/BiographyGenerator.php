<?php

namespace App\Services;

use App\Helpers\AiConfigHelper;
use App\Models\Memorial;

/**
 * The provider → template biography chain, shared by the synchronous
 * controller path and the queued GenerateBiography job. Quota bookkeeping is
 * the CALLER's job: on ['success' => false] the caller must release the
 * reserved slot; on success the slot stays consumed.
 */
class BiographyGenerator
{
    /**
     * @return array{success: bool, message?: string, ai_provider?: string,
     *               fallback_used?: bool, fallback_reason?: ?string,
     *               option_1?: string, option_2?: string, option_3?: string}
     */
    public function generate(Memorial $memorial, array $structuredData, bool $noCache): array
    {
        $aiProvider = AiConfigHelper::getActiveProvider();
        if (! $aiProvider) {
            return ['success' => false, 'message' => 'No AI provider is enabled. Please enable OpenAI, Claude, or Gemini in your configuration.'];
        }

        $service = match ($aiProvider) {
            'ChatGPT' => app(OpenAIBioGeneratorService::class),
            'Claude AI' => app(ClaudeBioGeneratorService::class),
            'Google Gemini' => app(GeminiBioGeneratorService::class),
            default => null,
        };
        if (! $service) {
            return ['success' => false, 'message' => 'The configured AI provider is not supported.'];
        }

        $fallbackUsed = false;
        $fallbackReason = null;

        try {
            $options = $service->generate($structuredData, $memorial->id, $noCache);
        } catch (\Throwable $e) {
            // Provider down/misconfigured — deliver template-built options
            // instead of an error so the user still gets a result.
            $fallbackReason = self::parseAiErrorMessage($e->getMessage());

            try {
                $options = app(TemplateBioGeneratorService::class)->generate($structuredData);
                $aiProvider = 'Template';
                $fallbackUsed = true;
            } catch (\Throwable $templateError) {
                report($templateError);

                return ['success' => false, 'message' => $fallbackReason];
            }
        }

        $o1 = strip_tags(trim($options['option_1'] ?? ''));
        $o2 = strip_tags(trim($options['option_2'] ?? ''));
        $o3 = strip_tags(trim($options['option_3'] ?? ''));

        if (! $o1 && ! $o2 && ! $o3) {
            return ['success' => false, 'message' => 'AI returned empty results. Please add more details and try again.'];
        }

        return [
            'success' => true,
            'ai_provider' => $aiProvider,
            'fallback_used' => $fallbackUsed,
            'fallback_reason' => $fallbackReason,
            'option_1' => $o1,
            'option_2' => $o2,
            'option_3' => $o3,
        ];
    }

    public static function parseAiErrorMessage(string $message): string
    {
        if (str_starts_with($message, 'AI_AUTH_ERROR:')) {
            return 'AI authentication failed. The API key may be invalid or expired. Template suggestions are shown instead.';
        }
        if (str_starts_with($message, 'AI_NO_CREDITS:')) {
            return 'Your AI account has no remaining credits. Please top up your API billing. Template suggestions are shown instead.';
        }
        if (str_starts_with($message, 'AI_RATE_LIMIT:')) {
            return 'AI rate limit reached. Please wait a moment and try again.';
        }
        if (str_starts_with($message, 'AI_MODEL_ERROR:')) {
            return 'The configured AI model is unavailable. Please check your settings. Template suggestions are shown instead.';
        }
        if (str_starts_with($message, 'AI_OVERLOADED:')) {
            return 'The AI service is temporarily overloaded. Please try again in a few seconds.';
        }
        if (str_starts_with($message, 'AI_API_ERROR:')) {
            return 'AI generation encountered an error. Template suggestions are shown instead.';
        }

        return 'AI generation failed. Template suggestions are shown instead.';
    }
}
