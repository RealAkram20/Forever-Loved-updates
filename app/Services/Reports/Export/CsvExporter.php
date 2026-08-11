<?php

namespace App\Services\Reports\Export;

use App\Reports\ReportResult;
use App\Services\Reports\ReportValueFormatter;
use Symfony\Component\HttpFoundation\Response;

class CsvExporter implements ReportExporter
{
    public function __construct(private readonly ReportValueFormatter $formatter) {}

    public function extension(): string
    {
        return 'csv';
    }

    public function export(ReportResult $result): Response
    {
        return response()->streamDownload(function () use ($result) {
            $handle = fopen('php://output', 'w');

            // Excel on Windows reads a BOM-less UTF-8 file as the system codepage, which
            // turns every accented name in a memorial report into mojibake. These files
            // are opened in Excel by resellers, so the BOM is the correct default.
            fwrite($handle, "\xEF\xBB\xBF");

            foreach ($result->header() as $label => $value) {
                fputcsv($handle, [$this->guard($label), $this->guard($value)]);
            }

            fputcsv($handle, []);

            foreach ($result->stats as $stat) {
                fputcsv($handle, [$this->guard($stat->label), $this->guard($stat->value)]);
            }

            fputcsv($handle, []);

            fputcsv($handle, array_map(fn ($column) => $this->guard($column->label), $result->columns));

            foreach ($result->rows as $row) {
                fputcsv($handle, array_map(
                    fn ($column) => $this->guard($this->formatter->display($column, $row)),
                    $result->columns,
                ));

                // Flushed per row: a large export starts downloading immediately instead of
                // sitting behind PHP's output buffer until the last row is built.
                flush();
            }

            fclose($handle);
        }, $result->filename().'.csv', [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Cache-Control' => 'no-store, no-cache',
        ]);
    }

    /**
     * Neutralises CSV formula injection.
     *
     * This is not theoretical here: the values are memorial names, client names and free-text
     * notes typed by the public, and the audience is resellers who open these in Excel. A
     * tribute signed "=cmd|'/c calc'!A1" would otherwise execute on their machine.
     */
    private function guard(mixed $value): string
    {
        $value = (string) $value;

        if ($value !== '' && str_contains("=+-@\t\r", $value[0])) {
            return "'".$value;
        }

        return $value;
    }
}
