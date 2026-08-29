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
                // Our own wording for this row, kept here rather than in the block's defaults
                // because it says something about the memorials shown — that they are examples
                // to borrow from — which is true of ours and would be false on a reseller's
                // site, where the same block shows real families.
                ['type' => MemorialShowcaseBlock::type(), 'props' => array_merge(MemorialShowcaseBlock::defaultProps(), [
                    'eyebrow' => 'Featured',
                    'title' => 'Memorial Inspiration',
                    'description' => 'Not sure where to begin? A memorial can be as unique as the life it celebrates. Let these fictional examples inspire you to create a beautiful place filled with love, memories, and the moments that made someone special.',
                ])],
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
