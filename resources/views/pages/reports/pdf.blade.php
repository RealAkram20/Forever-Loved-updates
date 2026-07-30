<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>{{ $result->report->title() }} — {{ $result->branding->organisationName }}</title>
    <style>
        /* DejaVu Sans is dompdf's only bundled font with full Latin-Extended coverage.
           Memorial and family names carry accents constantly; the default font drops them. */
        * { font-family: "DejaVu Sans", sans-serif; }

        @page { margin: 108px 28px 56px 28px; }

        body { margin: 0; color: #1f2937; font-size: 8.5pt; }

        header { position: fixed; top: -84px; left: 0; right: 0; height: 72px; }
        footer { position: fixed; bottom: -40px; left: 0; right: 0; height: 28px;
                 color: #9ca3af; font-size: 7pt; border-top: 0.5pt solid #e5e7eb; padding-top: 6px; }

        .brand { border-bottom: 2pt solid {{ $result->branding->primaryColor }}; padding-bottom: 8px; }
        .brand td { vertical-align: bottom; }
        .org { font-size: 12pt; font-weight: bold; color: {{ $result->branding->primaryColor }}; }
        .doc-title { font-size: 14pt; font-weight: bold; }
        .doc-desc { color: #6b7280; font-size: 8pt; margin-top: 2px; }

        table { width: 100%; border-collapse: collapse; }

        .meta { margin: 14px 0 16px; }
        .meta td { padding: 2px 12px 2px 0; font-size: 7.5pt; color: #4b5563; }
        .meta .k { color: #9ca3af; text-transform: uppercase; letter-spacing: 0.03em; font-size: 6.5pt; }

        .stats { margin-bottom: 14px; }
        .stats td { border: 0.5pt solid #e5e7eb; padding: 7px 9px; width: 25%; }
        .stats .label { color: #9ca3af; text-transform: uppercase; letter-spacing: 0.03em; font-size: 6.5pt; }
        .stats .value { font-size: 11pt; font-weight: bold; margin-top: 2px; }
        .stats .hint { color: #9ca3af; font-size: 6.5pt; margin-top: 2px; }

        .data th { background: #f3f4f6; color: #4b5563; text-align: left; font-size: 7pt;
                   text-transform: uppercase; letter-spacing: 0.03em; padding: 6px 7px;
                   border-bottom: 0.5pt solid #d1d5db; }
        .data td { padding: 5px 7px; border-bottom: 0.5pt solid #f3f4f6; }
        /* Zebra striping rather than a full grid: on a 2,000-row table, ruled columns are
           noise and the eye only ever needs help staying on one row. */
        .data tr:nth-child(even) td { background: #fafafa; }
        .num { text-align: right; }

        .notice { margin-top: 12px; padding: 7px 9px; background: #fffbeb;
                  border-left: 2pt solid #f59e0b; color: #92400e; font-size: 7.5pt; }
        .empty { padding: 40px; text-align: center; color: #9ca3af; }
    </style>
</head>
<body>
    <header>
        <table class="brand">
            <tr>
                <td>
                    @if ($result->branding->logoPath)
                        {{-- Read off disk. Remote fetching is disabled in the exporter, so a
                             report can never be turned into an outbound request. --}}
                        <img src="{{ $result->branding->logoPath }}" alt="" style="max-height: 34px; max-width: 180px;">
                    @else
                        <div class="org">{{ $result->branding->organisationName }}</div>
                    @endif
                </td>
                <td style="text-align: right;">
                    <div class="doc-title">{{ $result->report->title() }}</div>
                    <div class="doc-desc">{{ $result->report->description() }}</div>
                </td>
            </tr>
        </table>
    </header>

    <footer>
        <table>
            <tr>
                <td>{{ $result->branding->organisationName }} &middot; {{ $result->report->title() }} &middot; generated {{ now()->format('j M Y, H:i') }}</td>
                <td style="text-align: right;">Page <span class="pagenum"></span></td>
            </tr>
        </table>
    </footer>

    {{-- The provenance block. Present on every export deliberately: these files get
         forwarded, and one without a date range or a scope is unciteable a month later. --}}
    <table class="meta">
        @foreach (array_chunk($result->header(), 3, true) as $chunk)
            <tr>
                @foreach ($chunk as $label => $value)
                    <td><div class="k">{{ $label }}</div><div>{{ $value }}</div></td>
                @endforeach
            </tr>
        @endforeach
    </table>

    @if ($result->stats)
        <table class="stats">
            @foreach (array_chunk($result->stats, 4) as $chunk)
                <tr>
                    @foreach ($chunk as $stat)
                        <td>
                            <div class="label">{{ $stat->label }}</div>
                            <div class="value">{{ $stat->value }}</div>
                            @if ($stat->hint)<div class="hint">{{ $stat->hint }}</div>@endif
                        </td>
                    @endforeach
                    @for ($i = count($chunk); $i < 4; $i++)<td style="border: 0;"></td>@endfor
                </tr>
            @endforeach
        </table>
    @endif

    @if ($rows === [])
        <p class="empty">No rows in this period.</p>
    @else
        <table class="data">
            <thead>
                <tr>
                    @foreach ($columns as $column)
                        <th @if ($column->width) style="width: {{ $column->width }}" @endif
                            class="{{ $column->align() === 'right' ? 'num' : '' }}">{{ $column->label }}</th>
                    @endforeach
                </tr>
            </thead>
            <tbody>
                @foreach ($rows as $row)
                    <tr>
                        @foreach ($columns as $index => $column)
                            <td class="{{ $column->align() === 'right' ? 'num' : '' }}">{{ $row[$index] ?? '' }}</td>
                        @endforeach
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif

    {{-- Truncation is stated on the document's own face. A PDF that quietly stops at row
         2,000 reads as a complete record to whoever it was handed to. --}}
    @if ($truncated)
        <p class="notice">
            Showing the first {{ number_format($shownRows) }} of {{ number_format($result->total) }} rows.
            Download the Excel or CSV version of this report for the complete set.
        </p>
    @endif

    @if ($droppedColumns > 0)
        <p class="notice">
            {{ $droppedColumns }} {{ \Illuminate\Support\Str::plural('column', $droppedColumns) }}
            omitted so the table stays readable on this page size. The Excel and CSV downloads include every column.
        </p>
    @endif
</body>
</html>
