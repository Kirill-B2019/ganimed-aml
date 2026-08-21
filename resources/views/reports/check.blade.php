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
        .muted { color: #555; font-size: 10px; }
        .brand { margin: 0 0 14px; padding-bottom: 10px; border-bottom: 2px solid #0C0C0D; }
        .brand img { width: 22px; height: 22px; vertical-align: middle; }
        .brand-name { display: inline-block; margin-left: 8px; font-size: 12px; letter-spacing: 2px; color: #0C0C0D; vertical-align: middle; }
        .brand-aml { color: #6F6E69; }
        .pill { display: inline-block; padding: 2px 7px; font-size: 9px; border: 1px solid #d1d5db; margin: 0 4px 6px 0; }
        .pill-success { background: #ecfdf5; }
        .pill-warning { background: #fffbeb; }
        .pill-danger { background: #fef2f2; }
        .pill-neutral { background: #f3f4f6; }
        .callout { border: 1px solid #f59e0b; background: #fffbeb; padding: 8px 10px; margin: 10px 0 12px; }
        .stats td { border: 1px solid #e5e7eb; padding: 8px; width: 25%; }
        .stats .v { font-size: 14px; font-weight: bold; }
        .stats .l { font-size: 9px; color: #555; margin-top: 2px; }
        .stat-success { background: #ecfdf5; }
        .stat-warning { background: #fffbeb; }
        .stat-danger { background: #fef2f2; }
        table { width: 100%; border-collapse: collapse; margin: 6px 0 10px; }
        th, td { border: 1px solid #e5e7eb; padding: 5px 7px; text-align: left; vertical-align: top; }
        th { background: #F3F2EE; font-size: 10px; color: #555; }
        .mono { font-family: DejaVu Sans, sans-serif; font-size: 9px; word-wrap: break-word; }
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
    <div class="muted">{{ $check->type->label() }} · {{ $sources }} · {{ $generatedAt }} · #{{ $check->id }}</div>
    <div>
        @foreach ($pills as $pill)
            <span class="pill pill-{{ $pill['tone'] }}">{{ $pill['label'] }}</span>
        @endforeach
    </div>

    @php
        $verdictTone = match ($check->verdict?->value) {
            'block' => 'danger',
            'review' => 'warning',
            default => 'success',
        };
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
            <td class="{{ ((int) $check->risk_score) > 0 ? 'stat-warning' : 'stat-success' }}">
                <div class="v">{{ $check->risk_score }}</div>
                <div class="l">{{ __('aml.flag_score') }}</div>
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

    <div class="callout">
        <strong>{{ __('aml.reading_title') }}.</strong> {{ $readingNote }}
    </div>

    <h2>{{ __('aml.object_title') }}</h2>
    <table>
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

    @if ($showRadar && ! empty($radarAxes))
        <h2>{{ __('aml.radar_title') }}</h2>
        <p class="muted">{{ __('aml.radar_hint') }}</p>
        <table class="chart">
            @foreach ($radarAxes as $axis)
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
    @endif

    @if (! empty($flagRows))
        <h3>{{ __('aml.provider_decode') }}</h3>
        <table>
            <thead>
                <tr>
                    <th>{{ __('aml.api_field') }}</th>
                    <th>{{ __('aml.flag_value') }}</th>
                    <th>{{ __('aml.score_points') }}</th>
                    <th>{{ __('aml.meaning') }}</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($flagRows as $i => $row)
                    @php($hot = ! in_array((string) $row['value'], ['0', '—', '', '[]'], true))
                    <tr class="{{ $hot ? 'row-warning' : ($i % 2 === 1 ? 'row-stripe' : '') }}">
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

    @if (! empty($scoreBreakdown))
        <h3>{{ __('aml.score_title') }}</h3>
        <p>{{ __('aml.score_how') }}</p>
        <p class="muted">{{ $scoreBreakdown['formula'] }}</p>
        @if (! empty($scoreBreakdown['lines']))
            <table>
                <thead>
                    <tr>
                        <th>{{ __('aml.field') }}</th>
                        <th>{{ __('aml.score_rule') }}</th>
                        <th>{{ __('aml.score_points') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($scoreBreakdown['lines'] as $line)
                        <tr class="{{ ($line['severity'] ?? '') === 'block' ? 'row-danger' : '' }}">
                            <td>{{ $line['label'] }}</td>
                            <td>{{ $line['rule'] }}</td>
                            <td>{{ ((int) $line['points']) > 0 ? '+' : '' }}{{ (int) $line['points'] }}</td>
                        </tr>
                    @endforeach
                    <tr>
                        <td><strong>{{ __('aml.score_total') }}</strong></td>
                        <td>{{ $scoreBreakdown['formula'] }}</td>
                        <td><strong>{{ (int) $scoreBreakdown['total'] }}</strong></td>
                    </tr>
                </tbody>
            </table>
        @endif
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
        @if (! empty($tokenPieSlices))
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
        <table>
            <thead>
                <tr>
                    <th>{{ __('aml.inflow_asset') }}</th>
                    <th>{{ __('aml.inflow_amount') }}</th>
                    <th>{{ __('aml.usd') }}</th>
                    <th>{{ __('aml.col_contract') }}</th>
                    <th>{{ __('aml.token_status') }}</th>
                    <th>{{ __('aml.comment') }}</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($balanceRows as $row)
                    <tr class="{{ ($row['tone'] ?? '') !== '' && ($row['tone'] ?? '') !== 'neutral' ? 'row-'.$row['tone'] : '' }}">
                        <td>{{ $row['symbol'] }} {{ $row['name'] ?? '' }}</td>
                        <td>{{ $row['amount'] }}</td>
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
                        <td>{{ $row['comment'] }}</td>
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
        <table>
            <thead>
                <tr>
                    <th>{{ __('aml.inflow_from') }}</th>
                    <th>{{ __('aml.inflow_asset') }}</th>
                    <th>{{ __('aml.inflow_amount') }}</th>
                    <th>{{ __('aml.comment') }}</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($inflowRows as $row)
                    <tr class="{{ ! empty($row['tone']) ? 'row-'.$row['tone'] : '' }}">
                        <td class="mono">{{ $row['from'] }}</td>
                        <td>{{ $row['symbol'] }}</td>
                        <td>{{ $row['amount'] }}</td>
                        <td>{{ $row['comment'] }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif

    <div class="rule"></div>
    <h2>{{ __('aml.conclusion_title') }}</h2>
    @foreach ($conclusion as $paragraph)
        <p>{{ $paragraph }}</p>
    @endforeach
</body>
</html>
