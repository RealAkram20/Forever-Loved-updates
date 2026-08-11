<?php

namespace App\Services\Reports\Export;

use App\Reports\ReportColumn;
use App\Reports\ReportResult;
use App\Services\Reports\ReportValueFormatter;
use Barryvdh\DomPDF\Facade\Pdf;
use Symfony\Component\HttpFoundation\Response;

/**
 * The version people print, sign and hand over.
 */
class PdfExporter implements ReportExporter
{
    /**
     * Beyond this the document stops being a document. The table is still complete in the
     * Excel and CSV exports, and the PDF says so on its face rather than quietly ending.
     */
    private const MAX_ROWS = 2000;

    /**
     * Past this many columns a landscape A4 table is unreadable, so columns marked
     * secondary — references, gateway internals — are dropped from the PDF only.
     */
    private const COLUMN_BUDGET = 9;

    public function __construct(private readonly ReportValueFormatter $formatter) {}

    public function extension(): string
    {
        return 'pdf';
    }

    public function export(ReportResult $result): Response
    {
        $columns = $this->columnsFor($result);

        $rows = [];
        foreach ($result->rows as $row) {
            if (count($rows) >= self::MAX_ROWS) {
                break;
            }

            $rows[] = array_map(
                fn (ReportColumn $column) => $this->formatter->display($column, $row),
                $columns,
            );
        }

        $pdf = Pdf::setOptions([
            // A report must never become an outbound request. Everything it draws is on
            // this disk already, and chroot keeps a crafted path from reaching further.
            'isRemoteEnabled' => false,
            'isHtml5ParserEnabled' => true,
            'chroot' => public_path(),
            'defaultFont' => 'DejaVu Sans',
        ])->loadView('pages.reports.pdf', [
            'result' => $result,
            'columns' => $columns,
            'rows' => $rows,
            'truncated' => $result->total > count($rows),
            'shownRows' => count($rows),
            'droppedColumns' => count($result->columns) - count($columns),
        ]);

        // Landscape unless the table is narrow enough to breathe in portrait.
        $pdf->setPaper('a4', count($columns) > 5 ? 'landscape' : 'portrait');

        return $pdf->download($result->filename().'.pdf');
    }

    /**
     * @return ReportColumn[]
     */
    private function columnsFor(ReportResult $result): array
    {
        if (count($result->columns) <= self::COLUMN_BUDGET) {
            return $result->columns;
        }

        $essential = array_values(array_filter(
            $result->columns,
            fn (ReportColumn $column) => ! $column->secondary,
        ));

        // If a report marked nothing as secondary we keep every column and let it be
        // cramped — silently deciding which of someone's columns matter is worse.
        return $essential === [] ? $result->columns : $essential;
    }
}
