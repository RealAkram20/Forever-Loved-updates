<?php

namespace App\Http\Controllers\Reseller;

use App\Http\Controllers\Reports\BaseReportController;
use App\Models\Reseller;
use App\Reports\ReportBranding;
use App\Reports\ReportRegistry;

class ReportController extends BaseReportController
{
    protected function audience(): string
    {
        return ReportRegistry::AUDIENCE_RESELLER;
    }

    /**
     * Their branding, not ours. These downloads go to funeral-home clients and to
     * families — the platform's name has no business on them.
     *
     * Read from the container, where EnsureResellerActive bound the reseller resolved
     * from the authenticated user's own reseller_id.
     */
    protected function branding(): ReportBranding
    {
        return ReportBranding::forReseller(app(Reseller::class));
    }

    protected function routeName(string $action): string
    {
        return "reseller.reports.{$action}";
    }
}
