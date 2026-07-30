<?php

namespace App\Http\Controllers\Reports;

use App\Http\Controllers\Controller;
use App\Reports\ReportBranding;
use App\Reports\ReportFilters;
use App\Reports\ReportRegistry;
use App\Services\Reports\Export\ExporterFactory;
use App\Services\Reports\ReportRenderer;
use App\Services\Reports\ReportValueFormatter;
use Illuminate\Http\Request;

/**
 * Catalogue, one report, one download — shared by the admin and reseller areas.
 *
 * The two subclasses differ in three lines: which audience they read from the registry,
 * whose branding goes on the export, and which route names the views link to. Everything
 * that could be got wrong per-report — scoping, gating, format handling — happens once,
 * here.
 */
abstract class BaseReportController extends Controller
{
    public function __construct(
        protected readonly ReportRegistry $registry,
        protected readonly ReportRenderer $renderer,
        protected readonly ExporterFactory $exporters,
    ) {}

    abstract protected function audience(): string;

    abstract protected function branding(): ReportBranding;

    /** Route name for a given action, e.g. 'reports.show' or 'reseller.reports.show'. */
    abstract protected function routeName(string $action): string;

    public function index(Request $request)
    {
        return view('pages.reports.index', [
            'title' => 'Reports',
            'groups' => $this->registry->grouped($this->audience(), $request->user()),
            'showRoute' => $this->routeName('show'),
            'branding' => $this->branding(),
        ]);
    }

    public function show(Request $request, string $report)
    {
        $report = $this->registry->resolve($this->audience(), $report, $request->user());
        $filters = ReportFilters::fromRequest($request);

        // Not in their tier: show what the report is, not a 403. It is a paid capability,
        // not forbidden data — the same choice the Analytics page already makes.
        if (! $report->unlockedFor($request->user())) {
            return view('pages.reports.locked', [
                'title' => $report->title(),
                'report' => $report,
                'indexRoute' => $this->routeName('index'),
            ]);
        }

        $rendered = $this->renderer->forScreen($report, $filters);

        return view('pages.reports.show', [
            'title' => $report->title(),
            'report' => $report,
            'filters' => $filters,
            'stats' => $rendered['stats'],
            'columns' => $rendered['columns'],
            'paginator' => $rendered['paginator'],
            'showRoute' => $this->routeName('show'),
            'indexRoute' => $this->routeName('index'),
            'downloadRoute' => $this->routeName('download'),
            'formats' => ExporterFactory::formats(),
            // The same formatter the exports use, so a figure cannot read one way on the
            // page and another in the file downloaded from it.
            'formatter' => app(ReportValueFormatter::class),
        ]);
    }

    public function download(Request $request, string $report, string $format)
    {
        $report = $this->registry->resolve($this->audience(), $report, $request->user());

        // A locked report renders a pitch on screen but must not hand over the data by
        // URL. This is the enforcement; the pitch page is the courtesy.
        abort_unless($report->unlockedFor($request->user()), 403);

        $result = $this->renderer->forExport(
            $report,
            ReportFilters::fromRequest($request),
            $this->branding(),
            $request->user(),
        );

        return $this->exporters->make($format)->export($result);
    }
}
