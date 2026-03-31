<?php

namespace App\Services;

use App\Models\SiteLayout;
use App\SiteBlocks\CtaBannerBlock;
use App\SiteBlocks\FeaturesGridBlock;
use App\SiteBlocks\HeroBlock;
use App\SiteBlocks\MemorialShowcaseBlock;
use App\SiteBlocks\SiteBlockRegistry;
use Illuminate\Support\Facades\Validator;
use InvalidArgumentException;

class SiteLayoutService
{
    public function __construct(
        private SiteBlockRegistry $registry
    ) {}

    /**
     * @return array{version: int, blocks: array<int, array{type: string, props: array<string, mixed>}>}
     */
    public function defaultHomeDocument(): array
    {
        return [
            'version' => 1,
            'blocks' => [
                ['type' => HeroBlock::type(), 'props' => HeroBlock::defaultProps()],
                ['type' => MemorialShowcaseBlock::type(), 'props' => MemorialShowcaseBlock::defaultProps()],
                ['type' => FeaturesGridBlock::type(), 'props' => FeaturesGridBlock::defaultProps()],
                ['type' => CtaBannerBlock::type(), 'props' => CtaBannerBlock::defaultProps()],
            ],
        ];
    }

    /**
     * @return array<int, array{type: string, props: array<string, mixed>}>
     */
    public function blocksForHome(): array
    {
        $layout = SiteLayout::findPublished(SiteLayout::KEY_VISITOR_HOME);
        if (! $layout || $layout->json === null || trim($layout->json) === '') {
            return $this->registry->normalizeBlocksArray($this->defaultHomeDocument()['blocks']);
        }

        $decoded = json_decode($layout->json, true);
        if (! is_array($decoded) || ! isset($decoded['blocks']) || ! is_array($decoded['blocks'])) {
            return $this->registry->normalizeBlocksArray($this->defaultHomeDocument()['blocks']);
        }

        return $this->registry->normalizeBlocksArray($decoded['blocks']);
    }

    /**
     * Validate full document JSON from admin; returns normalized array for storage.
     *
     * @return array{version: int, blocks: array<int, array{type: string, props: array<string, mixed>}>}
     */
    public function validateDocumentFromJson(string $json): array
    {
        $decoded = json_decode($json, true);
        if (! is_array($decoded)) {
            throw new InvalidArgumentException('Invalid JSON.');
        }

        $version = isset($decoded['version']) ? (int) $decoded['version'] : 1;
        $blocksRaw = $decoded['blocks'] ?? [];
        if (! is_array($blocksRaw)) {
            throw new InvalidArgumentException('Missing blocks array.');
        }

        $normalized = [];
        foreach ($blocksRaw as $index => $block) {
            if (! is_array($block) || ! isset($block['type'])) {
                continue;
            }
            $type = (string) $block['type'];
            $class = $this->registry->classForType($type);
            if ($class === null) {
                throw new InvalidArgumentException('Unknown block type: '.$type);
            }
            $props = is_array($block['props'] ?? null) ? $block['props'] : [];
            $defaults = $class::defaultProps();
            $merged = $defaults;
            foreach ($props as $k => $v) {
                $merged[$k] = $v;
            }

            $rules = $class::rules();
            $prefix = 'props.';
            $prefixed = [];
            foreach ($rules as $key => $rule) {
                $prefixed[$prefix.$key] = $rule;
            }
            Validator::make(['props' => $merged], $prefixed)->validate();

            $normalized[] = ['type' => $type, 'props' => $merged];
        }

        return [
            'version' => max(1, $version),
            'blocks' => $normalized,
        ];
    }
}
