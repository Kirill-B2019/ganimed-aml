{{-- | KB @CerberRus00 - Nexus Invest Team --}}
<x-app-layout :title="$reportTitle ?? __('aml.report_title')">
    @php
        $isMultisig = $showOnchain && ($onchain['control']['type'] ?? '') === 'multisig';
        $verdictTone = match ($check->verdict?->value) {
            'block' => 'danger',
            'review' => 'warning',
            default => 'success',
        };
        $scoreTone = ((int) ($check->risk_score ?? 0)) > 0 ? 'warning' : 'success';
        $officialUsdt = 'TR7NHqjeKQxGTCi8q8ZY4pL8otSzgjLj6t';
        $pieMax = max(1, collect($tokenPieSlices ?? [])->sum('value'));
        $inflowMax = max(1, collect($inflowBars ?? [])->max('value') ?? 1);
    @endphp

    <div class="sticky top-14 z-20 border-b border-slate-200 bg-white">
        <div class="page flex items-center justify-between gap-3 py-2.5">
            <div class="flex min-w-0 items-center gap-3 text-sm">
                <a href="{{ route('checks.index') }}" class="shrink-0 text-slate-500 hover:text-slate-900">{{ __('aml.back_history') }}</a>
                <span class="hidden text-slate-300 sm:inline">/</span>
                <span class="hidden truncate font-mono text-slate-700 sm:inline">{{ $check->subject }}</span>
                <x-copy-button :text="$check->subject" class="hidden shrink-0 sm:inline" />
            </div>
            <div class="flex shrink-0 items-center gap-2">
                @if ($check->isCompleted())
                    <x-verdict-badge :verdict="$check->verdict" />
                    <x-secondary-button :href="route('checks.pdf', $check)">{{ __('aml.download_pdf') }}</x-secondary-button>
                @endif
            </div>
        </div>
    </div>

    <div class="py-8" @if ($check->isPending()) x-data="scanPoll({{ $check->id }})" @endif>
        <div class="page space-y-8">
            @if (session('status'))
                <div class="border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">{{ session('status') }}</div>
            @endif
            @if (session('error'))
                <div class="border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-800">{{ session('error') }}</div>
            @endif

            <div class="space-y-3">
                <div>
                    <h1 class="text-2xl font-semibold text-slate-900">{{ $reportTitle ?? __('aml.report_title') }}</h1>
                    <p class="mt-1 text-sm text-slate-500">
                        {{ $check->type->label() }}
                        @if ($check->chainName())
                            · {{ $check->chainName() }}
                        @endif
                        · {{ $sources }}
                        @if ($check->isCompleted())
                            · {{ $generatedAt }}
                        @endif
                        · #{{ $check->id }}
                    </p>
                    <div class="mt-2 flex items-center gap-2 sm:hidden">
                        <span class="truncate font-mono text-sm text-slate-700">{{ $check->subject }}</span>
                        <x-copy-button :text="$check->subject" />
                    </div>
                </div>
                <div class="flex flex-wrap gap-2">
                    @foreach ($pills as $pill)
                        <x-report-pill :tone="$pill['tone']">{{ $pill['label'] }}</x-report-pill>
                    @endforeach
                </div>
            </div>

            <div class="grid grid-cols-2 lg:grid-cols-4 gap-3">
                <x-report-stat :label="__('aml.verdict')" :tone="$check->isCompleted() ? $verdictTone : 'neutral'">
                    {{ $check->verdict?->label() ?? '—' }}
                </x-report-stat>
                <x-report-stat :label="__('aml.flag_score')" :tone="$check->isCompleted() ? $scoreTone : 'neutral'">
                    {{ $check->risk_score ?? '—' }}
                </x-report-stat>
                @if ($isWalletReport)
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
                @else
                    <x-report-stat :label="__('aml.type')">{{ $check->type->label() }}</x-report-stat>
                    <x-report-stat :label="__('aml.chain')">{{ $check->chainName() ?? '—' }}</x-report-stat>
                @endif
            </div>

            @if ($check->isCompleted())
                <div class="border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-950 leading-6">
                    <div class="font-semibold mb-1">{{ __('aml.reading_title') }}</div>
                    {{ $readingNote }}
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
                                <select id="verdict" name="verdict" class="mt-1 block w-full border-slate-300 bg-white text-slate-900 focus:border-indigo-600 focus:ring-indigo-600">
                                    <option value="review" @selected(old('verdict', $check->verdict?->value) === 'review')>{{ __('aml.verdicts.review') }}</option>
                                    <option value="block" @selected(old('verdict', $check->verdict?->value) === 'block')>{{ __('aml.verdicts.block') }}</option>
                                </select>
                                <p class="mt-1 text-xs text-slate-500">{{ __('aml.override_cannot_clear') }}</p>
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
                        <tr class="text-left text-xs text-slate-500">
                            <th class="pb-2 pr-4 font-medium">{{ __('aml.field') }}</th>
                            <th class="pb-2 font-medium">{{ __('aml.value') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($objectRows as $i => $row)
                            <tr class="border-t border-slate-100 {{ $i % 2 === 1 ? 'bg-slate-50' : '' }}">
                                <td class="py-2 pr-4 text-slate-500 align-top whitespace-nowrap">{{ $row['label'] }}</td>
                                <td class="py-2 font-mono text-[13px] text-slate-900 break-all">{{ $row['value'] }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </x-report-section>

            @if ($check->isPending())
                <div class="border border-indigo-200 bg-indigo-50 px-4 py-3 text-sm text-indigo-800">{{ __('aml.waiting_scan') }}</div>
            @endif

            @if ($check->error_message)
                <div class="border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-800">
                    <div class="font-medium">{{ __('aml.error') }}</div>
                    {{ $check->error_message }}
                </div>
            @endif

            @if ($check->isCompleted() && $showRadar && ! empty($radarAxes))
                <x-report-section :title="__('aml.radar_title')" :hint="__('aml.radar_hint')">
                    <div class="space-y-2">
                        @foreach ($radarAxes as $axis)
                            <x-report-hbar
                                :label="__('aml.radar.'.$axis['key'])"
                                :value="$axis['value']"
                                :max="100"
                                :tone="$axis['value'] > 0 ? 'danger' : 'success'"
                            />
                        @endforeach
                    </div>
                </x-report-section>
            @endif

            @if ($check->isCompleted() && ! empty($flagRows))
                <x-report-section :title="__('aml.provider_decode')">
                    <div class="overflow-x-auto">
                        <table class="min-w-full text-sm">
                            <thead>
                                <tr class="text-left text-xs text-slate-500">
                                    <th class="pb-2 pr-4 font-medium">{{ __('aml.api_field') }}</th>
                                    <th class="pb-2 pr-4 font-medium">{{ __('aml.flag_value') }}</th>
                                    <th class="pb-2 pr-4 font-medium">{{ __('aml.score_points') }}</th>
                                    <th class="pb-2 font-medium">{{ __('aml.meaning') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($flagRows as $i => $row)
                                    @php
                                        $hot = ! in_array((string) $row['value'], ['0', '—', '', '[]'], true);
                                    @endphp
                                    <tr class="border-t border-slate-100 {{ $hot ? 'bg-amber-50' : ($i % 2 === 1 ? 'bg-slate-50' : '') }}">
                                        <td class="py-2 pr-4 font-mono text-xs text-slate-600">{{ $row['field'] }}</td>
                                        <td @class(['py-2 pr-4 font-mono', 'font-semibold text-amber-900' => $hot])>{{ $row['value'] }}</td>
                                        <td class="py-2 pr-4 font-mono text-slate-700">
                                            @if ((int) ($row['points'] ?? 0) === 100)
                                                {{ __('aml.verdicts.block') }} (100)
                                            @elseif ((int) ($row['points'] ?? 0) > 0)
                                                +{{ (int) $row['points'] }}
                                            @else
                                                0
                                            @endif
                                        </td>
                                        <td class="py-2 text-slate-700">{{ $row['meaning'] }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </x-report-section>
            @endif

            @if ($check->isCompleted() && ! empty($scoreBreakdown))
                <x-report-section :title="__('aml.score_title')" :hint="$scoreBreakdown['formula']">
                    <p class="text-sm leading-6 text-slate-700">{{ __('aml.score_how') }}</p>
                    @if (! empty($scoreBreakdown['lines']))
                        <div class="mt-4 overflow-x-auto">
                            <table class="min-w-full text-sm">
                                <thead>
                                    <tr class="text-left text-xs text-slate-500">
                                        <th class="pb-2 pr-4 font-medium">{{ __('aml.field') }}</th>
                                        <th class="pb-2 pr-4 font-medium">{{ __('aml.score_rule') }}</th>
                                        <th class="pb-2 font-medium text-right">{{ __('aml.score_points') }}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($scoreBreakdown['lines'] as $i => $line)
                                        <tr class="border-t border-slate-100 {{ ($line['severity'] ?? '') === 'block' ? 'bg-rose-50' : ($i % 2 === 1 ? 'bg-slate-50' : '') }}">
                                            <td class="py-2 pr-4 text-slate-800">{{ $line['label'] }}</td>
                                            <td class="py-2 pr-4 text-slate-600">{{ $line['rule'] }}</td>
                                            <td class="py-2 font-mono text-right">{{ ((int) $line['points']) > 0 ? '+' : '' }}{{ (int) $line['points'] }}</td>
                                        </tr>
                                    @endforeach
                                    <tr class="border-t border-slate-200 bg-slate-50 font-semibold">
                                        <td class="py-2 pr-4">{{ __('aml.score_total') }}</td>
                                        <td class="py-2 pr-4 text-slate-600">{{ $scoreBreakdown['formula'] }}</td>
                                        <td class="py-2 font-mono text-right">{{ (int) $scoreBreakdown['total'] }}</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    @endif
                </x-report-section>
            @endif

            @if ($showOnchain)
                <x-report-section :title="__('aml.control')">
                    <p class="text-sm leading-6 text-slate-800">{{ $controlNarrative }}</p>
                    @if (! empty($signerRows))
                        <table class="mt-3 w-full text-sm">
                            <thead>
                                <tr class="text-left text-xs text-slate-500">
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
                                    <tr class="border-t border-slate-100 {{ $rowBg }}">
                                        <td class="py-2 pr-3 font-mono text-[11px] break-all text-slate-700">{{ $row['address'] }}</td>
                                        <td class="py-2 pr-3">{{ $row['weight'] }}</td>
                                        <td class="py-2 text-slate-700">{{ $row['role'] }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    @endif
                </x-report-section>

                <x-report-section :title="__('aml.asset_narrative')" :hint="__('aml.onchain_source').': '.($onchain['source'] ?? '—')">
                    @if (! empty($assetNarrative))
                        <p class="text-sm leading-7 text-slate-800">{{ $assetNarrative }}</p>
                    @endif
                    @if (! empty($tokenPieSlices))
                        <h3 class="mt-5 text-sm font-semibold text-slate-900">{{ __('aml.token_pie_title') }}</h3>
                        <p class="mt-1 text-xs text-slate-500">{{ __('aml.token_pie_hint') }}</p>
                        <div class="mt-3 flex flex-col sm:flex-row sm:items-center gap-6">
                            @if (! empty($tokenPieSvg))
                                <div class="mx-auto w-40 shrink-0 sm:mx-0">
                                    {!! $tokenPieSvg !!}
                                </div>
                            @endif
                            <div class="min-w-0 flex-1 space-y-3">
                                <div class="flex w-full overflow-hidden" style="height: 16px; background: #e2e8f0;">
                                    @foreach ($tokenPieSlices as $slice)
                                        @if ((int) ($slice['pct'] ?? 0) > 0)
                                            <div style="height: 16px; width: {{ (int) $slice['pct'] }}%; background-color: {{ $slice['color'] ?? '#64748b' }};"></div>
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
                        <p class="text-sm text-slate-500">{{ __('aml.no_balances') }}</p>
                    @else
                        <div class="overflow-x-auto">
                            <table class="min-w-full text-sm">
                                <thead>
                                    <tr class="text-left text-xs text-slate-500">
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
                                        <tr class="border-t border-slate-100 {{ $rowClass }}">
                                            <td class="py-2.5 pr-4 align-top font-medium text-slate-900">
                                                {{ $row['symbol'] }}
                                                <span class="font-normal text-slate-500">{{ $row['name'] ?? '' }}</span>
                                            </td>
                                            <td class="py-2.5 pr-4 font-mono text-right align-top">{{ $row['amount'] }}</td>
                                            <td class="py-2.5 pr-4 font-mono text-right align-top">
                                                @if (isset($row['usd']) && $row['usd'] !== null)
                                                    ${{ number_format((float) $row['usd'], 2, '.', ' ') }}
                                                @else
                                                    —
                                                @endif
                                            </td>
                                            <td class="py-2.5 pr-4 font-mono text-[11px] text-slate-500 align-top break-all">
                                                {{ $row['contract'] ?? __('aml.native_balance') }}
                                            </td>
                                            <td class="py-2.5 pr-4 align-top">
                                                @if (! empty($row['overridable']) && ! empty($row['contract']))
                                                    <select name="tokens[{{ $row['contract'] }}]" form="override-form" class="block w-full border-slate-300 bg-white text-sm text-slate-900 focus:border-indigo-600 focus:ring-indigo-600">
                                                        <option value="lookalike" @selected(($row['kind'] ?? '') === 'lookalike')>{{ __('aml.token_kind_lookalike') }}</option>
                                                        <option value="noise" @selected(($row['kind'] ?? '') === 'noise')>{{ __('aml.token_kind_noise') }}</option>
                                                        <option value="ignore" @selected(($row['kind'] ?? '') === 'ignore')>{{ __('aml.token_kind_ignore') }}</option>
                                                    </select>
                                                @else
                                                    <span class="text-xs text-slate-500">{{ __('aml.token_status_locked') }}</span>
                                                @endif
                                            </td>
                                            <td class="py-2.5 text-slate-600 align-top">{{ $row['comment'] }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </x-report-section>

                <div class="grid gap-4 md:grid-cols-2">
                    <x-report-card :title="__('aml.money_count_title')">
                        <p class="text-xl font-semibold text-slate-900">{{ __('aml.wallet_usd') }}: {{ $usdSummary['formatted'] ?? '—' }}</p>
                        <p class="mt-2">{{ __('aml.money_count_body', ['amount' => $usdSummary['formatted'] ?? '—']) }}</p>
                    </x-report-card>
                    <x-report-card :title="__('aml.money_junk_title')">
                        {{ __('aml.money_junk_body') }}
                    </x-report-card>
                </div>

                <x-report-section :title="__('aml.inflows')" :hint="__('aml.inflow_hint')">
                    @if (! empty($inflowBars) && collect($inflowBars)->sum('value') > 0)
                        <h3 class="text-sm font-semibold text-slate-900">{{ __('aml.inflow_bar_title') }}</h3>
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
                        <p class="text-sm text-slate-500">{{ __('aml.no_inflows') }}</p>
                    @else
                        <div class="mt-4 overflow-x-auto">
                            <table class="min-w-full text-sm">
                                <thead>
                                    <tr class="text-left text-xs text-slate-500">
                                        <th class="pb-2 pr-3 font-medium">{{ __('aml.inflow_from') }}</th>
                                        <th class="pb-2 pr-3 font-medium">{{ __('aml.inflow_asset') }}</th>
                                        <th class="pb-2 pr-3 font-medium text-right">{{ __('aml.inflow_amount') }}</th>
                                        <th class="pb-2 pr-3 font-medium">{{ __('aml.inflow_count') }}</th>
                                        <th class="pb-2 font-medium">{{ __('aml.comment') }}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($inflowRows as $row)
                                        @php
                                            $rowBg = match ($row['tone'] ?? '') {
                                                'success' => 'bg-emerald-50',
                                                'warning' => 'bg-amber-50',
                                                'danger' => 'bg-rose-50',
                                                default => '',
                                            };
                                        @endphp
                                        <tr class="border-t border-slate-100 align-top {{ $rowBg }}">
                                            <td class="py-2 pr-3 font-mono text-[11px] break-all text-slate-600">{{ $row['from'] }}</td>
                                            <td class="py-2 pr-3">{{ $row['symbol'] }}</td>
                                            <td class="py-2 pr-3 font-mono text-right">{{ $row['amount'] }}</td>
                                            <td class="py-2 pr-3">{{ $row['tx_count'] ?? '' }}</td>
                                            <td class="py-2 text-slate-600">{{ $row['comment'] }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </x-report-section>
            @elseif ($isWalletReport && $check->isCompleted() && ! empty($onchain['skipped']))
                <x-report-section :title="__('aml.balances')">
                    <p class="text-sm text-slate-500">{{ __('aml.onchain_unavailable') }}</p>
                </x-report-section>
            @elseif ($isWalletReport && $check->isCompleted() && ! empty($onchain['error']))
                <x-report-section :title="__('aml.balances')">
                    <p class="text-sm text-rose-700">{{ __('aml.onchain_error') }}: {{ $onchain['error'] }}</p>
                </x-report-section>
            @endif

            @if ($check->isCompleted() && ! empty($conclusion))
                <div class="border-t border-slate-200 pt-6 space-y-3">
                    <h2 class="text-lg font-semibold text-slate-900">{{ __('aml.conclusion_title') }}</h2>
                    <div class="space-y-3 text-sm leading-7 text-slate-800">
                        @foreach ($conclusion as $paragraph)
                            <p>{{ $paragraph }}</p>
                        @endforeach
                    </div>
                </div>
            @endif

            @if ($check->raw_response)
                <details class="border-t border-slate-200 pt-4">
                    <summary class="cursor-pointer text-sm font-medium text-slate-900">{{ __('aml.raw_response') }}</summary>
                    <pre class="mt-3 text-xs overflow-x-auto bg-slate-50 p-3 border border-slate-200">{{ json_encode($check->raw_response, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre>
                </details>
            @endif
        </div>
    </div>
</x-app-layout>
