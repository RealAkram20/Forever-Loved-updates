<?php

namespace App\Http\Controllers;

use App\Models\Memorial;
use App\Reports\ReportFilters;
use App\Services\Reports\MemorialReportBuilder;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * The report for one memorial — the document a reseller prints or emails to a family.
 *
 * Outside the report registry on purpose: that contract describes a table with columns,
 * and this is a keepsake.
 */
class MemorialReportController extends Controller
{
    public function __construct(private readonly MemorialReportBuilder $builder) {}

    public function show(Request $request, string $slug)
    {
        [$memorial, $data] = $this->prepare($request, $slug);

        return view('pages.reports.memorial', $data + [
            'title' => $memorial->full_name,
            'includeMessages' => $data['messagesIncluded'],
            // Where "back" goes depends on who is looking: reseller staff came from their
            // client list, everyone else from their own memorials. Resolved here rather
            // than with url()->previous(), which would send someone who arrived by a
            // pasted link somewhere arbitrary.
            'backUrl' => $request->user()->hasRole('reseller')
                ? route('reseller.memorials')
                : route('memorials.index'),
            'backLabel' => $request->user()->hasRole('reseller') ? 'Client memorials' : 'Memorials',
        ]);
    }

    public function download(Request $request, string $slug)
    {
        [$memorial, $data] = $this->prepare($request, $slug);

        $pdf = Pdf::setOptions([
            'isRemoteEnabled' => false,
            'isHtml5ParserEnabled' => true,
            'chroot' => public_path(),
            'defaultFont' => 'DejaVu Sans',
        ])->loadView('pages.reports.memorial-pdf', $data);

        // Portrait: this is a keepsake, not a spreadsheet.
        $pdf->setPaper('a4', 'portrait');

        return $pdf->download(Str::slug($memorial->full_name.' memorial report '.now()->format('Y-m-d')).'.pdf');
    }

    /**
     * @return array{0: Memorial, 1: array}
     */
    private function prepare(Request $request, string $slug): array
    {
        $memorial = Memorial::where('slug', $slug)->with('reseller')->firstOrFail();

        // 'report', not 'view': view() lets any visitor read a public memorial, and this
        // document carries visitor counts and reprints tribute messages together.
        $this->authorize('report', $memorial);

        // Defaults to every day since the memorial went up rather than the last 30 —
        // a family asking "how many people came?" means since we lost them, not this month.
        $filters = $request->hasAny(['preset', 'from', 'to'])
            ? ReportFilters::fromRequest($request)
            : ReportFilters::allTime();

        // Opt-out rather than opt-in: the messages are the point of the document for most
        // families, but not every family wants them reprinted, so it stays a choice.
        $includeMessages = ! $request->boolean('without_messages');

        return [$memorial, $this->builder->build($memorial, $filters, $includeMessages)];
    }
}
