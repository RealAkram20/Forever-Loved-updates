<?php

namespace App\Reports;

use App\Models\User;
use App\Reports\Concerns\FormatsReportValues;
use App\Reports\Contracts\Report;

/**
 * Defaults every report shares, so a report class is only its columns and its query.
 */
abstract class BaseReport implements Report
{
    use FormatsReportValues;

    public function group(): string
    {
        return 'General';
    }

    public function usesDateWindow(): bool
    {
        return true;
    }

    public function dateWindowNote(): ?string
    {
        return null;
    }

    public function availableTo(User $user): bool
    {
        return true;
    }

    public function lockedMessage(): ?string
    {
        return null;
    }

    public function unlockedFor(User $user): bool
    {
        return true;
    }

    /**
     * Rows keyed by column key, with any key the columns do not declare dropped.
     *
     * Reports build rows from queries that often select more than they display (ids for
     * joins, sort keys). Filtering here means an exporter can never leak a column the
     * report did not intend to publish — including, on a tenant report, a reseller_id.
     *
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    protected function shape(array $row): array
    {
        $shaped = [];

        foreach ($this->columns() as $column) {
            $shaped[$column->key] = $row[$column->key] ?? null;
        }

        // Currency keys are referenced by money columns for formatting but are not
        // themselves columns, so they survive the filter above.
        foreach ($this->columns() as $column) {
            if ($column->currencyKey && array_key_exists($column->currencyKey, $row)) {
                $shaped[$column->currencyKey] = $row[$column->currencyKey];
            }
        }

        return $shaped;
    }
}
