<?php

namespace App\Reports\Contracts;

use App\Models\User;
use App\Reports\ReportColumn;
use App\Reports\ReportFilters;
use App\Reports\ReportStat;
use Illuminate\Support\LazyCollection;

/**
 * One report.
 *
 * A report knows its columns and its query. It knows nothing about HTML, PDF, Excel, or
 * who is asking — that is what lets fourteen of them share one pipeline, and what keeps
 * "did this one remember to scope by reseller_id?" from being fourteen separate questions.
 */
interface Report
{
    /** URL segment and registry key, e.g. 'revenue'. */
    public function key(): string;

    public function title(): string;

    /** One line, shown on the catalogue card and under the PDF title. */
    public function description(): string;

    /** Catalogue grouping, e.g. 'Money' or 'Engagement'. */
    public function group(): string;

    /**
     * False for snapshot reports — a quota statement or a roster is "as of now" and a date
     * filter on it would be a lie. The UI hides the date controls when this is false.
     */
    public function usesDateWindow(): bool;

    /**
     * What the date window actually filters on, spelled out — "by payment date", "by the
     * date the memorial was created". Two reports over the same period disagree unless the
     * reader knows which date each one used.
     */
    public function dateWindowNote(): ?string;

    /** @return ReportColumn[] */
    public function columns(): array;

    /** @return ReportStat[] */
    public function summary(ReportFilters $filters): array;

    /**
     * The rows, lazily. Implementations MUST stream (cursor/chunk) rather than materialise
     * — a reseller exporting a year of view records must not hold it all in memory.
     *
     * @return LazyCollection<int, array<string, mixed>> keyed by column key
     */
    public function rows(ReportFilters $filters): LazyCollection;

    /** Cheap count for pagination — never enumerate rows() to answer this. */
    public function total(ReportFilters $filters): int;

    /** Whether this user may see the report at all. */
    public function availableTo(User $user): bool;

    /**
     * Shown instead of the data when the report exists but is not included in the viewer's
     * tier. Null means "not applicable" — an unavailable report with no message is simply
     * hidden rather than pitched.
     */
    public function lockedMessage(): ?string;

    /** Whether this user's tier includes it. False renders the pitch, not a 403. */
    public function unlockedFor(User $user): bool;
}
