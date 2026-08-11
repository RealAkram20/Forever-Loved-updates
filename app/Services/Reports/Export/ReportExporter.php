<?php

namespace App\Services\Reports\Export;

use App\Reports\ReportResult;
use Symfony\Component\HttpFoundation\Response;

interface ReportExporter
{
    /** File extension, without the dot. Also the route's {format} segment. */
    public function extension(): string;

    public function export(ReportResult $result): Response;
}
