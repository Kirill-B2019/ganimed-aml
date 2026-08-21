{{-- | KB @CerberRus00 - Nexus Invest Team --}}
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>{{ $reportTitle }} #{{ $check->id }}</title>
    <style>
        @page { margin: 28px 32px 52px 32px; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 11px; color: #111; }
        h1 { font-size: 18px; margin: 0 0 4px; font-weight: bold; }
        h2 { font-size: 13px; margin: 18px 0 6px; }
        h3 { font-size: 12px; margin: 12px 0 6px; }
        p { margin: 0 0 8px; line-height: 1.45; }
        .muted { color: #555; font-size: 10px; margin: 0 0 10px; line-height: 14px; }
        table.pills { width: auto; border-collapse: separate; border-spacing: 4px 0; margin: 0 0 12px; }
        table.pills td { border: 1px solid #d1d5db; padding: 3px 7px; font-size: 9px; white-space: nowrap; }
        .brand { margin: 0 0 14px; padding-bottom: 10px; border-bottom: 2px solid #0C0C0D; }
        .brand img { width: 22px; height: 22px; vertical-align: middle; }
        .brand-name { display: inline-block; margin-left: 8px; font-size: 12px; letter-spacing: 2px; color: #0C0C0D; vertical-align: middle; }
        .brand-aml { color: #6F6E69; }
        .pill-success { background: #ecfdf5; }
        .pill-caution { background: #fefce8; }
        .pill-warning { background: #fffbeb; }
        .pill-severe { background: #fff7ed; }
        .pill-danger { background: #fef2f2; }
        .pill-neutral { background: #f3f4f6; }
        .callout { border: 1px solid #f59e0b; background: #fffbeb; padding: 8px 10px; margin: 10px 0 12px; }
        .stats td { border: 1px solid #e5e7eb; padding: 8px; width: 25%; }
        .stats .v { font-size: 14px; font-weight: bold; }
        .stats .l { font-size: 9px; color: #555; margin-top: 2px; }
        .stat-success { background: #ecfdf5; }
        .stat-caution { background: #fefce8; }
        .stat-warning { background: #fffbeb; }
        .stat-severe { background: #fff7ed; }
        .stat-danger { background: #fef2f2; }
        table { width: 100%; border-collapse: collapse; margin: 6px 0 10px; }
        th, td { border: 1px solid #e5e7eb; padding: 5px 7px; text-align: left; vertical-align: top; }
        th { background: #F3F2EE; font-size: 10px; color: #555; }
        table.sheet { table-layout: fixed; }
        table.sheet th, table.sheet td { word-wrap: break-word; }
        table.sheet tr { page-break-inside: avoid; }
        .mono { font-family: DejaVu Sans, sans-serif; font-size: 8px; word-wrap: break-word; word-break: break-all; }
        .num { word-break: break-all; }
        a { color: #111827; text-decoration: none; }
        .graph { text-align: center; margin: 6px 0 10px; page-break-inside: avoid; }
        .graph img { width: 520px; height: 400px; border: none; }
        .legend { border-collapse: collapse; margin: 0 0 10px; width: auto; }
        .legend td { border: none; padding: 2px 8px 2px 0; font-size: 9px; color: #555; vertical-align: middle; }
        .row-success { background: #ecfdf5; }
        .row-warning { background: #fffbeb; }
        .row-danger { background: #fef2f2; }
        .row-info { background: #eff6ff; }
        .row-stripe { background: #f8fafc; }
        table.chart td { vertical-align: middle; }
        table.hbar, table.stackbar { width: 100%; border-collapse: collapse; margin: 0; }
        table.hbar td, table.stackbar td { border: none !important; padding: 0 !important; font-size: 1px; line-height: 12px; height: 12px; }
        table.stackbar td { height: 16px; line-height: 16px; }
        table.hbar .hbar-track { background-color: #e2e8f0; }
        .chart-val { white-space: nowrap; font-size: 10px; }
        .cards td { border: 1px solid #e5e7eb; width: 50%; vertical-align: top; }
        .cards h3 { margin: 0 0 6px; font-size: 11px; }
        .usd { font-size: 14px; font-weight: bold; margin: 0 0 6px; }
        .rule { border-top: 1px solid #e5e7eb; margin: 16px 0 10px; }
        footer {
            position: fixed; left: 0; right: 0; bottom: -36px; height: 28px;
            font-size: 8px; color: #555; border-top: 1px solid #d1d5db; padding-top: 5px; text-align: center;
        }
    </style>
</head>
<body>
    <footer>{{ $footer }}</footer>

    <div class="brand">
        @php
            $logoSrc = str_replace('\\', '/', (string) ($logoMark ?? public_path('images/logo-gnd-mark.png')));
            if (! str_starts_with($logoSrc, 'file:')) {
                $logoSrc = 'file:///'.$logoSrc;
            }
        @endphp
        <img src="{{ $logoSrc }}" width="22" height="22" alt="">
        <span class="brand-name">GANIMED <span class="brand-aml">AML</span></span>
    </div>
    <h1>{{ $reportTitle }}</h1>
    <p class="muted">{{ $check->type->label() }} · {{ $sources }} · {{ $generatedAt }} · #{{ $check->id }}</p>
    @if (! empty($pills))
        <table class="pills">
            <tr>
                @foreach ($pills as $pill)
                    <td class="pill-{{ $pill['tone'] }}">{{ $pill['label'] }}</td>
                @endforeach
            </tr>
        </table>
    @endif

    @php
        $verdictTone = match ($check->verdict?->value) {
            'block' => 'danger',
            'review' => 'warning',
            default => 'success',
        };
        $riskGrade = $riskGrade ?? null;
        $scoreTone = $riskGrade?->tone() ?? (((int) $check->risk_score) > 0 ? 'warning' : 'success');
        $isMultisig = $showOnchain && ($onchain['control']['type'] ?? '') === 'multisig';
        $pieMax = max(1, collect($tokenPieSlices ?? [])->sum('value'));
        $officialUsdt = 'TR7NHqjeKQxGTCi8q8ZY4pL8otSzgjLj6t';
    @endphp

    <table class="stats">
        <tr>
            <td class="stat-{{ $verdictTone }}">
                <div class="v">{{ $check->verdict?->label() ?? '—' }}</div>
                <div class="l">{{ __('aml.verdict') }}</div>
            </td>
            <td class="stat-{{ $scoreTone }}">
                <div class="v">
                    @if ($riskGrade)
                        {{ $riskGrade->label() }} · {{ $check->risk_score }}
                    @else
                        {{ $check->risk_score }}
                    @endif
                </div>
                <div class="l">{{ $riskGrade ? __('aml.risk_grade') : __('aml.flag_score') }}</div>
            </td>
            @if ($isWalletReport)
                <td class="{{ $isMultisig ? 'stat-warning' : '' }}">
                    <div class="v">
                        @if ($isMultisig)
                            {{ __('aml.multisig') }} {{ $onchain['control']['threshold'] ?? '' }}
                        @elseif ($showOnchain)
                            {{ __('aml.single_key') }}
                        @else
                            —
                        @endif
                    </div>
                    <div class="l">{{ __('aml.control_keys') }}</div>
                </td>
                <td>
                    <div class="v">
                        @if ($nativeRow)
                            {{ $nativeRow['amount'] }} {{ $nativeRow['symbol'] ?? '' }}
                        @else
                            —
                        @endif
                    </div>
                    <div class="l">{{ __('aml.native_balance') }}</div>
                </td>
            @else
                <td>
                    <div class="v">{{ $check->type->label() }}</div>
                    <div class="l">{{ __('aml.type') }}</div>
                </td>
                <td>
                    <div class="v">{{ $check->chainName() ?? '—' }}</div>
                    <div class="l">{{ __('aml.chain') }}</div>
                </td>
            @endif
        </tr>
    </table>

    @if ($riskGrade && ! empty($riskGradeLegend))
        <p class="muted" style="margin-bottom: 4px;">{{ __('aml.risk_grade_legend') }}. {{ __('aml.risk_grade_legend_hint') }}</p>
        <table class="legend">
            <tr>
                @foreach ($riskGradeLegend as $item)
                    <td style="background-color: {{ $item['swatch'] }}; width: 8px; padding: 0;">&nbsp;</td>
                    <td @if ($riskGrade->value === $item['key']) style="font-weight: bold;" @endif>{{ $item['label'] }} {{ $item['range'] }}</td>
                @endforeach
            </tr>
        </table>
        <p class="muted">{{ $riskGrade->hint() }}</p>
    @endif

    <div class="callout">
        <strong>{{ __('aml.reading_title') }}.</strong> {{ $readingNote }}
    </div>

    <h2>{{ __('aml.conclusion_title') }}</h2>
    @foreach ($conclusion as $paragraph)
        <p>{{ $paragraph }}</p>
    @endforeach

    <h2>{{ __('aml.object_title') }}</h2>
    <table class="sheet">
        <colgroup>
            <col style="width: 28%">
            <col style="width: 72%">
        </colgroup>
        <thead>
            <tr>
                <th>{{ __('aml.field') }}</th>
                <th>{{ __('aml.value') }}</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($objectRows as $i => $row)
                <tr class="{{ $i % 2 === 1 ? 'row-stripe' : '' }}">
                    <td>{{ $row['label'] }}</td>
                    <td class="mono">
                        @if (! empty($row['href']))
                            <a href="{{ $row['href'] }}">{{ $row['value'] }}</a>
                        @else
                            {{ $row['value'] }}
                        @endif
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>

    @if ($showTokenOnchain ?? false)
        <h2>{{ __('aml.tronscan_contract') }}</h2>
        <p class="muted">{{ __('aml.tronscan_contract_hint') }}</p>
        @if ($tokenTronscanError ?? '')
            <p class="muted">{{ __('aml.tronscan_error') }}: {{ $tokenTronscanError }}</p>
        @elseif (($tokenTronscanSkipped ?? false) || empty($tronscanContract))
            <p class="muted">{{ __('aml.tronscan_unavailable') }}</p>
        @else
        <table class="sheet">
            <colgroup>
                <col style="width: 28%">
                <col style="width: 72%">
            </colgroup>
            <tbody>
                @foreach ($tronscanContract as $i => $row)
                    <tr class="{{ $i % 2 === 1 ? 'row-stripe' : '' }}">
                        <td>{{ $row['label'] }}</td>
                        <td class="mono">
                            @if (! empty($row['href']))
                                <a href="{{ $row['href'] }}">{{ $row['value'] }}</a>
                            @else
                                {{ $row['value'] }}
                            @endif
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
        @endif
        @if (! empty($tokenPassport))
            <h2>{{ __('aml.token_passport') }}</h2>
            <p class="muted">{{ __('aml.token_passport_hint') }}</p>
            <table class="sheet">
                <colgroup>
                    <col style="width: 28%">
                    <col style="width: 72%">
                </colgroup>
                <tbody>
                    @foreach ($tokenPassport as $i => $row)
                        <tr class="{{ $i % 2 === 1 ? 'row-stripe' : '' }}">
                            <td>{{ $row['label'] }}</td>
                            <td class="mono">
                                @if (! empty($row['href']))
                                    <a href="{{ $row['href'] }}">{{ $row['value'] }}</a>
                                @else
                                    {{ $row['value'] }}
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    @endif

    @if ($showRadar && ! empty($hotRadarAxes) && empty($compact))
        <h2>{{ __('aml.radar_title') }}</h2>
        <p class="muted">{{ __('aml.radar_hint') }}</p>
        <table class="chart">
            @foreach ($hotRadarAxes as $axis)
                @php($pct = max(0, min(100, (int) $axis['value'])))
                <tr>
                    <td style="width: 28%;">{{ __('aml.radar.'.$axis['key']) }}</td>
                    <td>
                        <x-pdf-hbar :pct="$pct" :tone="$pct > 0 ? 'danger' : 'success'" />
                    </td>
                    <td class="chart-val" style="width: 12%;">{{ $axis['value'] }}</td>
                </tr>
            @endforeach
        </table>
        @if (($quietRadarCount ?? 0) > 0)
            <p class="muted">{{ __('aml.radar_quiet', ['count' => $quietRadarCount]) }}</p>
        @endif
    @endif

    <h3>{{ __('aml.why_title') }}</h3>
    @if (empty($hotFlags))
        <p class="muted">{{ __('aml.flags_none') }}</p>
    @else
        <table class="sheet">
            <thead>
                <tr>
                    <th>{{ __('aml.api_field') }}</th>
                    <th>{{ __('aml.flag_value') }}</th>
                    <th>{{ __('aml.score_points') }}</th>
                    <th>{{ __('aml.meaning') }}</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($hotFlags as $i => $row)
                    <tr class="row-warning">
                        <td class="mono">{{ $row['field'] }}</td>
                        <td>{{ $row['value'] }}</td>
                        <td>
                            @if ((int) ($row['points'] ?? 0) === 100)
                                {{ __('aml.verdicts.block') }} (100)
                            @elseif ((int) ($row['points'] ?? 0) > 0)
                                +{{ (int) $row['points'] }}
                            @else
                                0
                            @endif
                        </td>
                        <td>{{ $row['meaning'] }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif

    @if (! empty($quietFlags) && empty($compact))
        <p class="muted">{{ __('aml.flags_quiet') }}: {{ count($quietFlags) }}</p>
    @endif

    @if (! empty($scoreBreakdown) && empty($compact))
        <h3>{{ __('aml.score_title') }}</h3>
        <p class="muted">{{ $scoreBreakdown['formula'] }}</p>
    @endif

    @if ($showOnchain)
        <h2>{{ __('aml.control') }}</h2>
        <p>{{ $controlNarrative }}</p>
        @if (! empty($signerRows))
            <table>
                <thead>
                    <tr>
                        <th>{{ __('aml.signers') }}</th>
                        <th>{{ __('aml.threshold') }}</th>
                        <th>{{ __('aml.role') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($signerRows as $row)
                        <tr class="row-{{ $row['tone'] }}">
                            <td class="mono">{{ $row['address'] }}</td>
                            <td>{{ $row['weight'] }}</td>
                            <td>{{ $row['role'] }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif

        <h2>{{ __('aml.asset_narrative') }}</h2>
        @if (! empty($assetNarrative))
            <p>{{ $assetNarrative }}</p>
        @endif
        @if (! empty($tokenPieSlices) && empty($compact))
            <h3>{{ __('aml.token_pie_title') }}</h3>
            <p class="muted">{{ __('aml.token_pie_hint') }}</p>
            <table class="stackbar" cellpadding="0" cellspacing="0">
                <tr>
                    @foreach ($tokenPieSlices as $slice)
                        <td style="width: {{ (int) ($slice['pct'] ?? 0) }}%; background-color: {{ $slice['color'] ?? '#64748b' }};">&nbsp;</td>
                    @endforeach
                </tr>
            </table>
            <table class="chart">
                @foreach ($tokenPieSlices as $slice)
                    @php($pct = (int) ($slice['pct'] ?? round(100 * $slice['value'] / $pieMax)))
                    <tr>
                        <td style="width: 8px; background-color: {{ $slice['color'] ?? '#64748b' }};">&nbsp;</td>
                        <td style="width: 30%;">{{ __('aml.pie_'.$slice['key']) }}</td>
                        <td>
                            <x-pdf-hbar
                                :pct="$pct"
                                :color="$slice['color'] ?? null"
                                :tone="match ($slice['key']) {
                                    'native', 'canonical' => 'success',
                                    'lookalike' => 'warning',
                                    'ignore' => 'neutral',
                                    default => 'danger',
                                }"
                            />
                        </td>
                        <td class="chart-val" style="width: 16%;">{{ $slice['value'] }} · {{ $pct }}%</td>
                    </tr>
                @endforeach
            </table>
        @endif

        <h2>{{ __('aml.balances') }}</h2>
        <p class="muted">{{ __('aml.canonical_usdt_hint', ['contract' => $officialUsdt]) }}</p>
        <table class="sheet">
            <colgroup>
                <col style="width: 16%">
                <col style="width: 16%">
                <col style="width: 12%">
                <col style="width: 34%">
                <col style="width: 22%">
            </colgroup>
            <thead>
                <tr>
                    <th>{{ __('aml.inflow_asset') }}</th>
                    <th>{{ __('aml.inflow_amount') }}</th>
                    <th>{{ __('aml.usd') }}</th>
                    <th>{{ __('aml.col_contract') }}</th>
                    <th>{{ __('aml.token_status') }}</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($balanceRows as $row)
                    <tr class="{{ ($row['tone'] ?? '') !== '' && ($row['tone'] ?? '') !== 'neutral' ? 'row-'.$row['tone'] : '' }}">
                        <td>{{ $row['label'] ?? $row['symbol'] }}</td>
                        <td class="num">{{ $row['amount'] }}</td>
                        <td>
                            @if (isset($row['usd']) && $row['usd'] !== null)
                                ${{ number_format((float) $row['usd'], 2, '.', ' ') }}
                            @else
                                —
                            @endif
                        </td>
                        <td class="mono">{{ $row['contract'] ?? __('aml.native_balance') }}</td>
                        <td>
                            @if (in_array($row['kind'] ?? '', ['native', 'canonical'], true))
                                {{ __('aml.token_status_locked') }}
                            @else
                                {{ __('aml.token_kind_'.($row['kind'] ?? 'noise')) }}
                            @endif
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>

        <table class="cards">
            <tr>
                <td>
                    <h3>{{ __('aml.money_count_title') }}</h3>
                    <p class="usd">{{ __('aml.wallet_usd') }}: {{ $usdSummary['formatted'] ?? '—' }}</p>
                    <p>{{ __('aml.money_count_body', ['amount' => $usdSummary['formatted'] ?? '—']) }}</p>
                </td>
                <td>
                    <h3>{{ __('aml.money_junk_title') }}</h3>
                    <p>{{ __('aml.money_junk_body') }}</p>
                </td>
            </tr>
        </table>

        @if (empty($compact))
        <h2>{{ __('aml.wallet_graph') }}</h2>
        <p class="muted">{{ __('aml.wallet_graph_hint') }}</p>
        @if (! empty($walletGraphPending))
            <p class="muted">{{ __('aml.graph_pending') }}</p>
        @endif
        @if (! empty($walletGraphSvg))
            <div class="graph">{!! $walletGraphSvg !!}</div>
            @if (! empty($walletGraphLegend))
            <table class="legend">
                <tr>
                    @foreach ($walletGraphLegend as $kind => $color)
                        <td style="background-color: {{ $color }}; width: 8px; padding: 0;">&nbsp;</td>
                        <td>{{ __('aml.graph_kind_'.$kind) }}</td>
                    @endforeach
                </tr>
            </table>
            @endif
            @if (! empty($walletGraphPeers))
            <h3>{{ __('aml.graph_peers') }}</h3>
            <table class="sheet">
                <colgroup>
                    <col style="width: 6%">
                    <col style="width: 38%">
                    <col style="width: 22%">
                    <col style="width: 22%">
                    <col style="width: 12%">
                </colgroup>
                <thead>
                    <tr>
                        <th>#</th>
                        <th>{{ __('aml.graph_peer') }}</th>
                        <th>{{ __('aml.graph_peer_status') }}</th>
                        <th>{{ __('aml.graph_peer_assets') }}</th>
                        <th>{{ __('aml.graph_edge_in') }} / {{ __('aml.graph_edge_out') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($walletGraphPeers as $peer)
                        <tr>
                            <td>{{ $peer['n'] ?? '' }}</td>
                            <td class="mono">
                                @if (! empty($peer['explorer']))
                                    <a href="{{ $peer['explorer'] }}">{{ $peer['id'] }}</a>
                                @else
                                    {{ $peer['id'] }}
                                @endif
                            </td>
                            <td>
                                @foreach ($peer['status'] as $status)
                                    {{ __('aml.graph_kind_'.$status) }}@if (! $loop->last), @endif
                                @endforeach
                            </td>
                            <td>{{ $peer['assets'] !== '' ? $peer['assets'] : '—' }}</td>
                            <td>{{ (int) ($peer['in_count'] ?? 0) }} / {{ (int) ($peer['out_count'] ?? 0) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
            @endif
            @if (! empty($walletGraph['truncated']))
                <p class="muted">{{ __('aml.graph_truncated') }}</p>
            @endif
        @elseif (empty($walletGraphPending))
            <p class="muted">{{ __('aml.graph_empty') }}</p>
        @endif

        <h2>{{ __('aml.inflows') }}</h2>
        <p class="muted">{{ __('aml.inflow_hint') }}</p>
        @if (! empty($inflowBars) && collect($inflowBars)->sum('value') > 0)
            <h3>{{ __('aml.inflow_bar_title') }}</h3>
            <table class="chart">
                @foreach ($inflowBars as $bar)
                    @php($pct = (int) ($bar['pct'] ?? 0))
                    <tr>
                        <td style="width: 32%;">{{ __('aml.inflow_bar_'.$bar['key']) }}</td>
                        <td>
                            <x-pdf-hbar
                                :pct="$pct"
                                :tone="match ($bar['key']) {
                                    'trx', 'stable' => 'success',
                                    'dust' => 'warning',
                                    default => 'danger',
                                }"
                            />
                        </td>
                        <td class="chart-val" style="width: 12%;">{{ $bar['value'] }}</td>
                    </tr>
                @endforeach
            </table>
        @endif
        @if (empty($inflowRows))
            <p class="muted">{{ __('aml.no_inflows') }}</p>
        @else
        <table class="sheet">
            <colgroup>
                <col style="width: 28%">
                <col style="width: 16%">
                <col style="width: 14%">
                <col style="width: 10%">
                <col style="width: 32%">
            </colgroup>
            <thead>
                <tr>
                    <th>{{ __('aml.inflow_from') }}</th>
                    <th>{{ __('aml.inflow_asset') }}</th>
                    <th>{{ __('aml.inflow_amount') }}</th>
                    <th>{{ __('aml.inflow_count') }}</th>
                    <th>{{ __('aml.comment') }}</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($inflowRows as $row)
                    @php($fromHref = $row['explorer'] ?? \App\Support\TronAddress::explorerUrl((string) ($row['from'] ?? '')))
                    <tr class="{{ ! empty($row['tone']) ? 'row-'.$row['tone'] : '' }}">
                        <td class="mono">
                            @if ($fromHref)
                                <a href="{{ $fromHref }}">{{ $row['from'] }}</a>
                            @else
                                {{ $row['from'] }}
                            @endif
                        </td>
                        <td>
                            {{ $row['symbol'] }}
                            @if (! empty($row['contract']))
                                @php($contractHref = \App\Support\TronAddress::explorerUrl((string) $row['contract']))
                                <div class="mono">
                                    @if ($contractHref)
                                        <a href="{{ $contractHref }}">{{ \App\Support\TronAddress::short((string) $row['contract']) }}</a>
                                    @else
                                        {{ \App\Support\TronAddress::short((string) $row['contract']) }}
                                    @endif
                                </div>
                            @endif
                        </td>
                        <td class="num">{{ $row['amount'] }}</td>
                        <td>{{ $row['tx_count'] ?? '—' }}</td>
                        <td>{{ $row['comment'] }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
        @endif

        <h2>{{ __('aml.outflows') }}</h2>
        <p class="muted">{{ __('aml.outflow_hint') }}</p>
        @if (empty($outflowRows))
            <p class="muted">{{ __('aml.no_outflows') }}</p>
        @else
        <table class="sheet">
            <colgroup>
                <col style="width: 28%">
                <col style="width: 16%">
                <col style="width: 14%">
                <col style="width: 10%">
                <col style="width: 32%">
            </colgroup>
            <thead>
                <tr>
                    <th>{{ __('aml.outflow_to') }}</th>
                    <th>{{ __('aml.inflow_asset') }}</th>
                    <th>{{ __('aml.inflow_amount') }}</th>
                    <th>{{ __('aml.inflow_count') }}</th>
                    <th>{{ __('aml.comment') }}</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($outflowRows as $row)
                    @php($toHref = $row['explorer'] ?? \App\Support\TronAddress::explorerUrl((string) ($row['to'] ?? '')))
                    <tr class="{{ ! empty($row['tone']) ? 'row-'.$row['tone'] : '' }}">
                        <td class="mono">
                            @if ($toHref)
                                <a href="{{ $toHref }}">{{ $row['to'] }}</a>
                            @else
                                {{ $row['to'] }}
                            @endif
                        </td>
                        <td>
                            {{ $row['symbol'] }}
                            @if (! empty($row['contract']))
                                @php($contractHref = \App\Support\TronAddress::explorerUrl((string) $row['contract']))
                                <div class="mono">
                                    @if ($contractHref)
                                        <a href="{{ $contractHref }}">{{ \App\Support\TronAddress::short((string) $row['contract']) }}</a>
                                    @else
                                        {{ \App\Support\TronAddress::short((string) $row['contract']) }}
                                    @endif
                                </div>
                            @endif
                        </td>
                        <td class="num">{{ $row['amount'] }}</td>
                        <td>{{ $row['tx_count'] ?? '—' }}</td>
                        <td>{{ $row['comment'] }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
        @endif
        @endif
    @endif
</body>
</html>
