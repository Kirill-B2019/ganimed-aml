<?php

// | KB @CerberRus00 - Nexus Invest Team
namespace App\Services\Reports;

use App\Enums\CheckType;
use App\Enums\CheckVerdict;
use App\Models\Check;
use App\Models\User;
use App\Support\MskTime;
use App\Support\TronAddress;
use App\Services\Onchain\AssetNarrativeService;
use App\Services\Onchain\TokenCompositionChart;
use App\Services\Onchain\WalletGraphChart;
use App\Services\Onchain\WalletUsdValuationService;
use App\Services\Risk\RiskRadarService;
use App\Services\Risk\RiskScoringService;

class CheckReportPresenter
{
    /** @var list<string> */
    private const ADDRESS_FIELDS = [
        'sanctioned',
        'money_laundering',
        'mixer',
        'cybercrime',
        'financial_crime',
        'darkweb_transactions',
        'phishing_activities',
        'stealing_attack',
        'blackmail_activities',
        'fake_kyc',
        'blacklist_doubt',
        'honeypot_related_address',
        'fake_token',
        'fake_standard_interface',
        'gas_abuse',
        'malicious_mining_activities',
        'number_of_malicious_contracts_created',
        'contract_address',
        'reinit',
        'data_source',
    ];

    public function __construct(
        private RiskRadarService $radar,
        private AssetNarrativeService $narrative,
        private TokenCompositionChart $pie,
        private WalletUsdValuationService $usd,
        private RiskScoringService $scoring,
        private WalletGraphChart $graphChart,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function data(Check $check, bool $pdf = false, bool $compact = false): array
    {
        $onchain = is_array($check->enrichment) ? $check->enrichment : [];
        $hasOnchain = $onchain !== [] && empty($onchain['skipped']) && empty($onchain['error']);
        $isWalletReport = in_array($check->type, [CheckType::Address, CheckType::Scan], true);
        $usdSummary = $isWalletReport ? $this->usd->summarize($check) : null;
        $radarAxes = $isWalletReport ? $this->radar->axes($check) : [];
        $tokenPieSlices = $isWalletReport ? $this->withShares($this->pie->slices($check)) : [];
        $flagRows = $this->flagRows($check);
        [$hotFlags, $quietFlags] = $this->partitionFlags($flagRows);
        [$hotRadar, $quietRadarCount] = $this->partitionRadar($radarAxes);

        $balanceRows = $isWalletReport && $hasOnchain ? $this->balanceRows($check, $onchain, $usdSummary) : [];
        $inflowRows = $isWalletReport && $hasOnchain ? $this->inflowRows($check, $onchain) : [];
        $outflowRows = $isWalletReport && $hasOnchain ? $this->outflowRows($check, $onchain) : [];
        $walletGraph = $isWalletReport && $hasOnchain && is_array($onchain['graph'] ?? null)
            ? $onchain['graph']
            : [];
        $walletGraphPeers = $walletGraph !== [] ? $this->graphChart->peers($walletGraph) : [];
        $nativeRow = collect($balanceRows)->first(fn ($row) => ($row['kind'] ?? '') === 'native');
        $previous = $check->previousCheck;

        return [
            'check' => $check,
            'compact' => $compact,
            'generatedAt' => MskTime::stamp(now()),
            'footer' => (string) config('report.footer'),
            'brand' => 'GANIMED AML',
            'logoMark' => public_path('images/logo-gnd-mark.png'),
            'reportTitle' => $this->reportTitle($check),
            'sources' => $isWalletReport ? 'GoPlus · TronGrid' : 'GoPlus',
            'isWalletReport' => $isWalletReport,
            'showRadar' => $isWalletReport,
            'showOnchain' => $isWalletReport && $hasOnchain,
            'radarAxes' => $radarAxes,
            'hotRadarAxes' => $hotRadar,
            'quietRadarCount' => $quietRadarCount,
            'assetNarrative' => $isWalletReport ? $this->narrative->describe($check) : '',
            'tokenPieSlices' => $tokenPieSlices,
            'tokenPieSvg' => $tokenPieSlices !== [] ? $this->pie->svg($tokenPieSlices, 180) : '',
            'onchain' => $onchain,
            'hasOnchain' => $hasOnchain,
            'usdSummary' => $usdSummary,
            'nativeRow' => $nativeRow,
            'pills' => $this->pills($check, $isWalletReport && $hasOnchain, $onchain),
            'readingNote' => $this->readingNote($check, $isWalletReport && $hasOnchain, $onchain),
            'flagRows' => $flagRows,
            'hotFlags' => $hotFlags,
            'quietFlags' => $quietFlags,
            'scoreBreakdown' => $check->isCompleted() ? $this->scoring->breakdown($check) : null,
            'canOverrideVerdict' => $check->canOverrideVerdict(),
            'overrideNote' => is_string($check->overridePayload()['note'] ?? null) ? $check->overridePayload()['note'] : '',
            'objectRows' => $this->objectRows($check, $isWalletReport && $hasOnchain, $onchain, $usdSummary),
            'balanceRows' => $balanceRows,
            'inflowRows' => $inflowRows,
            'outflowRows' => $outflowRows,
            'inflowBars' => $this->withBarPercents($this->inflowBars($inflowRows)),
            'walletGraph' => $walletGraph,
            'walletGraphSvg' => $walletGraph !== [] ? $this->graphChart->svg($walletGraph, forPdf: $pdf) : '',
            'walletGraphPeers' => $walletGraphPeers,
            'walletGraphLegend' => $walletGraphPeers !== [] ? $this->graphChart->legend($walletGraphPeers) : [],
            'walletGraphPending' => (bool) ($walletGraph['pending'] ?? false),
            'signerRows' => $isWalletReport && $hasOnchain ? $this->signerRows($check, $onchain) : [],
            'controlNarrative' => $isWalletReport && $hasOnchain ? $this->controlNarrative($check, $onchain) : '',
            'conclusion' => $this->conclusion($check, $isWalletReport && $hasOnchain, $onchain, $usdSummary),
            'freshness' => $this->freshness($check, $onchain, $isWalletReport),
            'delta' => $previous ? $this->delta($previous, $check, $usdSummary, $inflowRows) : null,
        ];
    }

    private function reportTitle(Check $check): string
    {
        return match ($check->type) {
            CheckType::Token => __('aml.report_title_token'),
            CheckType::Phishing, CheckType::Dapp => __('aml.report_title_url'),
            CheckType::Scan => __('aml.report_title_scan'),
            default => __('aml.report_title_address'),
        };
    }

    /**
     * @param  array<string, mixed>  $onchain
     * @return list<array{label: string, tone: string}>
     */
    private function pills(Check $check, bool $hasOnchain, array $onchain): array
    {
        $goplus = $this->goplusVerdict($check);
        $pills = [[
            'label' => 'GoPlus: '.$goplus->label(),
            'tone' => match ($goplus) {
                CheckVerdict::Block => 'danger',
                CheckVerdict::Review => 'warning',
                default => 'success',
            },
        ]];

        if ($check->verdict === CheckVerdict::Review && $goplus === CheckVerdict::Clear) {
            $pills[] = ['label' => __('aml.pill_file_review'), 'tone' => 'warning'];
        }

        if ($hasOnchain) {
            $needsReview = $this->narrative->needsReview($onchain, $check);
            $pills[] = [
                'label' => $needsReview ? __('aml.pill_onchain_review') : __('aml.pill_onchain_ok'),
                'tone' => $needsReview ? 'warning' : 'success',
            ];
        }

        if ($check->verdictIsLocked()) {
            $pills[] = [
                'label' => $check->verdict === CheckVerdict::Manual
                    ? __('aml.verdicts.manual')
                    : __('aml.pill_analyst'),
                'tone' => $check->verdict?->isClearLike() ? 'success' : 'warning',
            ];
        }

        $raw = is_array($check->raw_response) ? $check->raw_response : [];
        if (($raw['scan_mode'] ?? null) === 'address_security_fallback') {
            $pills[] = ['label' => __('aml.pill_scan_fallback'), 'tone' => 'neutral'];
        }

        if (
            in_array($check->type, [CheckType::Address, CheckType::Scan], true)
            && (($raw['contract_address'] ?? '0') === '0' || ($raw['contract_address'] ?? null) === 0)
        ) {
            $pills[] = ['label' => __('aml.pill_eoa'), 'tone' => 'neutral'];
        }

        return $pills;
    }

    /**
     * @param  array<string, mixed>  $onchain
     */
    private function readingNote(Check $check, bool $hasOnchain, array $onchain): string
    {
        if ($check->type === CheckType::Token) {
            return __('aml.reading_token');
        }
        if (in_array($check->type, [CheckType::Phishing, CheckType::Dapp], true)) {
            return __('aml.reading_url');
        }

        $raw = is_array($check->raw_response) ? $check->raw_response : [];
        if ($check->verdict === CheckVerdict::Manual) {
            return __('aml.reading_manual');
        }
        if ($check->verdict === CheckVerdict::Block) {
            return __('aml.reading_block');
        }
        if ($hasOnchain && $this->narrative->needsReview($onchain, $check)) {
            return __('aml.reading_clear_with_noise');
        }
        if (($raw['scan_mode'] ?? null) === 'address_security_fallback') {
            return __('aml.reading_scan_tron');
        }

        return __('aml.reading_clear');
    }

    /**
     * @return list<array{field: string, value: string, meaning: string, points: int}>
     */
    private function flagRows(Check $check): array
    {
        $raw = is_array($check->raw_response) ? $check->raw_response : [];
        $useAddressDecode = $check->type === CheckType::Address
            || ($raw['scan_mode'] ?? null) === 'address_security_fallback';
        $pointsByKey = $this->flagPoints($check);

        if (! $useAddressDecode) {
            $rows = [];
            foreach ($check->flags ?? [] as $flag) {
                $key = (string) ($flag['key'] ?? '');
                $rows[] = [
                    'field' => $key,
                    'value' => is_array($flag['value'] ?? null) ? json_encode($flag['value']) : (string) ($flag['value'] ?? ''),
                    'meaning' => __('aml.flags.'.$key),
                    'points' => $pointsByKey[$key] ?? 0,
                ];
            }

            return $rows;
        }

        $rows = [];
        foreach (self::ADDRESS_FIELDS as $field) {
            $value = $raw[$field] ?? '';
            if ($value === '' || $value === null) {
                $value = '—';
            }
            $rows[] = [
                'field' => $field,
                'value' => is_scalar($value) ? (string) $value : json_encode($value),
                'meaning' => __('aml.flag_help.'.$field),
                'points' => $pointsByKey[$field] ?? 0,
            ];
        }

        foreach ($check->flags ?? [] as $flag) {
            $key = (string) ($flag['key'] ?? '');
            if ($key === '' || in_array($key, self::ADDRESS_FIELDS, true)) {
                continue;
            }
            $rows[] = [
                'field' => $key,
                'value' => is_array($flag['value'] ?? null) ? json_encode($flag['value']) : (string) ($flag['value'] ?? ''),
                'meaning' => __('aml.flags.'.$key),
                'points' => $pointsByKey[$key] ?? 0,
            ];
        }

        return $rows;
    }

    /**
     * @param  list<array{field: string, value: string, meaning: string, points: int}>  $rows
     * @return array{0: list<array{field: string, value: string, meaning: string, points: int}>, 1: list<array{field: string, value: string, meaning: string, points: int}>}
     */
    private function partitionFlags(array $rows): array
    {
        $hot = [];
        $quiet = [];
        foreach ($rows as $row) {
            if ($this->isHotFlag($row)) {
                $hot[] = $row;
            } else {
                $quiet[] = $row;
            }
        }

        return [$hot, $quiet];
    }

    /**
     * @param  array{value?: string, points?: int}  $row
     */
    public static function isHotFlag(array $row): bool
    {
        if ((int) ($row['points'] ?? 0) > 0) {
            return true;
        }

        return ! in_array((string) ($row['value'] ?? ''), ['0', '—', '', '[]', 'null'], true);
    }

    /**
     * @param  list<array{key: string, value: int}>  $axes
     * @return array{0: list<array{key: string, value: int}>, 1: int}
     */
    private function partitionRadar(array $axes): array
    {
        $hot = [];
        foreach ($axes as $axis) {
            if ((int) ($axis['value'] ?? 0) > 0) {
                $hot[] = $axis;
            }
        }

        return [$hot, max(0, count($axes) - count($hot))];
    }

    /**
     * @param  array<string, mixed>  $onchain
     * @return array{goplus: ?string, trongrid: ?string, tx_window: ?int}
     */
    private function freshness(Check $check, array $onchain, bool $isWalletReport): array
    {
        $goplus = MskTime::format($check->updated_at);
        $trongrid = $isWalletReport && ! empty($onchain['fetched_at'])
            ? MskTime::format((string) $onchain['fetched_at'])
            : null;

        return [
            'goplus' => $goplus !== null ? $goplus.' '.__('aml.timezone_msk') : null,
            'trongrid' => $trongrid !== null ? $trongrid.' '.__('aml.timezone_msk') : null,
            'tx_window' => $isWalletReport ? ($onchain['tx_window'] ?? null) : null,
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $inflowRows
     * @return array<string, mixed>
     */
    private function delta(Check $previous, Check $current, ?array $usdSummary, array $inflowRows): array
    {
        $prevUsd = in_array($previous->type, [CheckType::Address, CheckType::Scan], true)
            ? $this->usd->summarize($previous)
            : null;
        $prevOnchain = is_array($previous->enrichment) ? $previous->enrichment : [];
        $prevInflows = is_array($prevOnchain['inflows'] ?? null) ? $prevOnchain['inflows'] : [];

        $prevKeys = collect($previous->flags ?? [])->pluck('key')->filter()->sort()->values()->all();
        $currKeys = collect($current->flags ?? [])->pluck('key')->filter()->sort()->values()->all();

        return [
            'previous_id' => $previous->id,
            'verdict' => [
                'from' => $previous->verdict?->label() ?? '—',
                'to' => $current->verdict?->label() ?? '—',
            ],
            'score' => [
                'from' => (int) $previous->risk_score,
                'to' => (int) $current->risk_score,
            ],
            'usd' => [
                'from' => is_array($prevUsd) ? ($prevUsd['formatted'] ?? '—') : '—',
                'to' => is_array($usdSummary) ? ($usdSummary['formatted'] ?? '—') : '—',
            ],
            'flags_added' => array_values(array_diff($currKeys, $prevKeys)),
            'flags_removed' => array_values(array_diff($prevKeys, $currKeys)),
            'inflows' => [
                'from' => count($prevInflows),
                'to' => count($inflowRows),
            ],
        ];
    }

    /**
     * @return array<string, int>
     */
    private function flagPoints(Check $check): array
    {
        $points = [];
        foreach ($check->flags ?? [] as $flag) {
            if (! is_array($flag)) {
                continue;
            }
            $key = (string) ($flag['key'] ?? '');
            if ($key === '') {
                continue;
            }
            $points[$key] = match ((string) ($flag['severity'] ?? 'review')) {
                'block' => RiskScoringService::BLOCK_SCORE,
                'review' => $key === 'onchain_hygiene' ? 0 : RiskScoringService::REVIEW_PER_FLAG,
                default => 0,
            };
        }

        return $points;
    }

    /**
     * @param  array<string, mixed>  $onchain
     * @return list<array<string, mixed>>
     */
    private function balanceRows(Check $check, array $onchain, ?array $usdSummary): array
    {
        $usdBySymbol = [];
        foreach ($usdSummary['counted'] ?? [] as $line) {
            $key = strtoupper((string) ($line['symbol'] ?? '')).'|'.($line['kind'] ?? '');
            $usdBySymbol[$key] = $line['usd'] ?? null;
        }

        $rows = [];
        foreach ($onchain['balances'] ?? [] as $row) {
            if (! is_array($row)) {
                continue;
            }
            $kind = $this->narrative->classify($row, $check);
            $usdKey = strtoupper((string) ($row['symbol'] ?? '')).'|'.$kind;
            $rows[] = [
                ...$row,
                'kind' => $kind,
                'label' => $this->assetLabel($row),
                'overridable' => $check->canOverrideVerdict() && ! $this->narrative->isStatusLocked($row),
                'tone' => match ($kind) {
                    'native', 'canonical' => 'success',
                    'lookalike' => 'warning',
                    'noise' => 'danger',
                    default => 'neutral',
                },
                'comment' => __('aml.balance_comment_'.$kind),
                'usd' => $usdBySymbol[$usdKey] ?? null,
            ];
        }

        return $rows;
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function assetLabel(array $row): string
    {
        $symbol = trim((string) ($row['symbol'] ?? ''));
        $name = trim((string) ($row['name'] ?? ''));
        $contract = (string) ($row['contract'] ?? '');
        $nameIsAddress = $name === $contract
            || (strlen($name) >= 26 && str_starts_with($name, 'T'));

        if ($name === '' || $nameIsAddress || strcasecmp($name, $symbol) === 0) {
            return $symbol !== '' ? $symbol : $name;
        }

        return trim($symbol.' '.$name);
    }

    /**
     * @param  array<string, mixed>  $onchain
     * @return list<array<string, mixed>>
     */
    private function inflowRows(Check $check, array $onchain): array
    {
        $rows = [];
        foreach ($onchain['inflows'] ?? [] as $row) {
            if (! is_array($row)) {
                continue;
            }
            $bucket = $this->inflowBucket($check, $row);
            $rows[] = [
                ...$row,
                'bucket' => $bucket,
                'comment' => $this->inflowComment($check, $row),
                'tone' => $this->inflowTone($bucket),
                'explorer' => TronAddress::explorerUrl((string) ($row['from'] ?? '')),
            ];
        }

        return $rows;
    }

    /**
     * @param  array<string, mixed>  $onchain
     * @return list<array<string, mixed>>
     */
    private function outflowRows(Check $check, array $onchain): array
    {
        $rows = [];
        foreach ($onchain['outflows'] ?? [] as $row) {
            if (! is_array($row)) {
                continue;
            }
            $bucket = $this->inflowBucket($check, $row);
            $rows[] = [
                ...$row,
                'bucket' => $bucket,
                'comment' => $this->inflowComment($check, $row),
                'tone' => $this->inflowTone($bucket),
                'explorer' => TronAddress::explorerUrl((string) ($row['to'] ?? '')),
            ];
        }

        return $rows;
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function inflowBucket(Check $check, array $row): string
    {
        $symbol = strtoupper((string) ($row['symbol'] ?? ''));
        $amount = (float) str_replace(',', '', (string) ($row['amount'] ?? '0'));

        if ($symbol === 'TRX' && $amount > 0 && $amount < 0.001) {
            return 'dust';
        }
        if ($symbol === 'TRX') {
            return 'trx';
        }

        $kind = $this->narrative->classify([
            'symbol' => (string) ($row['symbol'] ?? ''),
            'name' => (string) ($row['name'] ?? ''),
            'contract' => (string) ($row['contract'] ?? ''),
            'kind' => 'trc20',
        ], $check);

        return match ($kind) {
            'canonical' => 'stable',
            'ignore' => 'other',
            default => 'spam',
        };
    }

    private function inflowTone(string $bucket): string
    {
        return match ($bucket) {
            'stable', 'trx' => 'success',
            'dust', 'other' => 'warning',
            'spam' => 'danger',
            default => 'neutral',
        };
    }

    /**
     * @param  list<array<string, mixed>>  $inflowRows
     * @return list<array{key: string, value: int}>
     */
    private function inflowBars(array $inflowRows): array
    {
        $counts = ['trx' => 0, 'dust' => 0, 'stable' => 0, 'spam' => 0];
        foreach ($inflowRows as $row) {
            $bucket = $row['bucket'] ?? 'spam';
            $n = max(1, (int) ($row['tx_count'] ?? 1));
            if (! isset($counts[$bucket])) {
                $bucket = 'spam';
            }
            $counts[$bucket] += $n;
        }

        $rows = [];
        foreach ($counts as $key => $value) {
            if ($value > 0) {
                $rows[] = ['key' => $key, 'value' => $value];
            }
        }

        return $rows;
    }

    /**
     * @param  list<array<string, mixed>>  $slices
     * @return list<array<string, mixed>>
     */
    private function withShares(array $slices): array
    {
        $total = (int) array_sum(array_column($slices, 'value'));
        $assigned = 0;
        $last = count($slices) - 1;
        foreach ($slices as $i => &$slice) {
            $slice['pct'] = $total < 1
                ? 0
                : ($i === $last ? max(0, 100 - $assigned) : (int) round(100 * $slice['value'] / $total));
            if ($i !== $last) {
                $assigned += (int) $slice['pct'];
            }
        }
        unset($slice);

        return $slices;
    }

    /**
     * @param  list<array{key: string, value: int}>  $bars
     * @return list<array{key: string, value: int, pct: int}>
     */
    private function withBarPercents(array $bars): array
    {
        $values = array_column($bars, 'value');
        $max = max(1, $values === [] ? 1 : max($values));
        foreach ($bars as &$bar) {
            $bar['pct'] = (int) round(100 * $bar['value'] / $max);
        }
        unset($bar);

        return $bars;
    }

    /**
     * @param  array<string, mixed>  $onchain
     * @param  array<string, mixed>|null  $usdSummary
     * @return list<array{label: string, value: string, href?: string}>
     */
    private function objectRows(Check $check, bool $hasOnchain, array $onchain, ?array $usdSummary): array
    {
        $raw = is_array($check->raw_response) ? $check->raw_response : [];
        $isContract = ($raw['contract_address'] ?? '0') === '1' || ($raw['contract_address'] ?? null) === 1;
        $isWallet = in_array($check->type, [CheckType::Address, CheckType::Scan], true);
        $typeValue = $isWallet
            ? ($isContract ? __('aml.object_contract') : __('aml.object_eoa'))
            : $check->type->label();

        $subjectRow = ['label' => __('aml.subject'), 'value' => $check->subject];
        if ($explorerUrl = TronAddress::explorerUrl($check->subject)) {
            $subjectRow['href'] = $explorerUrl;
        }

        $rows = [
            $subjectRow,
            ['label' => __('aml.chain'), 'value' => $check->chainName() ?? '—'],
            ['label' => __('aml.type'), 'value' => $typeValue],
            ['label' => __('aml.created'), 'value' => (string) $check->created_at],
            ['label' => __('aml.operator'), 'value' => $check->user->name ?? '—'],
        ];

        if ($hasOnchain && is_array($usdSummary)) {
            $rows[] = ['label' => __('aml.wallet_usd'), 'value' => $usdSummary['formatted']];
        }

        $rows[] = ['label' => __('aml.onchain_source'), 'value' => $hasOnchain
            ? ($onchain['source'] ?? 'GoPlus').' · '.$this->sourcesLabel($check)
            : $this->sourcesLabel($check)];

        if ($check->verdictIsLocked()) {
            $payload = $check->overridePayload();
            $analyst = isset($payload['by']) ? User::query()->find($payload['by']) : null;
            $at = isset($payload['at']) ? MskTime::format((string) $payload['at']) : null;
            $note = is_string($payload['note'] ?? null) && $payload['note'] !== '' ? $payload['note'] : null;
            $rows[] = ['label' => __('aml.verdict'), 'value' => $check->verdict?->label() ?? '—'];
            $rows[] = ['label' => __('aml.override_by'), 'value' => $analyst?->name ?? '—'];
            $rows[] = ['label' => __('aml.override_at'), 'value' => $at ?? '—'];
            $rows[] = ['label' => __('aml.override_note'), 'value' => $note ?? __('aml.override_no_note')];
        }

        return $rows;
    }

    private function sourcesLabel(Check $check): string
    {
        return in_array($check->type, [CheckType::Address, CheckType::Scan], true)
            ? 'GoPlus · TronGrid'
            : 'GoPlus';
    }

    /**
     * @param  array<string, mixed>  $onchain
     * @return list<array{address: string, weight: int, role: string, tone: string}>
     */
    private function signerRows(Check $check, array $onchain): array
    {
        $signers = is_array($onchain['control']['signers'] ?? null) ? $onchain['control']['signers'] : [];
        $weights = array_map(fn ($row) => (int) ($row['weight'] ?? 1), $signers);
        $max = $weights === [] ? 0 : max($weights);
        $rows = [];

        foreach ($signers as $signer) {
            if (! is_array($signer)) {
                continue;
            }
            $address = (string) ($signer['address'] ?? '');
            $weight = (int) ($signer['weight'] ?? 1);
            if ($address === $check->subject) {
                $role = __('aml.signer_checked');
                $tone = 'info';
            } elseif ($max > 1 && $weight === $max) {
                $role = __('aml.signer_controlling');
                $tone = 'warning';
            } else {
                $role = __('aml.signer_other');
                $tone = 'neutral';
            }

            $rows[] = [
                'address' => $address,
                'weight' => $weight,
                'role' => $role,
                'tone' => $tone,
            ];
        }

        return $rows;
    }

    /**
     * @param  array<string, mixed>  $onchain
     */
    private function controlNarrative(Check $check, array $onchain): string
    {
        if (($onchain['control']['type'] ?? '') !== 'multisig') {
            return __('aml.control_single_note');
        }

        $threshold = (string) ($onchain['control']['threshold'] ?? '');
        $checked = '1';
        $other = '';
        foreach ($onchain['control']['signers'] ?? [] as $signer) {
            if (! is_array($signer)) {
                continue;
            }
            if (($signer['address'] ?? '') === $check->subject) {
                $checked = (string) ($signer['weight'] ?? 1);
            } else {
                $other = (string) ($signer['weight'] ?? 1);
            }
        }

        return __('aml.control_multisig_note', [
            'threshold' => $threshold,
            'checked' => $checked,
            'other' => $other !== '' ? $other : '—',
        ]);
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function inflowComment(Check $check, array $row): string
    {
        return match ($this->inflowBucket($check, $row)) {
            'dust' => __('aml.inflow_comment_dust'),
            'trx' => __('aml.inflow_comment_trx'),
            'stable' => __('aml.inflow_comment_stable'),
            'spam' => __('aml.inflow_comment_spam'),
            default => __('aml.inflow_comment_other'),
        };
    }

    /**
     * @param  array<string, mixed>  $onchain
     * @return list<string>
     */
    private function conclusion(Check $check, bool $hasOnchain, array $onchain, ?array $usdSummary): array
    {
        $parts = [__('aml.conclusion_goplus', [
            'verdict' => $check->verdict?->label() ?? '—',
            'score' => (string) $check->risk_score,
        ])];

        if ($hasOnchain && is_array($usdSummary)) {
            $parts[] = __('aml.conclusion_usd', [
                'amount' => $usdSummary['formatted'],
                'source' => __('aml.usd_source_'.$usdSummary['source']),
            ]);
        }

        if ($hasOnchain && $this->narrative->needsReview($onchain, $check)) {
            $parts[] = __('aml.conclusion_onchain_review');
        } elseif ($hasOnchain) {
            $parts[] = __('aml.conclusion_onchain_ok');
        }

        if ($check->verdictIsLocked()) {
            $payload = $check->overridePayload();
            $note = is_string($payload['note'] ?? null) && $payload['note'] !== ''
                ? $payload['note']
                : null;
            $analyst = isset($payload['by']) ? User::query()->find($payload['by']) : null;
            $at = isset($payload['at']) ? MskTime::format((string) $payload['at']) : null;
            $parts[] = __('aml.conclusion_analyst', [
                'verdict' => $check->verdict?->label() ?? '—',
                'note' => $note ?? __('aml.override_no_note'),
                'name' => $analyst?->name ?? '—',
                'at' => $at ?? '—',
            ]);
        }

        $parts[] = __('aml.disclaimer');

        return $parts;
    }

    private function goplusVerdict(Check $check): CheckVerdict
    {
        $raw = is_array($check->raw_response) ? $check->raw_response : [];

        return $this->scoring->score($check->type, $raw)['verdict'];
    }
}
