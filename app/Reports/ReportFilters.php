<?php

namespace App\Reports;

use Carbon\CarbonImmutable;
use Illuminate\Http\Request;

/**
 * The date window a report runs over, plus any per-report extras.
 *
 * Built from the request in exactly one place so every report gets the same validated,
 * clamped window. A report class never reads the request itself — that is what keeps a
 * tenant id from ever arriving through this object.
 */
final class ReportFilters
{
    public const PRESETS = [
        '7d' => 'Last 7 days',
        '30d' => 'Last 30 days',
        '90d' => 'Last 90 days',
        'ytd' => 'Year to date',
        'all' => 'All time',
        'custom' => 'Custom range',
    ];

    public function __construct(
        public readonly string $preset,
        public readonly ?CarbonImmutable $from,
        public readonly ?CarbonImmutable $to,
        /** Per-report extras (group-by, status filter, …), already whitelisted by the report. */
        public readonly array $extras = [],
    ) {}

    public static function fromRequest(Request $request): self
    {
        $preset = (string) $request->query('preset', '30d');

        if (! array_key_exists($preset, self::PRESETS)) {
            $preset = '30d';
        }

        [$from, $to] = self::resolveWindow($preset, $request);

        // A backwards range is a typo, not an intent. Swapping is friendlier than an error
        // page and cannot produce a wrong number — an empty result would just look like
        // "no data", which is the misleading outcome.
        if ($from && $to && $from->greaterThan($to)) {
            [$from, $to] = [$to, $from];
        }

        return new self($preset, $from, $to, $request->query('extras', []));
    }

    /**
     * @return array{0: ?CarbonImmutable, 1: ?CarbonImmutable}
     */
    private static function resolveWindow(string $preset, Request $request): array
    {
        $now = CarbonImmutable::now();

        return match ($preset) {
            '7d' => [$now->subDays(6)->startOfDay(), $now->endOfDay()],
            '90d' => [$now->subDays(89)->startOfDay(), $now->endOfDay()],
            'ytd' => [$now->startOfYear(), $now->endOfDay()],
            'all' => [null, null],
            'custom' => [
                self::parseDate($request->query('from'))?->startOfDay(),
                self::parseDate($request->query('to'))?->endOfDay(),
            ],
            default => [$now->subDays(29)->startOfDay(), $now->endOfDay()],
        };
    }

    private static function parseDate(mixed $value): ?CarbonImmutable
    {
        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        try {
            return CarbonImmutable::parse($value);
        } catch (\Throwable) {
            // An unparseable date degrades to "no bound" rather than a 500. The header
            // block prints the window that was actually used, so nothing is hidden.
            return null;
        }
    }

    /**
     * Default window used when a report is run without any request behind it.
     */
    public static function lastDays(int $days): self
    {
        $now = CarbonImmutable::now();

        return new self('custom', $now->subDays($days - 1)->startOfDay(), $now->endOfDay());
    }

    public static function allTime(): self
    {
        return new self('all', null, null);
    }

    public function isBounded(): bool
    {
        return $this->from !== null || $this->to !== null;
    }

    /**
     * Applies the window to a query on the given column. Reports call this instead of
     * writing the same pair of nullable wheres fourteen times.
     */
    public function applyTo(mixed $query, string $column): mixed
    {
        if ($this->from) {
            $query->where($column, '>=', $this->from);
        }

        if ($this->to) {
            $query->where($column, '<=', $this->to);
        }

        return $query;
    }

    /**
     * The same window, shifted back by its own length — for "vs. previous period"
     * comparisons. Null when the window is unbounded, since "before all time" is nothing.
     */
    public function previousPeriod(): ?self
    {
        if (! $this->from || ! $this->to) {
            return null;
        }

        $length = $this->from->diffInSeconds($this->to);

        return new self(
            'custom',
            $this->from->subSeconds($length + 1),
            $this->from->subSecond(),
        );
    }

    public function label(): string
    {
        if (! $this->isBounded()) {
            return 'All time';
        }

        $from = $this->from?->format('j M Y') ?? 'the beginning';
        $to = $this->to?->format('j M Y') ?? 'today';

        return $from === $to ? $from : "{$from} – {$to}";
    }

    /**
     * Filename-safe form of the window, for downloaded files.
     */
    public function slug(): string
    {
        if (! $this->isBounded()) {
            return 'all-time';
        }

        return implode('_', array_filter([
            $this->from?->format('Y-m-d'),
            $this->to?->format('Y-m-d'),
        ]));
    }

    public function extra(string $key, mixed $default = null): mixed
    {
        return $this->extras[$key] ?? $default;
    }
}
