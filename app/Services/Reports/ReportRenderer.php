<?php

namespace App\Services\Reports;

use App\Models\User;
use App\Reports\Contracts\Report;
use App\Reports\ReportBranding;
use App\Reports\ReportColumn;
use App\Reports\ReportFilters;
use App\Reports\ReportResult;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Request as RequestFacade;

/**
 * Runs a report — once for the screen, once for a download.
 *
 * The two paths differ only in whether the rows are sliced. Everything a reader needs to
 * trust the numbers (stats, header block, branding) is built identically for both, so a
 * downloaded PDF cannot disagree with the page it was downloaded from.
 */
class ReportRenderer
{
    public function forExport(Report $report, ReportFilters $filters, ReportBranding $branding, ?User $user = null): ReportResult
    {
        return new ReportResult(
            report: $report,
            filters: $filters,
            columns: $report->columns(),
            stats: $report->summary($filters),
            rows: $report->rows($filters),
            total: $report->total($filters),
            branding: $branding,
            generatedBy: $user,
        );
    }

    /**
     * @return array{stats: array, columns: ReportColumn[], paginator: LengthAwarePaginator}
     */
    public function forScreen(Report $report, ReportFilters $filters, int $perPage = 25): array
    {
        $page = max(1, (int) RequestFacade::query('page', 1));
        $total = $report->total($filters);

        // Sliced off the LazyCollection rather than fetched with a limit clause, because a
        // report is not required to be one query — the roster and engagement reports
        // assemble rows from several. The collection is lazy, so page 1 stops reading
        // after 25 rows rather than walking the table.
        $rows = $report->rows($filters)
            ->slice(($page - 1) * $perPage)
            ->take($perPage)
            ->all();

        $paginator = new LengthAwarePaginator(
            array_values($rows),
            $total,
            $perPage,
            $page,
            ['path' => RequestFacade::url(), 'query' => RequestFacade::query()],
        );

        return [
            'stats' => $report->summary($filters),
            'columns' => $report->columns(),
            'paginator' => $paginator,
        ];
    }
}
