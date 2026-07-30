<?php

namespace App\Services\Reports\Export;

use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Resolves the {format} route segment to an exporter.
 *
 * The map is closed. The route regex already whitelists the three formats, so this is the
 * second of two gates — nothing reachable from a URL can name a class here.
 */
class ExporterFactory
{
    private const MAP = [
        'pdf' => PdfExporter::class,
        'xlsx' => XlsxExporter::class,
        'csv' => CsvExporter::class,
    ];

    public function make(string $format): ReportExporter
    {
        $class = self::MAP[strtolower($format)] ?? null;

        if (! $class) {
            throw new NotFoundHttpException('Unsupported report format.');
        }

        return app($class);
    }

    /**
     * @return string[]
     */
    public static function formats(): array
    {
        return array_keys(self::MAP);
    }
}
