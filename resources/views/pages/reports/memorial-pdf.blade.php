<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>{{ $memorial->full_name }} — memorial report</title>
    <style>
        * { font-family: "DejaVu Sans", sans-serif; }
        @page { margin: 34px 40px 52px 40px; }
        body { margin: 0; color: #1f2937; font-size: 9.5pt; line-height: 1.5; }

        footer { position: fixed; bottom: -38px; left: 0; right: 0; height: 30px;
                 border-top: 0.5pt solid #e5e7eb; padding-top: 7px;
                 color: #9ca3af; font-size: 7.5pt; }

        table { width: 100%; border-collapse: collapse; }

        .masthead { border-bottom: 2pt solid {{ $branding->primaryColor }}; padding-bottom: 14px; margin-bottom: 18px; }
        .masthead td { vertical-align: middle; }
        .portrait { width: 92px; }
        .portrait img { width: 84px; height: 84px; border-radius: 42px; }
        .name { font-size: 19pt; font-weight: bold; letter-spacing: -0.01em; }
        .dates { color: #6b7280; font-size: 9pt; margin-top: 3px; }
        .period { color: #9ca3af; font-size: 8pt; margin-top: 6px; }

        h2 { font-size: 10pt; text-transform: uppercase; letter-spacing: 0.06em;
             color: #9ca3af; font-weight: bold; margin: 22px 0 9px; }

        .figures td { width: 25%; padding: 11px 12px; border: 0.5pt solid #e5e7eb; }
        .figures .n { font-size: 20pt; font-weight: bold; color: {{ $branding->primaryColor }}; line-height: 1.1; }
        .figures .l { color: #6b7280; font-size: 8pt; margin-top: 3px; }

        .chart td { vertical-align: bottom; padding: 0 0.5pt; }
        .bar { background: {{ $branding->primaryColor }}; }
        .axis td { color: #9ca3af; font-size: 7pt; padding-top: 5px; }

        .breakdown td { padding: 5px 0; border-bottom: 0.5pt solid #f3f4f6; }
        .breakdown .v { text-align: right; font-weight: bold; }

        .message { border-left: 2pt solid #e5e7eb; padding: 2px 0 2px 11px; margin-bottom: 13px; }
        .message .body { font-style: italic; color: #374151; }
        .message .who { color: #9ca3af; font-size: 8pt; margin-top: 3px; }

        .quiet { color: #9ca3af; }
        .note { color: #9ca3af; font-size: 8pt; margin-top: 7px; }
    </style>
</head>
<body>
    <footer>
        <table>
            <tr>
                <td>{{ $branding->organisationName }}</td>
                <td style="text-align: right;">Prepared {{ now()->format('j F Y') }}</td>
            </tr>
        </table>
    </footer>

    <table class="masthead">
        <tr>
            @if ($photoPath)
                <td class="portrait"><img src="{{ $photoPath }}" alt=""></td>
            @endif
            <td>
                <div class="name">{{ $memorial->full_name }}</div>
                @if ($memorial->date_of_birth || $memorial->date_of_passing)
                    <div class="dates">
                        {{ $memorial->date_of_birth?->format('j F Y') ?? '' }}
                        @if ($memorial->date_of_birth && $memorial->date_of_passing) &ndash; @endif
                        {{ $memorial->date_of_passing?->format('j F Y') ?? '' }}
                    </div>
                @endif
                <div class="period">
                    {{ $filters->isBounded() ? $filters->label() : 'Since the memorial was published' }}
                </div>
            </td>
        </tr>
    </table>

    <h2>Who has visited</h2>

    {{-- Visitors are only ever counted. memorial_views stores a hash and a timestamp and
         nothing else, deliberately — so this page says how many people came and never
         suggests the family could find out who they were. --}}
    <table class="figures">
        <tr>
            <td>
                <div class="n">{{ number_format($visitors) }}</div>
                <div class="l">{{ Str::plural('person', $visitors) }} visited</div>
            </td>
            <td>
                <div class="n">{{ number_format($visits) }}</div>
                <div class="l">{{ Str::plural('visit', $visits) }} in total</div>
            </td>
            <td>
                <div class="n">{{ number_format($tributeCount) }}</div>
                <div class="l">{{ Str::plural('tribute', $tributeCount) }} left</div>
            </td>
            <td>
                <div class="n">{{ number_format($shareCount) }}</div>
                <div class="l">{{ Str::plural('time', $shareCount) }} shared</div>
            </td>
        </tr>
    </table>

    @if ($firstVisit)
        <p class="note">
            First visit {{ \Carbon\Carbon::parse($firstVisit)->format('j F Y') }};
            most recent {{ \Carbon\Carbon::parse($lastVisit)->format('j F Y') }}.
            @if ($busiestDay && $busiestDay['visits'] > 0)
                The busiest day was {{ $busiestDay['date']->format('j F Y') }},
                with {{ number_format($busiestDay['visits']) }} {{ Str::plural('visit', $busiestDay['visits']) }}.
            @endif
        </p>
    @endif

    @if ($series->isNotEmpty() && $series->max('visits') > 0)
        @php $peak = max(1, $series->max('visits')); @endphp

        <h2>Visits over time</h2>

        {{-- Bars drawn as table cells: dompdf has no flexbox, and a table is the one
             layout primitive it renders identically every time. Days with no visits keep
             a hairline so a gap reads as zero rather than as missing time. --}}
        <table class="chart" style="height: 92px;">
            <tr>
                @foreach ($series as $point)
                    <td>
                        <div class="bar" style="height: {{ $point['visits'] > 0 ? max(2, round($point['visits'] / $peak * 88)) : 1 }}px;"></div>
                    </td>
                @endforeach
            </tr>
        </table>
        <table class="axis">
            <tr>
                <td>{{ $series->first()['label'] }}</td>
                <td style="text-align: right;">{{ $series->last()['label'] }}</td>
            </tr>
        </table>
    @endif

    @if ($tributesByType->isNotEmpty() || $sharesByChannel->isNotEmpty())
        <h2>Tributes and sharing</h2>

        <table>
            <tr>
                <td style="width: 50%; vertical-align: top; padding-right: 18px;">
                    @if ($tributesByType->isNotEmpty())
                        <table class="breakdown">
                            @foreach ($tributesByType as $type => $count)
                                <tr>
                                    <td>{{ ucfirst($type) }}s</td>
                                    <td class="v">{{ number_format($count) }}</td>
                                </tr>
                            @endforeach
                        </table>
                    @else
                        <p class="quiet">No tributes left yet.</p>
                    @endif
                </td>
                <td style="width: 50%; vertical-align: top;">
                    @if ($sharesByChannel->isNotEmpty())
                        <table class="breakdown">
                            @foreach ($sharesByChannel as $channel => $count)
                                <tr>
                                    <td>Shared via {{ ucfirst($channel) }}</td>
                                    <td class="v">{{ number_format($count) }}</td>
                                </tr>
                            @endforeach
                        </table>
                    @else
                        <p class="quiet">Not shared yet.</p>
                    @endif
                </td>
            </tr>
        </table>
    @endif

    <h2>The memorial itself</h2>

    <table class="breakdown">
        <tr><td>Story chapters published</td><td class="v">{{ number_format($chapterCount) }}</td></tr>
        <tr><td>Photographs in the gallery</td><td class="v">{{ number_format($photoCount) }}</td></tr>
        <tr><td>Videos in the gallery</td><td class="v">{{ number_format($videoCount) }}</td></tr>
        @if ($collaborators->isNotEmpty())
            <tr>
                <td>People helping to look after it</td>
                <td class="v">{{ $collaborators->count() }}</td>
            </tr>
        @endif
    </table>

    @if ($collaborators->isNotEmpty())
        <p class="note">
            {{ $collaborators->map(fn ($c) => $c->user?->name)->filter()->join(', ', ' and ') }}.
        </p>
    @endif

    @if ($messagesIncluded && $messages->isNotEmpty())
        <h2>What people wrote</h2>

        @foreach ($messages as $message)
            <div class="message">
                <div class="body">&ldquo;{{ $message->message }}&rdquo;</div>
                <div class="who">
                    {{ $message->user?->name ?? $message->guest_name ?? 'A visitor' }}
                    &middot; {{ $message->created_at->format('j F Y') }}
                </div>
            </div>
        @endforeach

        @if ($tributeCount > $messages->count())
            <p class="note">
                Showing {{ $messages->count() }} of {{ number_format($tributeCount) }} tributes. The rest can be read on the memorial page.
            </p>
        @endif
    @endif
</body>
</html>
