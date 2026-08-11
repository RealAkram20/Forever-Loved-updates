<?php

namespace App\Services\Reports\Export;

use App\Reports\ReportColumn;
use App\Reports\ReportResult;
use App\Services\Reports\ReportValueFormatter;
use OpenSpout\Common\Entity\Cell;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Common\Entity\Style\Style;
use OpenSpout\Writer\XLSX\Options;
use OpenSpout\Writer\XLSX\Properties;
use OpenSpout\Writer\XLSX\Writer;
use Symfony\Component\HttpFoundation\Response;

/**
 * Real .xlsx, written a row at a time.
 *
 * The point of shipping this alongside CSV is types: money arrives as a number with a
 * currency format and dates as real dates, so the recipient can sort, filter and SUM()
 * without cleaning the sheet. A CSV of formatted strings cannot do that, and "clean the
 * export before you can use it" is how a reporting feature gets abandoned.
 */
class XlsxExporter implements ReportExporter
{
    public function __construct(private readonly ReportValueFormatter $formatter) {}

    public function extension(): string
    {
        return 'xlsx';
    }

    public function export(ReportResult $result): Response
    {
        // Written to a temp file rather than straight to the browser: OpenSpout's
        // openToBrowser() sends its own headers, which fights Laravel's response pipeline.
        // Spout streams to disk as it goes, so memory stays flat either way.
        $path = tempnam(sys_get_temp_dir(), 'report_').'.xlsx';

        $writer = new Writer($this->options($result));
        $writer->openToFile($path);

        $this->writeHeaderBlock($writer, $result);
        $this->writeColumnHeadings($writer, $result);
        $this->writeRows($writer, $result);

        $writer->close();

        return response()
            ->download($path, $result->filename().'.xlsx', [
                'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                'Cache-Control' => 'no-store, no-cache',
            ])
            ->deleteFileAfterSend(true);
    }

    private function options(ReportResult $result): Options
    {
        // Document properties carry the owning organisation, not ours — a reseller's
        // spreadsheet should say who produced it in File → Properties too, not only on
        // the page. Set through Options because XLSX rejects the writer's setters.
        $options = new Options(properties: new Properties(
            title: $result->report->title(),
            application: $result->branding->organisationName,
            creator: $result->branding->organisationName,
            lastModifiedBy: $result->branding->organisationName,
            description: $result->report->description(),
        ));

        // Wide enough that names and emails are readable on open. Excel's default of ~8
        // characters truncates almost every column a report of ours produces.
        $options->setColumnWidthForRange(22, 1, max(1, count($result->columns)));

        return $options;
    }

    /**
     * The provenance block: what this is, whose it is, when it was run and over what
     * window. A spreadsheet outlives the page it came from, and gets forwarded.
     */
    private function writeHeaderBlock(Writer $writer, ReportResult $result): void
    {
        $bold = new Style(fontBold: true);
        $title = new Style(fontBold: true, fontSize: 14);

        $writer->addRow(Row::fromValuesWithStyle([$result->report->title()], $title));
        $writer->addRow(Row::fromValues([$result->report->description()]));
        $writer->addRow(Row::fromValues([]));

        foreach ($result->header() as $label => $value) {
            $writer->addRow(new Row([
                Cell::fromValue($label, $bold),
                Cell::fromValue($value),
            ]));
        }

        $writer->addRow(Row::fromValues([]));

        foreach ($result->stats as $stat) {
            $writer->addRow(new Row(array_filter([
                Cell::fromValue($stat->label, $bold),
                Cell::fromValue($stat->value),
                $stat->hint ? Cell::fromValue($stat->hint) : null,
            ])));
        }

        $writer->addRow(Row::fromValues([]));
    }

    private function writeColumnHeadings(Writer $writer, ReportResult $result): void
    {
        $heading = new Style(fontBold: true, backgroundColor: 'EEF2F7');

        $writer->addRow(new Row(array_map(
            fn (ReportColumn $column) => Cell::fromValue($column->label, $heading),
            $result->columns,
        )));
    }

    private function writeRows(Writer $writer, ReportResult $result): void
    {
        // Styles built once and reused. Building a Style per cell across 50,000 rows is
        // the difference between an export that finishes and one that times out.
        $styles = [];
        foreach ($result->columns as $column) {
            $styles[$column->key] = $this->styleFor($column);
        }

        foreach ($result->rows as $row) {
            $writer->addRow(new Row(array_map(
                fn (ReportColumn $column) => Cell::fromValue(
                    $this->formatter->raw($column, $row),
                    $styles[$column->key],
                ),
                $result->columns,
            )));
        }
    }

    private function styleFor(ReportColumn $column): ?Style
    {
        return match ($column->type) {
            // Two decimals regardless of the currency's own convention: a zero-decimal
            // currency shows .00 rather than a shilling being silently rounded away.
            ReportColumn::TYPE_MONEY => new Style(format: '#,##0.00'),
            ReportColumn::TYPE_NUMBER => new Style(format: '#,##0'),
            ReportColumn::TYPE_PERCENT => new Style(format: '0"%"'),
            ReportColumn::TYPE_DATE => new Style(format: 'yyyy-mm-dd'),
            ReportColumn::TYPE_DATETIME => new Style(format: 'yyyy-mm-dd hh:mm'),
            default => null,
        };
    }
}
