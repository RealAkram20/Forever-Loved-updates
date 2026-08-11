<?php

namespace App\Services\Reports;

use App\Helpers\PriceHelper;
use App\Reports\ReportColumn;
use Carbon\Carbon;
use Carbon\CarbonInterface;

/**
 * Turns a raw row value into what each output medium needs.
 *
 * One class rather than per-exporter formatting, so a date cannot render as "30 Jul 2026"
 * on screen and "2026-07-30 00:00:00" in the PDF of the same report.
 */
class ReportValueFormatter
{
    /**
     * Human-readable — screen, PDF and CSV.
     */
    public function display(ReportColumn $column, array $row): string
    {
        $value = $row[$column->key] ?? null;

        if ($value === null || $value === '') {
            // An em dash, not an empty cell: "we looked and there is nothing" reads
            // differently from "this column stopped rendering".
            return '—';
        }

        return match ($column->type) {
            ReportColumn::TYPE_MONEY => $this->money($value, $column->currencyKey ? ($row[$column->currencyKey] ?? null) : null),
            ReportColumn::TYPE_NUMBER => number_format((float) $value),
            ReportColumn::TYPE_PERCENT => number_format((float) $value, 0).'%',
            ReportColumn::TYPE_DATE => $this->date($value, 'j M Y'),
            ReportColumn::TYPE_DATETIME => $this->date($value, 'j M Y, H:i'),
            ReportColumn::TYPE_BOOL => $value ? 'Yes' : 'No',
            default => (string) $value,
        };
    }

    /**
     * Typed — Excel. Numbers stay numbers and dates stay dates so the recipient can sort,
     * filter and SUM() without cleaning the sheet first. This is the whole reason the
     * xlsx export exists rather than just handing everyone a CSV.
     */
    public function raw(ReportColumn $column, array $row): mixed
    {
        $value = $row[$column->key] ?? null;

        if ($value === null || $value === '') {
            return null;
        }

        return match ($column->type) {
            ReportColumn::TYPE_MONEY, ReportColumn::TYPE_NUMBER, ReportColumn::TYPE_PERCENT => (float) $value,
            ReportColumn::TYPE_DATE, ReportColumn::TYPE_DATETIME => $this->toDate($value),
            ReportColumn::TYPE_BOOL => (bool) $value,
            default => (string) $value,
        };
    }

    private function money(mixed $value, ?string $currency): string
    {
        $formatted = PriceHelper::format($value);

        return $currency ? "{$currency} {$formatted}" : $formatted;
    }

    private function date(mixed $value, string $format): string
    {
        $date = $this->toDate($value);

        return $date?->format($format) ?? (string) $value;
    }

    private function toDate(mixed $value): ?CarbonInterface
    {
        if ($value instanceof CarbonInterface) {
            return $value;
        }

        try {
            return Carbon::parse((string) $value);
        } catch (\Throwable) {
            return null;
        }
    }
}
