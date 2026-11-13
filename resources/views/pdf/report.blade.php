@php
    $colours = [
        'registered' => '#136ea9',
        'speaker'    => '#f43f5e',
        'partner'    => '#7a2e83',
        'invited'    => '#f59e0b',
        'walkin'     => '#10b981',
    ];
    $currency = config('checkin.currency');
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>{{ config('checkin.event_name') }}: Attendance report</title>
    <style>
        @page { margin: 14mm 14mm 16mm 14mm; }
        body { font-family: "DejaVu Sans", sans-serif; font-size: 9pt; color: #292929; }

        /* letterhead: full width across the top of page 1 */
        .letterhead { position: absolute; top: -14mm; left: -14mm; width: 210mm; }
        .letterhead img { width: 210mm; display: block; }
        .letterhead-space { height: 42mm; }

        /* footer on every page */
        footer { position: fixed; bottom: -10mm; left: 0; right: 0; font-size: 7pt; color: #9ca3af; }
        footer .page:after { content: counter(page); }

        h1 { font-size: 16pt; color: #7a2e83; margin: 0; }
        h2 { font-size: 10.5pt; margin: 7mm 0 2.5mm; padding-bottom: 1mm; border-bottom: 1.5px solid #7a2e83; }
        .muted { color: #6b7280; }
        .small { font-size: 7.5pt; }
        .right { text-align: right; }
        .center { text-align: center; }

        table { width: 100%; border-collapse: collapse; }

        /* headline numbers */
        .kpis td { width: 25%; padding: 3mm; border: 1px solid #e5e7eb; background: #fafafa; vertical-align: top; }
        .kpi-label { font-size: 7pt; color: #6b7280; text-transform: uppercase; letter-spacing: 0.3px; }
        .kpi-value { font-size: 15pt; font-weight: bold; margin-top: 1mm; }

        /* bar chart */
        .chart td { padding: 1.3mm 0; vertical-align: middle; }
        .chart .label { width: 32mm; padding-right: 3mm; }
        .chart .value { width: 22mm; padding-left: 3mm; text-align: right; white-space: nowrap; }
        .bar-expected { height: 4.5mm; background: #e5e7eb; }
        .bar-in { height: 4.5mm; }
        .legend span { display: inline-block; width: 3mm; height: 3mm; margin: 0 1mm 0 4mm; vertical-align: middle; }

        /* data tables */
        .grid th { background: #7a2e83; color: #fff; font-weight: bold; text-align: left; padding: 1.6mm 2mm; font-size: 8pt; }
        .grid td { padding: 1.4mm 2mm; border-bottom: 1px solid #eef0f3; font-size: 8pt; vertical-align: top; }
        .grid tr.alt td { background: #f9fafb; }
        .grid tr.total td { font-weight: bold; background: #f3e8f5; border-bottom: none; }
        .grid thead { display: table-header-group; }
        .grid tr { page-break-inside: avoid; }
        .in { color: #047857; font-weight: bold; }
        .tag { font-size: 7pt; color: #6b7280; }
        .page-break { page-break-before: always; }
    </style>
</head>
<body>

    <footer>
        {{ config('checkin.event_name') }} · Attendance report · Generated {{ $generatedAt->format('d M Y, H:i') }}
        <span style="float: right;">Page <span class="page"></span></span>
    </footer>

    {{-- ===== Letterhead (logo only) ===== --}}
    <div class="letterhead">
        <img src="{{ public_path('img/ggit-letterhead.jpg') }}" alt="">
    </div>
    <div class="letterhead-space"></div>

    {{-- ===== Title ===== --}}
    <h1>Attendance report</h1>
    <p class="muted" style="margin: 1mm 0 5mm;">
        {{ config('checkin.event_name') }} · Generated {{ $generatedAt->format('l d F Y, H:i') }}
    </p>

    {{-- ===== Headline numbers ===== --}}
    <table class="kpis">
        <tr>
            <td>
                <div class="kpi-label">Participants</div>
                <div class="kpi-value">{{ $total }}</div>
            </td>
            <td>
                <div class="kpi-label">Checked in</div>
                <div class="kpi-value" style="color: #047857;">{{ $checkedIn }}</div>
            </td>
            <td>
                <div class="kpi-label">Arrived</div>
                <div class="kpi-value" style="color: #7a2e83;">{{ $pct }}%</div>
            </td>
            <td>
                <div class="kpi-label">Collected today</div>
                <div class="kpi-value">{{ number_format($collectedToday) }} <span class="small muted">{{ $currency }}</span></div>
                <div class="small muted">{{ $paymentsToday }} {{ \Illuminate\Support\Str::plural('payment', $paymentsToday) }} at the door</div>
            </td>
        </tr>
    </table>

    {{-- ===== Bar chart ===== --}}
    <h2>Who attended</h2>
    <p class="small muted legend" style="margin: 0 0 2mm;">
        <span style="background: #7a2e83; margin-left: 0;"></span>Checked in
        <span style="background: #e5e7eb;"></span>Expected
    </p>
    <table class="chart">
        @foreach ($categories as $key => $c)
            <tr>
                <td class="label">{{ $c['label'] }}</td>
                <td>
                    <div class="bar-expected" style="width: {{ max(round($c['total'] / $maxCategory * 100, 1), 0.5) }}%;">
                        <div class="bar-in" style="width: {{ $c['pct'] }}%; background: {{ $colours[$key] ?? '#6b7280' }};"></div>
                    </div>
                </td>
                <td class="value"><strong>{{ $c['in'] }}</strong> / {{ $c['total'] }}</td>
            </tr>
        @endforeach
    </table>

    {{-- ===== Breakdown ===== --}}
    <h2>Breakdown by participant type</h2>
    <table class="grid">
        <thead>
            <tr>
                <th>Type</th>
                <th class="right">Expected</th>
                <th class="right">Checked in</th>
                <th class="right">Not arrived</th>
                <th class="right">% arrived</th>
                <th class="right">Collected ({{ $currency }})</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($categories as $c)
                <tr class="{{ $loop->even ? 'alt' : '' }}">
                    <td>{{ $c['label'] }}</td>
                    <td class="right">{{ $c['total'] }}</td>
                    <td class="right">{{ $c['in'] }}</td>
                    <td class="right">{{ $c['total'] - $c['in'] }}</td>
                    <td class="right">{{ $c['pct'] }}%</td>
                    <td class="right">{{ $c['amount'] ? number_format($c['amount']) : '—' }}</td>
                </tr>
            @endforeach
            <tr class="total">
                <td>Total</td>
                <td class="right">{{ $total }}</td>
                <td class="right">{{ $checkedIn }}</td>
                <td class="right">{{ $total - $checkedIn }}</td>
                <td class="right">{{ $pct }}%</td>
                <td class="right">{{ number_format(array_sum(array_column($categories, 'amount'))) }}</td>
            </tr>
        </tbody>
    </table>

    {{-- ===== Attendance list ===== --}}
    <div class="page-break"></div>
    <h1 style="font-size: 13pt;">Attendance list</h1>
    <p class="small muted" style="margin: 1mm 0 3mm;">Checked-in participants first, in order of arrival, then everyone not yet arrived.</p>

    <table class="grid">
        <thead>
            <tr>
                <th style="width: 7mm;">#</th>
                <th>Name</th>
                <th>Type</th>
                <th style="width: 24mm;">Phone</th>
                <th style="width: 20mm;">Checked in</th>
                <th class="right" style="width: 22mm;">Amount</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($people as $p)
                <tr class="{{ $loop->even ? 'alt' : '' }}">
                    <td class="muted">{{ $loop->iteration }}</td>
                    <td>{{ $p->full_name }}</td>
                    <td>
                        {{ $categories[$p->category]['label'] ?? $p->category }}
                        @if ($p->partner_name)<br><span class="tag">{{ $p->partner_name }}</span>@endif
                    </td>
                    <td>{{ $p->phone ?: '—' }}</td>
                    <td>
                        @if ($p->checked_in_at)
                            <span class="in">{{ $p->checked_in_at->format('H:i') }}</span>
                        @else
                            <span class="muted">—</span>
                        @endif
                    </td>
                    <td class="right">{{ $p->amount ? number_format($p->amount).' '.$currency : '—' }}</td>
                </tr>
            @empty
                <tr><td colspan="6" class="center muted">No participants.</td></tr>
            @endforelse
        </tbody>
    </table>

</body>
</html>