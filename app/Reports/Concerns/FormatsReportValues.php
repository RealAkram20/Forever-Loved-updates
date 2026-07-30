<?php

namespace App\Reports\Concerns;

use App\Helpers\PriceHelper;
use App\Models\SystemSetting;

/**
 * Shared formatting for summary stats.
 *
 * Only stats use these — row values stay raw so the exporters can type them properly.
 */
trait FormatsReportValues
{
    protected function money(float|int|string|null $amount, ?string $currency = null): string
    {
        $formatted = PriceHelper::format($amount ?? 0);

        return $currency ? "{$currency} {$formatted}" : $formatted;
    }

    /**
     * Money that may span currencies — the honest rendering of a set of payment rows.
     * "UGX 4,120,000 · USD 300" rather than a single meaningless total.
     *
     * @param  array<string, float>  $totalsByCurrency
     */
    protected function moneyByCurrency(array $totalsByCurrency): string
    {
        if ($totalsByCurrency === []) {
            return $this->money(0, $this->defaultCurrency());
        }

        arsort($totalsByCurrency);

        return implode('  ·  ', array_map(
            fn ($amount, $currency) => $this->money($amount, $currency),
            $totalsByCurrency,
            array_keys($totalsByCurrency),
        ));
    }

    protected function defaultCurrency(): string
    {
        return (string) SystemSetting::get('payments.currency', 'USD');
    }

    protected function number(float|int|null $value): string
    {
        return number_format((float) ($value ?? 0));
    }

    protected function percent(float|int|null $value, int $decimals = 0): string
    {
        return number_format((float) ($value ?? 0), $decimals).'%';
    }

    /**
     * Share of a whole, guarding the zero denominator that otherwise turns an empty
     * period into a division error.
     */
    protected function ratio(float|int|null $part, float|int|null $whole, int $decimals = 0): string
    {
        if (! $whole) {
            return '—';
        }

        return $this->percent(((float) $part / (float) $whole) * 100, $decimals);
    }

    protected function bytes(?int $bytes): string
    {
        $bytes = (int) $bytes;

        if ($bytes <= 0) {
            return '0 MB';
        }

        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $power = min((int) floor(log($bytes, 1024)), count($units) - 1);
        $value = $bytes / (1024 ** $power);

        return number_format($value, $value >= 100 || $power < 2 ? 0 : 1).' '.$units[$power];
    }

    /**
     * Change against a previous period, as a signed percentage. Returns null when there is
     * no baseline — growth "from zero" is not a percentage, and printing +100% would
     * misrepresent a first sale as a doubling.
     */
    protected function changeVsPrevious(float|int|null $current, float|int|null $previous): ?string
    {
        if (! $previous) {
            return null;
        }

        $delta = (((float) $current - (float) $previous) / (float) $previous) * 100;

        return sprintf('%s%s vs. previous period', $delta >= 0 ? '+' : '', $this->percent($delta, 1));
    }
}
