<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Reports\BaseReportController;
use App\Reports\ReportBranding;
use App\Reports\ReportRegistry;

class ReportController extends BaseReportController
{
    protected function audience(): string
    {
        return ReportRegistry::AUDIENCE_ADMIN;
    }

    protected function branding(): ReportBranding
    {
        return ReportBranding::platform();
    }

    protected function routeName(string $action): string
    {
        return "reports.{$action}";
    }
}
