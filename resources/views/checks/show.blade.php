{{-- | KB @CerberRus00 - Nexus Invest Team --}}
<x-app-layout :title="$reportTitle ?? __('aml.report_title')">
    @php
        $isMultisig = $showOnchain && ($onchain['control']['type'] ?? '') === 'multisig';
        $scoreTone = ((int) ($check->risk_score ?? 0)) > 0 ? 'warning' : 'success';
        $officialUsdt = 'TR7NHqjeKQxGTCi8q8ZY4pL8otSzgjLj6t';
        $pieMax = max(1, collect($tokenPieSlices ?? [])->sum('value'));
        $inflowMax = max(1, collect($inflowBars ?? [])->max('value') ?? 1);
        $hotFlags = $hotFlags ?? [];
        $quietFlags = $quietFlags ?? [];
        $hotRadarAxes = $hotRadarAxes ?? [];
        $quietRadarCount = $quietRadarCount ?? 0;
        $freshness = $freshness ?? [];
        $delta = $delta ?? null;
    @endphp

    <div class="sticky top-14 z-20 border-b border-ink-line bg-ink-paper/95 backdrop-blur-sm">
        <div class="page flex flex-col gap-2 py-2 sm:flex-row sm:items-center sm:justify-between sm:py-2.5">
            <div class="flex min-w-0 items-center gap-3 text-sm">
                <a href="{{ route('checks.index') }}" class="shrink-0 text-ink-muted hover:text-ink">{{ __('aml.back_history') }}</a>
                <span class="hidden text-ink-line sm:inline">/</span>
                <span class="hidden truncate font-mono text-ink sm:inline">{{ $check->subject }}</span>
                <x-copy-button :text="$check->subject" class="hidden shrink-0 sm:inline" />
            </div>
            @if ($check->isCompleted() || auth()->user()->is_admin)
                <div class="flex flex-nowrap items-center gap-2 overflow-x-auto pb-0.5 [-ms-overflow-style:none] [scrollbar-width:none] sm:flex-wrap sm:overflow-visible [&::-webkit-scrollbar]:hidden">
                    @if ($check->isCompleted())
                        <x-verdict-badge :verdict="$check->verdict" />
                        <x-secondary-button :href="route('checks.pdf', [$check, 'variant' => 'file'])">{{ __('aml.pdf_file') }}</x-secondary-button>
                        <x-secondary-button :href="route('checks.pdf', [$check, 'variant' => 'full'])">{{ __('aml.pdf_full') }}</x-secondary-button>
                        <form method="POST" action="{{ route('checks.rerun', $check) }}" class="shrink-0">
                            @csrf
                            <x-primary-button>{{ __('aml.rerun') }}</x-primary-button>
                        </form>
                        <form method="POST" action="{{ route('watch.store') }}" class="flex shrink-0 items-center gap-2">
                            @csrf
                            <input type="hidden" name="check_id" value="{{ $check->id }}">
                            <select name="interval_days" class="ui-select w-20 text-sm" title="{{ __('aml.watch_interval') }}">
                                @foreach ([1, 3, 7, 14, 30] as $n)
                                    <option value="{{ $n }}" @selected($n === 7)>{{ __('aml.watch_interval_n', ['n' => $n]) }}</option>
                                @endforeach
                            </select>
                            <x-secondary-button type="submit">
                                <span class="sm:hidden">{{ __('aml.watch_add_short') }}</span>
                                <span class="hidden sm:inline">{{ __('aml.watch_add') }}</span>
                            </x-secondary-button>
                        </form>
                    @endif
                    @if (auth()->user()->is_admin)
                        <form method="POST" action="{{ route('checks.destroy', $check) }}" class="shrink-0" onsubmit="return confirm(@js(__('aml.delete_check_confirm')))">
                            @csrf
                            @method('DELETE')
                            <x-danger-button>{{ __('aml.delete_check') }}</x-danger-button>
                        </form>
                    @endif
                </div>
            @endif
        </div>
    </div>

    <div
        class="py-8"
        @if ($check->isPending() || ($needsOnchainFetch ?? false))
            x-data="checkWaiter({
                pending: {{ $check->isPending() ? 'true' : 'false' }},
                enrich: {{ ($needsOnchainFetch ?? false) ? 'true' : 'false' }},
                statusUrl: @js(route('checks.status', $check)),
                enrichUrl: @js(route('checks.enrich', $check)),
                scanTitle: @js(__('aml.processing_title')),
                scanBody: @js(__('aml.processing_scan')),
                enrichBody: @js(__('aml.processing_onchain')),
            })"
        @endif
    >
        <div class="page space-y-8">
            @if (session('status'))
                <div class="ui-alert ui-alert-success">{{ session('status') }}</div>
            @endif
            @if (session('error'))
                <div class="ui-alert ui-alert-danger">{{ session('error') }}</div>
            @endif

            <div class="ui-panel space-y-5">
                <div class="flex flex-col gap-5 lg:flex-row lg:items-start lg:justify-between">
                    <div class="min-w-0">
                        <h1 class="text-2xl font-semibold tracking-tight text-ink">{{ $reportTitle ?? __('aml.report_title') }}</h1>
                        <p class="mt-1 text-sm text-ink-muted">
                            {{ $check->type->label() }}
                            @if ($check->chainName())
                                · {{ $check->chainName() }}
                            @endif
                            · {{ $sources }}
                            · #{{ $check->id }}
                        </p>
                        @if (! empty($freshness['goplus']))
                            <p class="mt-1 text-xs text-ink-muted">
                                {{ __('aml.freshness_goplus') }} · {{ $freshness['goplus'] }}
                                @if (! empty($freshness['trongrid']))
                                    · {{ __('aml.freshness_trongrid') }} · {{ $freshness['trongrid'] }}
                                @endif
                                @if (! empty($freshness['tx_window']))
                                    · {{ __('aml.freshness_window', ['n' => $freshness['tx_window']]) }}
                                @endif
                            </p>
                        @endif
                        <div class="mt-3 flex min-w-0 items-center gap-2">
                            <span class="truncate font-mono text-sm text-ink">{{ $check->subject }}</span>
                            <x-copy-button :text="$check->subject" class="sm:hidden" />
                        </div>
                        <div class="mt-3 flex flex-wrap gap-2">
                            @foreach ($pills as $pill)
                                <x-report-pill :tone="$pill['tone']">{{ $pill['label'] }}</x-report-pill>
                            @endforeach
                        </div>
                    </div>
                    <div>
                        <div class="text-[11px] uppercase tracking-[0.08em] text-ink-muted">{{ __('aml.flag_score') }}</div>
                        <div @class([
                            'mt-1 text-2xl font-semibold tabular-nums tracking-tight',
                            'text-amber-800' => $scoreTone === 'warning',
                            'text-emerald-800' => $scoreTone === 'success',
                        ])>{{ $check->risk_score ?? '—' }}</div>
                        @if ($check->isCompleted() && ! empty($scoreBreakdown['formula']))
                            <p class="mt-1 text-xs text-ink-muted">{{ $scoreBreakdown['formula'] }}</p>
                        @endif
                    </div>
                </div>

                @if ($check->isCompleted() && ! empty($conclusion))
                    <div class="space-y-3 border-t border-ink-line pt-5 text-sm leading-7 text-ink">
                        <div class="text-[11px] font-semibold uppercase tracking-[0.08em] text-ink-muted">{{ __('aml.conclusion_title') }}</div>
                        @foreach ($conclusion as $paragraph)
                            <p>{{ $paragraph }}</p>
                        @endforeach
                    </div>
                @endif

                @if ($check->isCompleted())
                    <div class="border-l-[3px] border-amber-600 bg-amber-50/80 px-4 py-3 text-sm leading-6 text-amber-950">
                        <div class="mb-1 font-semibold">{{ __('aml.reading_title') }}</div>
                        {{ $readingNote }}
                    </div>
                @endif
            </div>

            @if (! empty($delta))
                <x-report-section :title="__('aml.delta_title')">
                    <p class="mb-3 text-sm text-ink-muted">#{{ $delta['previous_id'] }}</p>
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="text-left text-xs text-ink-muted">
                                <th class="pb-2 pr-4 font-medium"></th>
                                <th class="pb-2 pr-4 font-medium">{{ __('aml.delta_from') }}</th>
                                <th class="pb-2 font-medium">{{ __('aml.delta_to') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr class="border-t border-ink-line">
                                <td class="py-2 pr-4 text-ink-muted">{{ __('aml.verdict') }}</td>
                                <td class="py-2 pr-4">{{ $delta['verdict']['from'] }}</td>
                                <td class="py-2">{{ $delta['verdict']['to'] }}</td>
                            </tr>
                            <tr class="border-t border-ink-line">
                                <td class="py-2 pr-4 text-ink-muted">{{ __('aml.score') }}</td>
                                <td class="py-2 pr-4 font-mono">{{ $delta['score']['from'] }}</td>
                                <td class="py-2 font-mono">{{ $delta['score']['to'] }}</td>
                            </tr>
                            <tr class="border-t border-ink-line">
                                <td class="py-2 pr-4 text-ink-muted">{{ __('aml.wallet_usd') }}</td>
                                <td class="py-2 pr-4 font-mono">{{ $delta['usd']['from'] }}</td>
                                <td class="py-2 font-mono">{{ $delta['usd']['to'] }}</td>
                            </tr>
                            <tr class="border-t border-ink-line">
                                <td class="py-2 pr-4 text-ink-muted">{{ __('aml.delta_inflows') }}</td>
                                <td class="py-2 pr-4 font-mono">{{ $delta['inflows']['from'] }}</td>
                                <td class="py-2 font-mono">{{ $delta['inflows']['to'] }}</td>
                            </tr>
                        </tbody>
                    </table>
                    @if ($delta['flags_added'] !== [])
                        <p class="mt-3 text-sm">{{ __('aml.delta_flags_added') }}: {{ implode(', ', $delta['flags_added']) }}</p>
                    @endif
                    @if ($delta['flags_removed'] !== [])
                        <p class="mt-1 text-sm">{{ __('aml.delta_flags_removed') }}: {{ implode(', ', $delta['flags_removed']) }}</p>
                    @endif
                </x-report-section>
            @endif

            @if ($isWalletReport)
                <div class="grid grid-cols-2 lg:grid-cols-4 gap-3">
                    <x-report-stat :label="__('aml.control_keys')" :tone="$isMultisig ? 'warning' : 'neutral'">
                        @if ($isMultisig)
                            {{ __('aml.multisig') }} {{ $onchain['control']['threshold'] ?? '' }}
                        @elseif ($hasOnchain)
                            {{ __('aml.single_key') }}
                        @else
                            —
                        @endif
                    </x-report-stat>
                    <x-report-stat :label="__('aml.native_balance')">
                        @if ($nativeRow)
                            {{ $nativeRow['amount'] }} {{ $nativeRow['symbol'] ?? '' }}
                        @else
                            —
                        @endif
                    </x-report-stat>
                    <x-report-stat :label="__('aml.wallet_usd')">{{ $usdSummary['formatted'] ?? '—' }}</x-report-stat>
                    <x-report-stat :label="__('aml.chain')">{{ $check->chainName() ?? '—' }}</x-report-stat>
                </div>
            @endif

            @if ($canOverrideVerdict)
                <x-report-section :title="__('aml.override_title')" :hint="__('aml.override_hint')">
                    <form id="override-form" method="POST" action="{{ route('checks.verdict', $check) }}" class="space-y-4">
                        @csrf
                        @method('PATCH')
                        <div class="grid gap-4 md:grid-cols-2">
                            <div>
                                <x-input-label for="verdict" :value="__('aml.verdict')" />
                                <select id="verdict" name="verdict" class="ui-select mt-1 block w-full">
                                    <option value="manual" @selected(old('verdict', $check->verdict?->value) === 'manual')>{{ __('aml.verdicts.manual') }}</option>
                                    <option value="review" @selected(old('verdict', $check->verdict?->value) === 'review')>{{ __('aml.verdicts.review') }}</option>
                                    <option value="block" @selected(old('verdict', $check->verdict?->value) === 'block')>{{ __('aml.verdicts.block') }}</option>
                                </select>
                                <p class="mt-1 text-xs text-ink-muted">{{ __('aml.override_cannot_clear') }}</p>
                            </div>
                            <div>
                                <x-input-label for="override_note" :value="__('aml.override_note')" />
                                <x-text-input id="override_note" name="note" type="text" class="mt-1 block w-full" :value="old('note', $overrideNote)" maxlength="500" />
                            </div>
                        </div>
                        <x-input-error :messages="$errors->get('verdict')" class="mt-1" />
                        <x-primary-button>{{ __('aml.override_save') }}</x-primary-button>
                    </form>
                </x-report-section>
            @endif

            <x-report-section :title="__('aml.object_title')">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="text-left text-xs text-ink-muted">
                            <th class="pb-2 pr-4 font-medium">{{ __('aml.field') }}</th>
                            <th class="pb-2 font-medium">{{ __('aml.value') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($objectRows as $i => $row)
                            <tr class="border-t border-ink-line {{ $i % 2 === 1 ? 'bg-ink-paper' : '' }}">
                                <td class="py-2 pr-4 text-ink-muted align-top whitespace-nowrap">{{ $row['label'] }}</td>
                                <td class="py-2 font-mono text-[13px] text-ink break-all">
                                    @if (! empty($row['href']))
                                        <a class="ui-link" href="{{ $row['href'] }}" target="_blank" rel="noopener noreferrer">{{ $row['value'] }}</a>
                                    @else
                                        {{ $row['value'] }}
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </x-report-section>

            @if ($check->isPending())
                <div class="ui-alert ui-alert-info">{{ __('aml.waiting_scan') }}</div>
            @endif

            @if ($check->error_message)
                <div class="border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-800">
                    <div class="font-medium">{{ __('aml.error') }}</div>
                    {{ $check->error_message }}
                </div>
            @endif

            @if ($check->isCompleted())
                <x-report-section :title="__('aml.why_title')">
                    @include('checks.partials.flag-table', ['rows' => $hotFlags])
                </x-report-section>
            @endif

            @if ($showOnchain)
                <x-report-section :title="__('aml.control')">
                    <p class="text-sm leading-6 text-ink">{{ $controlNarrative }}</p>
                    @if (! empty($signerRows))
                        <table class="mt-3 w-full text-sm">
                            <thead>
                                <tr class="text-left text-xs text-ink-muted">
                                    <th class="pb-2 pr-3 font-medium">{{ __('aml.signers') }}</th>
                                    <th class="pb-2 pr-3 font-medium">{{ __('aml.threshold') }}</th>
                                    <th class="pb-2 font-medium">{{ __('aml.role') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($signerRows as $row)
                                    @php
                                        $rowBg = match ($row['tone']) {
                                            'warning' => 'bg-amber-50',
                                            'info' => 'bg-sky-50',
                                            default => '',
                                        };
                                    @endphp
                                    <tr class="border-t border-ink-line {{ $rowBg }}">
                                        <td class="py-2 pr-3 font-mono text-[11px] break-all text-ink">{{ $row['address'] }}</td>
                                        <td class="py-2 pr-3">{{ $row['weight'] }}</td>
                                        <td class="py-2 text-ink">{{ $row['role'] }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    @endif
                </x-report-section>

                <x-report-section :title="__('aml.asset_narrative')" :hint="__('aml.onchain_source').': '.($onchain['source'] ?? '—')">
                    @if (! empty($assetNarrative))
                        <p class="text-sm leading-7 text-ink">{{ $assetNarrative }}</p>
                    @endif
                    @if (! empty($tokenPieSlices))
                        <h3 class="mt-5 text-sm font-semibold text-ink">{{ __('aml.token_pie_title') }}</h3>
                        <p class="mt-1 text-xs text-ink-muted">{{ __('aml.token_pie_hint') }}</p>
                        <div class="mt-3 flex flex-col sm:flex-row sm:items-center gap-6">
                            @if (! empty($tokenPieSvg))
                                <div class="mx-auto w-40 shrink-0 sm:mx-0">
                                    {!! $tokenPieSvg !!}
                                </div>
                            @endif
                            <div class="min-w-0 flex-1 space-y-3">
                                <div class="flex w-full overflow-hidden bg-ink-line" style="height: 12px;">
                                    @foreach ($tokenPieSlices as $slice)
                                        @if ((int) ($slice['pct'] ?? 0) > 0)
                                            <div style="height: 12px; width: {{ (int) $slice['pct'] }}%; background-color: {{ $slice['color'] ?? '#0C0C0D' }};"></div>
                                        @endif
                                    @endforeach
                                </div>
                                <div class="space-y-2">
                                    @foreach ($tokenPieSlices as $slice)
                                        <x-report-hbar
                                            :label="__('aml.pie_'.$slice['key'])"
                                            :value="$slice['value']"
                                            :max="$pieMax"
                                            :color="$slice['color'] ?? null"
                                            :hint="((int) ($slice['pct'] ?? 0)).'%'"
                                            :tone="match ($slice['key']) {
                                                'native', 'canonical' => 'success',
                                                'lookalike' => 'warning',
                                                'ignore' => 'neutral',
                                                default => 'danger',
                                            }"
                                        />
                                    @endforeach
                                </div>
                            </div>
                        </div>
                    @endif
                </x-report-section>

                <x-report-section :title="__('aml.balances')" :hint="__('aml.canonical_usdt_hint', ['contract' => $officialUsdt])">
                    @if (empty($balanceRows))
                        <p class="text-sm text-ink-muted">{{ __('aml.no_balances') }}</p>
                    @else
                        <div class="overflow-x-auto">
                            <table class="min-w-full text-sm">
                                <thead>
                                    <tr class="text-left text-xs text-ink-muted">
                                        <th class="pb-2 pr-4 font-medium">{{ __('aml.inflow_asset') }}</th>
                                        <th class="pb-2 pr-4 font-medium text-right">{{ __('aml.inflow_amount') }}</th>
                                        <th class="pb-2 pr-4 font-medium text-right">{{ __('aml.usd') }}</th>
                                        <th class="pb-2 pr-4 font-medium">{{ __('aml.col_contract') }}</th>
                                        <th class="pb-2 pr-4 font-medium">{{ __('aml.token_status') }}</th>
                                        <th class="pb-2 font-medium">{{ __('aml.comment') }}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($balanceRows as $row)
                                        @php
                                            $kind = $row['kind'] ?? '';
                                            $rowClass = match ($row['tone'] ?? $kind) {
                                                'success', 'native', 'canonical' => 'bg-emerald-50',
                                                'warning', 'lookalike' => 'bg-amber-50',
                                                'danger', 'noise' => 'bg-rose-50',
                                                default => '',
                                            };
                                        @endphp
                                        <tr class="border-t border-ink-line {{ $rowClass }}">
                                            <td class="py-2.5 pr-4 align-top font-medium text-ink">
                                                {{ $row['symbol'] }}
                                                <span class="font-normal text-ink-muted">{{ $row['name'] ?? '' }}</span>
                                            </td>
                                            <td class="py-2.5 pr-4 font-mono text-right align-top">{{ $row['amount'] }}</td>
                                            <td class="py-2.5 pr-4 font-mono text-right align-top">
                                                @if (isset($row['usd']) && $row['usd'] !== null)
                                                    ${{ number_format((float) $row['usd'], 2, '.', ' ') }}
                                                @else
                                                    —
                                                @endif
                                            </td>
                                            <td class="py-2.5 pr-4 font-mono text-[11px] text-ink-muted align-top break-all">
                                                {{ $row['contract'] ?? __('aml.native_balance') }}
                                            </td>
                                            <td class="py-2.5 pr-4 align-top">
                                                @if (! empty($row['overridable']) && ! empty($row['contract']))
                                                    <select name="tokens[{{ $row['contract'] }}]" form="override-form" class="ui-select block w-full text-sm">
                                                        <option value="lookalike" @selected(($row['kind'] ?? '') === 'lookalike')>{{ __('aml.token_kind_lookalike') }}</option>
                                                        <option value="noise" @selected(($row['kind'] ?? '') === 'noise')>{{ __('aml.token_kind_noise') }}</option>
                                                        <option value="ignore" @selected(($row['kind'] ?? '') === 'ignore')>{{ __('aml.token_kind_ignore') }}</option>
                                                    </select>
                                                @else
                                                    <span class="text-xs text-ink-muted">{{ __('aml.token_status_locked') }}</span>
                                                @endif
                                            </td>
                                            <td class="py-2.5 text-ink-muted align-top">{{ $row['comment'] }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </x-report-section>

                <div class="grid gap-4 md:grid-cols-2">
                    <x-report-card :title="__('aml.money_count_title')">
                        <p class="text-xl font-semibold text-ink">{{ __('aml.wallet_usd') }}: {{ $usdSummary['formatted'] ?? '—' }}</p>
                        <p class="mt-2">{{ __('aml.money_count_body', ['amount' => $usdSummary['formatted'] ?? '—']) }}</p>
                    </x-report-card>
                    <x-report-card :title="__('aml.money_junk_title')">
                        {{ __('aml.money_junk_body') }}
                    </x-report-card>
                </div>

                <x-report-section :title="__('aml.wallet_graph')" :hint="__('aml.wallet_graph_hint')">
                    @include('checks.partials.inflow-graph')
                </x-report-section>

                <x-report-section :title="__('aml.inflows')" :hint="__('aml.inflow_hint')">
                    @if (! empty($inflowBars) && collect($inflowBars)->sum('value') > 0)
                        <h3 class="text-sm font-semibold text-ink">{{ __('aml.inflow_bar_title') }}</h3>
                        <div class="mt-3 space-y-2">
                            @foreach ($inflowBars as $bar)
                                <x-report-hbar
                                    :label="__('aml.inflow_bar_'.$bar['key'])"
                                    :value="$bar['value']"
                                    :max="$inflowMax"
                                    :tone="match ($bar['key']) {
                                        'trx', 'stable' => 'success',
                                        'dust' => 'warning',
                                        default => 'danger',
                                    }"
                                />
                            @endforeach
                        </div>
                    @endif
                    @if (empty($inflowRows))
                        <p class="text-sm text-ink-muted">{{ __('aml.no_inflows') }}</p>
                    @else
                        @include('checks.partials.flow-table', ['rows' => $inflowRows, 'peerKey' => 'from'])
                    @endif
                </x-report-section>

                <x-report-section :title="__('aml.outflows')" :hint="__('aml.outflow_hint')">
                    @if (empty($outflowRows))
                        <p class="text-sm text-ink-muted">{{ __('aml.no_outflows') }}</p>
                    @else
                        @include('checks.partials.flow-table', ['rows' => $outflowRows, 'peerKey' => 'to'])
                    @endif
                </x-report-section>
            @elseif ($isWalletReport && $check->isCompleted() && ! empty($onchain['skipped']))
                <x-report-section :title="__('aml.balances')">
                    <p class="text-sm text-ink-muted">{{ __('aml.onchain_unavailable') }}</p>
                </x-report-section>
            @elseif ($isWalletReport && $check->isCompleted() && ! empty($onchain['error']))
                <x-report-section :title="__('aml.balances')">
                    <p class="text-sm text-rose-700">{{ __('aml.onchain_error') }}: {{ $onchain['error'] }}</p>
                    <button
                        type="button"
                        class="mt-3 inline-flex items-center px-4 py-2 bg-ink text-sm font-medium text-white hover:bg-ink-soft"
                        @click="retry(@js(route('checks.enrich', $check)), @js(__('aml.processing_onchain')))"
                    >{{ __('aml.processing_retry') }}</button>
                </x-report-section>
            @endif

            @if ($check->isCompleted() && $showRadar && (! empty($hotRadarAxes) || $quietRadarCount > 0))
                <x-report-section :title="__('aml.radar_title')" :hint="__('aml.radar_hint')">
                    <div class="space-y-2">
                        @foreach ($hotRadarAxes as $axis)
                            <x-report-hbar
                                :label="__('aml.radar.'.$axis['key'])"
                                :value="$axis['value']"
                                :max="100"
                                :tone="$axis['value'] > 0 ? 'danger' : 'success'"
                            />
                        @endforeach
                    </div>
                    @if ($quietRadarCount > 0)
                        <p class="mt-3 text-xs text-ink-muted">{{ __('aml.radar_quiet', ['count' => $quietRadarCount]) }}</p>
                    @endif
                </x-report-section>
            @endif

            @if ($check->isCompleted() && $quietFlags !== [])
                <details class="border-t border-ink-line pt-4">
                    <summary class="cursor-pointer text-sm font-medium text-ink">{{ __('aml.flags_quiet') }} ({{ count($quietFlags) }})</summary>
                    <div class="mt-3">
                        @include('checks.partials.flag-table', ['rows' => $quietFlags])
                    </div>
                </details>
            @endif

            @if ($check->isCompleted() && ! empty($scoreBreakdown['lines']))
                <details class="border-t border-ink-line pt-4">
                    <summary class="cursor-pointer text-sm font-medium text-ink">{{ __('aml.score_details') }}</summary>
                    <p class="mt-3 text-sm leading-6 text-ink">{{ __('aml.score_how') }}</p>
                    <div class="mt-4 overflow-x-auto">
                        <table class="min-w-full text-sm">
                            <thead>
                                <tr class="text-left text-xs text-ink-muted">
                                    <th class="pb-2 pr-4 font-medium">{{ __('aml.field') }}</th>
                                    <th class="pb-2 pr-4 font-medium">{{ __('aml.score_rule') }}</th>
                                    <th class="pb-2 font-medium text-right">{{ __('aml.score_points') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($scoreBreakdown['lines'] as $i => $line)
                                    <tr class="border-t border-ink-line {{ ($line['severity'] ?? '') === 'block' ? 'bg-rose-50' : ($i % 2 === 1 ? 'bg-ink-paper' : '') }}">
                                        <td class="py-2 pr-4 text-ink">{{ $line['label'] }}</td>
                                        <td class="py-2 pr-4 text-ink-muted">{{ $line['rule'] }}</td>
                                        <td class="py-2 font-mono text-right">{{ ((int) $line['points']) > 0 ? '+' : '' }}{{ (int) $line['points'] }}</td>
                                    </tr>
                                @endforeach
                                <tr class="border-t border-ink-line bg-ink-paper font-semibold">
                                    <td class="py-2 pr-4">{{ __('aml.score_total') }}</td>
                                    <td class="py-2 pr-4 text-ink-muted">{{ $scoreBreakdown['formula'] }}</td>
                                    <td class="py-2 font-mono text-right">{{ (int) $scoreBreakdown['total'] }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </details>
            @endif

            @if (($activityLogs ?? null) && $activityLogs->isNotEmpty())
                <x-report-section :title="__('aml.activity')">
                    <div class="overflow-x-auto">
                        <table class="ui-table">
                            <thead>
                                <tr>
                                    <th>{{ __('aml.created') }}</th>
                                    <th>{{ __('aml.operator') }}</th>
                                    <th>{{ __('aml.type') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($activityLogs as $log)
                                    <tr>
                                        <td class="text-ink-muted whitespace-nowrap">{{ $log->created_at->format('d.m.Y H:i') }}</td>
                                        <td>{{ $log->user?->name ?? '—' }}</td>
                                        <td>
                                            {{ $log->label() }}
                                            @if ($log->note())
                                                <div class="text-xs text-ink-muted">{{ $log->note() }}</div>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </x-report-section>
            @endif

            @if ($check->raw_response)
                <details class="border-t border-ink-line pt-4">
                    <summary class="cursor-pointer text-sm font-medium text-ink">{{ __('aml.raw_response') }}</summary>
                    <pre class="mt-3 text-xs overflow-x-auto bg-ink-paper p-3 border border-ink-line">{{ json_encode($check->raw_response, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre>
                </details>
            @endif
        </div>
    </div>
</x-app-layout>
