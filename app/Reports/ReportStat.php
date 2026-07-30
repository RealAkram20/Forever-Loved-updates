<?php

namespace App\Reports;

/**
 * A headline figure shown above the table and printed at the top of the PDF.
 *
 * The value arrives already formatted. A stat is a sentence, not a datum — "18 of 25
 * profiles used" and "UGX 4,120,000" are both legitimate, and forcing them through a
 * numeric type would only make the report classes fight it.
 */
final class ReportStat
{
    public const TONE_NEUTRAL = 'neutral';

    public const TONE_POSITIVE = 'positive';

    public const TONE_WARNING = 'warning';

    public const TONE_DANGER = 'danger';

    public function __construct(
        public readonly string $label,
        public readonly string $value,
        /** Optional second line — a comparison, a caveat, or what the figure excludes. */
        public readonly ?string $hint = null,
        public readonly string $tone = self::TONE_NEUTRAL,
    ) {}

    public static function make(string $label, string $value, ?string $hint = null, string $tone = self::TONE_NEUTRAL): self
    {
        return new self($label, $value, $hint, $tone);
    }
}
