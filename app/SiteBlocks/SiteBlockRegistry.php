<?php

namespace App\SiteBlocks;

use App\Contracts\SiteBlockContract;
use InvalidArgumentException;

class SiteBlockRegistry
{
    /**
     * @return array<string, class-string<SiteBlockContract>>
     */
    public function typeMap(): array
    {
        return [
            HeroBlock::type() => HeroBlock::class,
            MemorialShowcaseBlock::type() => MemorialShowcaseBlock::class,
            FeaturesGridBlock::type() => FeaturesGridBlock::class,
            CtaBannerBlock::type() => CtaBannerBlock::class,
        ];
    }

    /**
     * @return array<int, array{type: string, label: string, category: string, defaultProps: array<string, mixed>}>
     */
    public function manifest(): array
    {
        $out = [];
        foreach ($this->typeMap() as $class) {
            /** @var class-string<SiteBlockContract> $class */
            $out[] = [
                'type' => $class::type(),
                'label' => $class::label(),
                'category' => $class::category(),
                'defaultProps' => $class::defaultProps(),
            ];
        }

        return $out;
    }

    /**
     * @param  class-string<SiteBlockContract>  $class
     */
    public function register(string $class): void
    {
        if (! is_subclass_of($class, SiteBlockContract::class)) {
            throw new InvalidArgumentException('Invalid site block class: '.$class);
        }
    }

    public function classForType(string $type): ?string
    {
        return $this->typeMap()[$type] ?? null;
    }

    /**
     * @param  array<int, array{type: string, props?: array<string, mixed>}>  $blocks
     * @return array<int, array{type: string, props: array<string, mixed>}>
     */
    public function normalizeBlocksArray(array $blocks): array
    {
        $map = $this->typeMap();
        $out = [];
        foreach ($blocks as $block) {
            if (! is_array($block) || ! isset($block['type']) || ! is_string($block['type'])) {
                continue;
            }
            $type = $block['type'];
            if (! isset($map[$type])) {
                continue;
            }
            /** @var class-string<SiteBlockContract> $class */
            $class = $map[$type];
            $defaults = $class::defaultProps();
            $props = is_array($block['props'] ?? null) ? $block['props'] : [];
            $merged = $defaults;
            foreach ($props as $k => $v) {
                $merged[$k] = $v;
            }
            $out[] = [
                'type' => $type,
                'props' => $merged,
            ];
        }

        return $out;
    }
}
