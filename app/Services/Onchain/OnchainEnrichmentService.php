<?php

// | KB @CerberRus00 - Nexus Invest Team
namespace App\Services\Onchain;

use App\Enums\CheckType;
use App\Enums\CheckStatus;
use App\Enums\CheckVerdict;
use App\Jobs\ExpandWalletGraphJob;
use App\Models\Check;
use App\Support\TronAddress;
use Throwable;

class OnchainEnrichmentService
{
    /** @var array<string, array{symbol: string, decimals: int, name: string}> */
    private const KNOWN_TRC20 = [
        'TR7NHqjeKQxGTCi8q8ZY4pL8otSzgjLj6t' => ['symbol' => 'USDT', 'decimals' => 6, 'name' => 'Tether USD'],
        'TEkxiTehnzSmSe2XqrBj4w32RUN966rdz8' => ['symbol' => 'USDC', 'decimals' => 6, 'name' => 'USD Coin'],
    ];

    public function __construct(
        private TronGridClient $tron,
        private AssetNarrativeService $narrative,
    ) {}

    public function fill(Check $check): Check
    {
        if (! in_array($check->type, [CheckType::Address, CheckType::Scan], true)) {
            return $check;
        }

        $deep = $check->type === CheckType::Scan;

        if ($this->shouldRefetch(is_array($check->enrichment) ? $check->enrichment : null)) {
            try {
                $payload = $this->forAddress($check->subject, $check->chain_id, $deep);
                $check->update(['enrichment' => $payload]);
            } catch (Throwable $e) {
                $check->update([
                    'enrichment' => [
                        'source' => 'trongrid',
                        'error' => $e->getMessage(),
                        'fetched_at' => now()->toIso8601String(),
                    ],
                ]);
            }
            $check = $check->refresh();
        }

        $this->queueGraphExpansion($check);

        return $this->promoteOnchainReview($check->refresh());
    }

    /**
     * Type hop-1 neighbors and optionally walk hop-2. Scan only; never from the HTTP request.
     */
    public function expandGraph(Check $check): Check
    {
        if ($check->type !== CheckType::Scan) {
            return $check;
        }

        $onchain = is_array($check->enrichment) ? $check->enrichment : [];
        if ($onchain === [] || ! empty($onchain['error']) || ! empty($onchain['skipped'])) {
            return $check;
        }

        $graph = is_array($onchain['graph'] ?? null) ? $onchain['graph'] : [];
        if (($graph['pending'] ?? false) !== true) {
            return $check;
        }

        $truncated = (bool) ($graph['truncated'] ?? false);
        $subject = $check->subject;
        $limit = (int) ($onchain['tx_window'] ?? config('onchain.scan_tx_limit', 200));
        $pages = max(1, (int) config('onchain.fingerprint_pages', 2));

        $nodes = $this->nodesById(is_array($graph['nodes'] ?? null) ? $graph['nodes'] : []);
        $edges = is_array($graph['edges'] ?? null) ? $graph['edges'] : [];
        $fingerprint = is_string($onchain['trc20_fingerprint'] ?? null) ? $onchain['trc20_fingerprint'] : null;
        if ($pages > 1 && $fingerprint) {
            $extra = $this->tron->incomingTrc20($subject, $limit, $pages - 1, true, $fingerprint);
            $truncated = $truncated || $this->tron->lastWasRateLimited();
            $this->ingestTrc20Edges($nodes, $edges, $subject, $extra, 'in');
        }

        $maxNodes = (int) config('onchain.graph_max_nodes', 20);
        $neighborCap = (int) config('onchain.graph_neighbor_cap', 12);
        $truncated = $this->trimGraph($nodes, $edges, $subject, $maxNodes, $neighborCap) || $truncated;

        $hop1Ids = [];
        foreach ($nodes as $id => $node) {
            if ((int) ($node['hop'] ?? 1) === 1 && $id !== $subject) {
                $hop1Ids[] = $id;
            }
        }

        foreach ($hop1Ids as $id) {
            if (($nodes[$id]['kind'] ?? 'unknown') !== 'unknown') {
                continue;
            }
            $account = $this->tron->account($id, true);
            if (! empty($account['_rate_limited'])) {
                $truncated = true;
                break;
            }
            $nodes[$id]['kind'] = $this->kindFromAccount($id, $account);
        }

        $seeds = $this->hop2Seeds($nodes, $edges, $subject);
        foreach ($seeds as $seed) {
            if (count($nodes) >= $maxNodes) {
                $truncated = true;
                break;
            }
            $rows = $this->tron->incomingTrc20($seed, min(50, $limit), 1, true);
            if ($this->tron->lastWasRateLimited()) {
                $truncated = true;
                break;
            }
            foreach ($this->moneyTrc20($rows) as $tx) {
                $from = (string) ($tx['from'] ?? '');
                if ($from === '' || $from === $seed || isset($nodes[$from])) {
                    if ($from !== '' && $from !== $seed) {
                        $edges[] = $this->edge($from, $seed, $tx, 'in');
                    }
                    continue;
                }
                if (count($nodes) >= $maxNodes) {
                    $truncated = true;
                    break;
                }
                $kind = $this->kindFromKnown($from);
                if ($kind === 'unknown') {
                    $account = $this->tron->account($from, true);
                    if (! empty($account['_rate_limited'])) {
                        $truncated = true;
                        $kind = 'unknown';
                    } else {
                        $kind = $this->kindFromAccount($from, $account);
                    }
                }
                $nodes[$from] = ['id' => $from, 'kind' => $kind, 'hop' => 2];
                $edges[] = $this->edge($from, $seed, $tx, 'in');
            }
        }

        $onchain['graph'] = [
            'nodes' => array_values($nodes),
            'edges' => $this->uniqueEdges($edges),
            'truncated' => $truncated,
            'pending' => false,
            'hop2_queued' => true,
        ];
        $graphNodes = $this->nodesById($onchain['graph']['nodes']);
        $graphEdges = $onchain['graph']['edges'];
        $this->annotateNodes($graphNodes, $graphEdges);
        $onchain['graph']['nodes'] = array_values($graphNodes);
        $onchain['graph']['edges'] = $graphEdges;
        $check->update(['enrichment' => $onchain]);

        return $check->refresh();
    }

    /**
     * @param  array<string, mixed>|null  $enrichment
     */
    private function shouldRefetch(?array $enrichment): bool
    {
        if ($enrichment === null || $enrichment === []) {
            return true;
        }

        $error = (string) ($enrichment['error'] ?? '');
        if ($error === '') {
            return false;
        }

        return str_contains($error, '429')
            || str_contains($error, 'allowed_rps')
            || str_contains($error, 'Too Many Attempts');
    }

    public function needsFetch(Check $check): bool
    {
        if (! in_array($check->type, [CheckType::Address, CheckType::Scan], true)) {
            return false;
        }

        $enrichment = is_array($check->enrichment) ? $check->enrichment : null;

        return $enrichment === null || $enrichment === [];
    }

    /**
     * On-chain hygiene review (multisig, lookalikes, spam) raises a clear file to review.
     */
    public function promoteOnchainReview(Check $check): Check
    {
        if ($check->status !== CheckStatus::Completed) {
            return $check;
        }
        if ($check->verdictIsLocked()) {
            return $check;
        }
        if ($check->verdict !== CheckVerdict::Clear) {
            return $check;
        }

        $onchain = is_array($check->enrichment) ? $check->enrichment : [];
        if (! $this->narrative->needsReview($onchain, $check)) {
            return $check;
        }

        $flags = is_array($check->flags) ? $check->flags : [];
        $already = false;
        foreach ($flags as $flag) {
            if (($flag['key'] ?? '') === 'onchain_hygiene') {
                $already = true;
                break;
            }
        }
        if (! $already) {
            $flags[] = [
                'key' => 'onchain_hygiene',
                'value' => 'review',
                'severity' => 'review',
            ];
        }

        $check->update([
            'verdict' => CheckVerdict::Review,
            'risk_score' => max((int) $check->risk_score, 20),
            'flags' => $flags,
        ]);

        return $check->refresh();
    }

    /**
     * @return array<string, mixed>
     */
    public function forAddress(string $address, ?string $chainId, bool $deep = false): array
    {
        $isTron = TronAddress::isTron($address) || strtolower((string) $chainId) === 'tron';
        if (! $isTron) {
            return [
                'skipped' => true,
                'reason' => 'unsupported_network',
                'fetched_at' => now()->toIso8601String(),
            ];
        }

        $limit = $deep
            ? (int) config('onchain.scan_tx_limit', 200)
            : (int) config('onchain.tx_limit', 50);

        $account = $this->tron->account($address);
        $inTrc20 = $this->tron->incomingTrc20($address, $limit);
        $trc20Fingerprint = $this->tron->lastFingerprint();
        $inTrx = $this->tron->incomingTrx($address, $limit);
        $outTrc20 = $this->tron->outgoingTrc20($address, $limit, 1, true);
        $partial = $this->tron->lastWasRateLimited();
        $outTrx = $this->tron->outgoingTrx($address, $limit, 1, true);
        $partial = $partial || $this->tron->lastWasRateLimited();
        $internalTx = $this->tron->internalTransactions($address, $limit, true);
        $partial = $partial || $this->tron->lastWasRateLimited();

        $moneyIn = $this->moneyTrc20($inTrc20);
        $moneyOut = $this->moneyTrc20($outTrc20);
        $approvals = count($inTrc20) + count($outTrc20) - count($moneyIn) - count($moneyOut);
        $tokenMeta = $this->tokenMetaFromTransfers(array_merge($moneyIn, $moneyOut));
        $graph = $this->buildHop1Graph($address, $account, $inTrx, $outTrx, $moneyIn, $moneyOut, $internalTx, $deep);

        $payload = [
            'source' => 'trongrid',
            'network' => 'tron',
            'fetched_at' => now()->toIso8601String(),
            'tx_window' => $limit,
            'control' => $this->control($account),
            'balances' => $this->balances($account, $tokenMeta),
            'inflows' => $this->inflows($inTrx, $moneyIn),
            'outflows' => $this->outflows($outTrx, $moneyOut),
            'approvals' => max(0, $approvals),
            'internal_count' => count($internalTx),
            'partial' => $partial || (bool) ($graph['truncated'] ?? false),
            'graph' => $graph,
            'trc20_fingerprint' => $trc20Fingerprint,
        ];

        return $payload;
    }

    private function queueGraphExpansion(Check $check): void
    {
        if ($check->type !== CheckType::Scan) {
            return;
        }

        $onchain = is_array($check->enrichment) ? $check->enrichment : [];
        $graph = is_array($onchain['graph'] ?? null) ? $onchain['graph'] : [];
        if (($graph['pending'] ?? false) !== true) {
            return;
        }
        if (! empty($graph['hop2_queued'])) {
            return;
        }

        $onchain['graph']['hop2_queued'] = true;
        $check->update(['enrichment' => $onchain]);
        ExpandWalletGraphJob::dispatch($check->id);
    }

    /**
     * @param  array<string, mixed>  $account
     * @param  list<array<string, mixed>>  $inTrx
     * @param  list<array<string, mixed>>  $outTrx
     * @param  list<array<string, mixed>>  $inTrc20
     * @param  list<array<string, mixed>>  $outTrc20
     * @param  list<array<string, mixed>>  $internalTx
     * @return array<string, mixed>
     */
    private function buildHop1Graph(
        string $subject,
        array $account,
        array $inTrx,
        array $outTrx,
        array $inTrc20,
        array $outTrc20,
        array $internalTx,
        bool $deep,
    ): array {
        $nodes = [
            $subject => [
                'id' => $subject,
                'kind' => $this->kindFromAccount($subject, $account),
                'hop' => 0,
            ],
        ];
        $edges = [];

        $this->ingestTrxEdges($nodes, $edges, $subject, $inTrx, 'in');
        $this->ingestTrxEdges($nodes, $edges, $subject, $outTrx, 'out');
        $this->ingestTrc20Edges($nodes, $edges, $subject, $inTrc20, 'in');
        $this->ingestTrc20Edges($nodes, $edges, $subject, $outTrc20, 'out');
        $this->ingestInternalEdges($nodes, $edges, $subject, $internalTx);

        $maxNodes = (int) config('onchain.graph_max_nodes', 20);
        $neighborCap = (int) config('onchain.graph_neighbor_cap', 12);
        $truncated = $this->trimGraph($nodes, $edges, $subject, $maxNodes, $neighborCap);

        $edges = $this->uniqueEdges($edges);
        $this->annotateNodes($nodes, $edges);

        return [
            'nodes' => array_values($nodes),
            'edges' => $edges,
            'truncated' => $truncated,
            'pending' => $deep,
            'hop2_queued' => false,
        ];
    }

    /**
     * @param  array<string, array<string, mixed>>  $nodes
     * @param  list<array<string, mixed>>  $edges
     * @param  list<array<string, mixed>>  $rows
     */
    private function ingestTrxEdges(array &$nodes, array &$edges, string $subject, array $rows, string $direction): void
    {
        foreach ($rows as $tx) {
            $value = $tx['raw_data']['contract'][0]['parameter']['value'] ?? [];
            $from = $this->base58((string) ($value['owner_address'] ?? ''));
            $to = $this->base58((string) ($value['to_address'] ?? ''));
            $peer = $direction === 'in' ? $from : $to;
            if ($peer === '' || $peer === $subject) {
                continue;
            }
            $this->ensureHop1($nodes, $peer);
            $raw = (string) ($value['amount'] ?? '0');
            $edges[] = [
                'from' => $from !== '' ? $from : $subject,
                'to' => $to !== '' ? $to : $subject,
                'asset' => 'TRX',
                'count' => 1,
                'direction' => $direction,
                'raw' => $raw,
                'decimals' => 6,
                'name' => 'TRON',
                'hygiene' => $this->edgeHygiene('TRX', $raw, 6, null, 'TRON'),
            ];
        }
    }

    /**
     * @param  array<string, array<string, mixed>>  $nodes
     * @param  list<array<string, mixed>>  $edges
     * @param  list<array<string, mixed>>  $rows
     */
    private function ingestTrc20Edges(array &$nodes, array &$edges, string $subject, array $rows, string $direction): void
    {
        foreach ($this->moneyTrc20($rows) as $tx) {
            $from = (string) ($tx['from'] ?? '');
            $to = (string) ($tx['to'] ?? '');
            $peer = $direction === 'in' ? $from : $to;
            if ($peer === '' || $peer === $subject) {
                continue;
            }
            $this->ensureHop1($nodes, $peer);
            $edges[] = $this->edge($from !== '' ? $from : $subject, $to !== '' ? $to : $subject, $tx, $direction);
        }
    }

    /**
     * @param  array<string, array<string, mixed>>  $nodes
     * @param  list<array<string, mixed>>  $edges
     * @param  list<array<string, mixed>>  $rows
     */
    private function ingestInternalEdges(array &$nodes, array &$edges, string $subject, array $rows): void
    {
        foreach ($rows as $tx) {
            $from = (string) ($tx['from_address'] ?? $tx['from'] ?? '');
            $to = (string) ($tx['to_address'] ?? $tx['to'] ?? '');
            foreach ([$from, $to] as $peer) {
                if ($peer !== '' && $peer !== $subject) {
                    $this->ensureHop1($nodes, $peer);
                }
            }
            if ($from === '' && $to === '') {
                continue;
            }
            $raw = (string) ($tx['call_value'] ?? $tx['value'] ?? '0');
            if (! preg_match('/^-?\d+$/', $raw)) {
                $raw = '0';
            }
            $edges[] = [
                'from' => $from !== '' ? $from : $subject,
                'to' => $to !== '' ? $to : $subject,
                'asset' => 'TRX',
                'count' => 1,
                'direction' => 'internal',
                'raw' => $raw,
                'decimals' => 6,
                'name' => 'TRON',
                'hygiene' => $this->edgeHygiene('TRX', $raw, 6, null, 'TRON'),
            ];
        }
    }

    /**
     * @param  array<string, array<string, mixed>>  $nodes
     */
    private function ensureHop1(array &$nodes, string $id): void
    {
        if (isset($nodes[$id])) {
            return;
        }
        $nodes[$id] = [
            'id' => $id,
            'kind' => $this->kindFromKnown($id),
            'hop' => 1,
        ];
    }

    /**
     * @param  array<string, mixed>  $tx
     * @return array<string, mixed>
     */
    private function edge(string $from, string $to, array $tx, string $direction): array
    {
        $info = is_array($tx['token_info'] ?? null) ? $tx['token_info'] : [];
        $symbol = (string) ($info['symbol'] ?? 'TRC20');
        $decimals = (int) ($info['decimals'] ?? 6);
        $raw = (string) ($tx['value'] ?? '0');
        $contract = (string) ($info['address'] ?? '');
        $name = (string) ($info['name'] ?? '');

        return [
            'from' => $from,
            'to' => $to,
            'asset' => $symbol,
            'count' => 1,
            'direction' => $direction,
            'contract' => $contract,
            'raw' => $raw,
            'decimals' => $decimals,
            'name' => $name,
            'hygiene' => $this->edgeHygiene($symbol, $raw, $decimals, $contract, $name),
        ];
    }

    /**
     * @param  array<string, array<string, mixed>>  $nodes
     * @param  list<array<string, mixed>>  $edges
     */
    private function trimGraph(array &$nodes, array &$edges, string $subject, int $maxNodes, int $neighborCap): bool
    {
        $truncated = false;
        $hop1 = [];
        foreach ($nodes as $id => $node) {
            if ($id !== $subject && (int) ($node['hop'] ?? 1) === 1) {
                $hop1[] = $id;
            }
        }
        if (count($hop1) > $neighborCap) {
            $truncated = true;
            $keep = array_slice($hop1, 0, $neighborCap);
            $allowed = array_flip(array_merge([$subject], $keep));
            $nodes = array_intersect_key($nodes, $allowed);
            $edges = array_values(array_filter(
                $edges,
                fn ($edge) => isset($allowed[$edge['from'] ?? '']) && isset($allowed[$edge['to'] ?? '']),
            ));
        }
        if (count($nodes) > $maxNodes) {
            $truncated = true;
            $keepIds = array_slice(array_keys($nodes), 0, $maxNodes);
            if (! in_array($subject, $keepIds, true)) {
                $keepIds[0] = $subject;
            }
            $allowed = array_flip($keepIds);
            $nodes = array_intersect_key($nodes, $allowed);
            $edges = array_values(array_filter(
                $edges,
                fn ($edge) => isset($allowed[$edge['from'] ?? '']) && isset($allowed[$edge['to'] ?? '']),
            ));
        }

        return $truncated;
    }

    /**
     * @param  list<array<string, mixed>>  $nodes
     * @return array<string, array<string, mixed>>
     */
    private function nodesById(array $nodes): array
    {
        $map = [];
        foreach ($nodes as $node) {
            if (! is_array($node) || empty($node['id'])) {
                continue;
            }
            $map[(string) $node['id']] = $node;
        }

        return $map;
    }

    /**
     * @param  array<string, array<string, mixed>>  $nodes
     * @param  list<array<string, mixed>>  $edges
     */
    private function annotateNodes(array &$nodes, array $edges): void
    {
        foreach ($nodes as $id => &$node) {
            $in = 0;
            $out = 0;
            $flags = [];
            $hop = (int) ($node['hop'] ?? 1);
            foreach ($edges as $edge) {
                if (! is_array($edge)) {
                    continue;
                }
                $from = (string) ($edge['from'] ?? '');
                $to = (string) ($edge['to'] ?? '');
                if ($from !== $id && $to !== $id) {
                    continue;
                }
                $count = (int) ($edge['count'] ?? 1);
                $direction = (string) ($edge['direction'] ?? '');
                if ($hop === 0) {
                    if ($direction === 'in' && $to === $id) {
                        $in += $count;
                    }
                    if ($direction === 'out' && $from === $id) {
                        $out += $count;
                    }
                } else {
                    if ($direction === 'in' && $from === $id) {
                        $in += $count;
                    }
                    if ($direction === 'out' && $to === $id) {
                        $out += $count;
                    }
                    if ($direction === 'internal') {
                        if ($from === $id) {
                            $out += $count;
                        }
                        if ($to === $id) {
                            $in += $count;
                        }
                    }
                }
                $hygiene = (string) ($edge['hygiene'] ?? '');
                if ($hygiene === 'dust' && ! in_array('dust', $flags, true)) {
                    $flags[] = 'dust';
                }
                if ($hygiene === 'spam' && ! in_array('spam', $flags, true)) {
                    $flags[] = 'spam';
                }
            }
            $node['in_count'] = $in;
            $node['out_count'] = $out;
            $node['flags'] = $flags;
        }
        unset($node);
    }

    private function edgeHygiene(string $symbol, string $raw, int $decimals, ?string $contract, ?string $name): string
    {
        $amount = (float) str_replace(',', '', $this->formatAmount($raw, max(0, $decimals)));
        if (strtoupper($symbol) === 'TRX') {
            if ($amount > 0 && $amount < 0.001) {
                return 'dust';
            }

            return 'trx';
        }

        $kind = $this->narrative->autoKind([
            'symbol' => $symbol,
            'name' => (string) $name,
            'contract' => (string) $contract,
            'kind' => 'trc20',
        ]);

        return $kind === 'canonical' ? 'stable' : 'spam';
    }

    /**
     * @param  list<array<string, mixed>>  $edges
     * @return list<array<string, mixed>>
     */
    private function uniqueEdges(array $edges): array
    {
        $groups = [];
        foreach ($edges as $edge) {
            if (! is_array($edge)) {
                continue;
            }
            $key = ($edge['from'] ?? '').'|'.($edge['to'] ?? '').'|'.($edge['asset'] ?? '').'|'.($edge['direction'] ?? '');
            if (! isset($groups[$key])) {
                $groups[$key] = $edge;
                $groups[$key]['count'] = (int) ($edge['count'] ?? 1);
                $groups[$key]['raw'] = (string) ($edge['raw'] ?? '0');
            } else {
                $groups[$key]['count'] += (int) ($edge['count'] ?? 1);
                $groups[$key]['raw'] = $this->addDecimalStrings(
                    (string) ($groups[$key]['raw'] ?? '0'),
                    (string) ($edge['raw'] ?? '0'),
                );
            }
        }

        foreach ($groups as &$group) {
            $group['hygiene'] = $this->edgeHygiene(
                (string) ($group['asset'] ?? ''),
                (string) ($group['raw'] ?? '0'),
                (int) ($group['decimals'] ?? 6),
                (string) ($group['contract'] ?? ''),
                (string) ($group['name'] ?? ''),
            );
        }
        unset($group);

        return array_values($groups);
    }

    /**
     * @param  array<string, array<string, mixed>>  $nodes
     * @param  list<array<string, mixed>>  $edges
     * @return list<string>
     */
    private function hop2Seeds(array $nodes, array $edges, string $subject): array
    {
        $scores = [];
        foreach ($edges as $edge) {
            foreach (['from', 'to'] as $side) {
                $id = (string) ($edge[$side] ?? '');
                if ($id === '' || $id === $subject) {
                    continue;
                }
                $kind = $nodes[$id]['kind'] ?? 'unknown';
                $hop = (int) ($nodes[$id]['hop'] ?? 1);
                if ($hop !== 1 || $kind !== 'eoa') {
                    continue;
                }
                $scores[$id] = ($scores[$id] ?? 0) + (int) ($edge['count'] ?? 1);
            }
        }
        arsort($scores);
        $cap = (int) config('onchain.graph_hop2_seeds', 4);

        return array_slice(array_keys($scores), 0, $cap);
    }

    /**
     * @param  array<string, mixed>  $account
     */
    private function kindFromAccount(string $address, array $account): string
    {
        $known = $this->kindFromKnown($address);
        if ($known !== 'unknown') {
            return $known;
        }
        $type = $account['type'] ?? 0;
        if ($type === 2 || $type === '2' || strcasecmp((string) $type, 'Contract') === 0) {
            return 'contract';
        }
        if (! empty($account['bytecode']) || ! empty($account['code'])) {
            return 'contract';
        }

        return 'eoa';
    }

    private function kindFromKnown(string $address): string
    {
        if (isset(self::KNOWN_TRC20[$address])) {
            return 'token';
        }

        $bucket = $this->narrative->autoKind([
            'symbol' => '',
            'name' => '',
            'contract' => $address,
            'kind' => 'trc20',
        ]);

        return $bucket === 'canonical' ? 'token' : 'unknown';
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @return list<array<string, mixed>>
     */
    private function moneyTrc20(array $rows): array
    {
        return array_values(array_filter($rows, function ($tx) {
            if (! is_array($tx)) {
                return false;
            }
            $type = strtolower((string) ($tx['type'] ?? 'transfer'));
            if (str_contains($type, 'approval')) {
                return false;
            }
            $info = is_array($tx['token_info'] ?? null) ? $tx['token_info'] : [];
            $tokenType = strtolower((string) ($info['type'] ?? ''));
            if (str_contains($tokenType, 'nft') || str_contains($tokenType, 'trc721')) {
                return false;
            }

            return true;
        }));
    }

    private function base58(string $hexOrBase58): string
    {
        if ($hexOrBase58 === '') {
            return '';
        }
        if (TronAddress::isTron($hexOrBase58)) {
            return $hexOrBase58;
        }

        return $this->tron->hexToBase58($hexOrBase58);
    }

    /**
     * @param  array<string, mixed>  $account
     * @return array<string, mixed>
     */
    private function control(array $account): array
    {
        $permission = is_array($account['owner_permission'] ?? null) ? $account['owner_permission'] : [];
        $signers = [];
        foreach ($permission['keys'] ?? [] as $key) {
            if (! empty($key['address'])) {
                $signers[] = [
                    'address' => (string) $key['address'],
                    'weight' => (int) ($key['weight'] ?? 1),
                ];
            }
        }

        $threshold = (int) ($permission['threshold'] ?? 1);

        return [
            'type' => count($signers) > 1 || $threshold > 1 ? 'multisig' : 'single',
            'threshold' => $threshold,
            'signers' => $signers,
            'created_at' => isset($account['create_time'])
                ? date('c', (int) floor(((int) $account['create_time']) / 1000))
                : null,
        ];
    }

    /**
     * @param  array<string, mixed>  $account
     * @param  array<string, array{symbol: string, decimals: int, name: string}>  $tokenMeta
     * @return list<array<string, mixed>>
     */
    private function balances(array $account, array $tokenMeta): array
    {
        $rows = [[
            'symbol' => 'TRX',
            'name' => 'TRON',
            'amount' => $this->formatAmount((string) ($account['balance'] ?? '0'), 6),
            'contract' => null,
            'kind' => 'native',
        ]];

        foreach ($account['trc20'] ?? [] as $entry) {
            if (! is_array($entry)) {
                continue;
            }
            $contract = (string) array_key_first($entry);
            $raw = (string) ($entry[$contract] ?? '0');
            $meta = $tokenMeta[$contract] ?? self::KNOWN_TRC20[$contract] ?? [
                'symbol' => substr($contract, 0, 6).'…',
                'decimals' => 6,
                'name' => $contract,
            ];
            $rows[] = [
                'symbol' => $meta['symbol'],
                'name' => $meta['name'],
                'amount' => $this->formatAmount($raw, $meta['decimals']),
                'contract' => $contract,
                'kind' => 'trc20',
            ];
        }

        usort($rows, function (array $a, array $b) {
            $priority = ['TRX' => 0, 'USDT' => 1, 'USDC' => 2];

            return ($priority[$a['symbol']] ?? 50) <=> ($priority[$b['symbol']] ?? 50);
        });

        $max = (int) config('onchain.balance_limit', 15);

        return array_slice($rows, 0, $max);
    }

    /**
     * @param  list<array<string, mixed>>  $trxTx
     * @param  list<array<string, mixed>>  $trc20Tx
     * @return list<array<string, mixed>>
     */
    private function inflows(array $trxTx, array $trc20Tx): array
    {
        $groups = [];

        foreach ($trxTx as $tx) {
            $value = $tx['raw_data']['contract'][0]['parameter']['value'] ?? [];
            $fromHex = (string) ($value['owner_address'] ?? '');
            $from = $fromHex !== '' ? $this->tron->hexToBase58($fromHex) : 'unknown';
            $raw = (string) ($value['amount'] ?? '0');
            $this->addFlow($groups, $from, 'TRX', 6, $raw, (string) ($tx['txID'] ?? ''), (int) ($tx['block_timestamp'] ?? 0));
        }

        foreach ($trc20Tx as $tx) {
            $info = is_array($tx['token_info'] ?? null) ? $tx['token_info'] : [];
            $this->addFlow(
                $groups,
                (string) ($tx['from'] ?? 'unknown'),
                (string) ($info['symbol'] ?? 'TRC20'),
                (int) ($info['decimals'] ?? 6),
                (string) ($tx['value'] ?? '0'),
                (string) ($tx['transaction_id'] ?? ''),
                (int) ($tx['block_timestamp'] ?? 0),
                (string) ($info['address'] ?? ''),
                (string) ($info['name'] ?? ''),
            );
        }

        return $this->sortedFlows($groups);
    }

    /**
     * @param  list<array<string, mixed>>  $trxTx
     * @param  list<array<string, mixed>>  $trc20Tx
     * @return list<array<string, mixed>>
     */
    private function outflows(array $trxTx, array $trc20Tx): array
    {
        $groups = [];

        foreach ($trxTx as $tx) {
            $value = $tx['raw_data']['contract'][0]['parameter']['value'] ?? [];
            $toHex = (string) ($value['to_address'] ?? '');
            $to = $toHex !== '' ? $this->tron->hexToBase58($toHex) : 'unknown';
            $raw = (string) ($value['amount'] ?? '0');
            $this->addFlow($groups, $to, 'TRX', 6, $raw, (string) ($tx['txID'] ?? ''), (int) ($tx['block_timestamp'] ?? 0), null, null, 'to');
        }

        foreach ($trc20Tx as $tx) {
            $info = is_array($tx['token_info'] ?? null) ? $tx['token_info'] : [];
            $this->addFlow(
                $groups,
                (string) ($tx['to'] ?? 'unknown'),
                (string) ($info['symbol'] ?? 'TRC20'),
                (int) ($info['decimals'] ?? 6),
                (string) ($tx['value'] ?? '0'),
                (string) ($tx['transaction_id'] ?? ''),
                (int) ($tx['block_timestamp'] ?? 0),
                (string) ($info['address'] ?? ''),
                (string) ($info['name'] ?? ''),
                'to',
            );
        }

        return $this->sortedFlows($groups);
    }

    /**
     * @param  array<string, array<string, mixed>>  $groups
     * @return list<array<string, mixed>>
     */
    private function sortedFlows(array $groups): array
    {
        $rows = array_values($groups);
        usort($rows, fn ($a, $b) => ($b['last_at'] ?? '') <=> ($a['last_at'] ?? ''));

        return array_slice($rows, 0, 20);
    }

    /**
     * @param  array<string, array<string, mixed>>  $groups
     */
    private function addFlow(
        array &$groups,
        string $peer,
        string $symbol,
        int $decimals,
        string $raw,
        string $txId,
        int $timestampMs,
        ?string $contract = null,
        ?string $name = null,
        string $peerKey = 'from',
    ): void {
        $contract = (string) $contract;
        $key = $peer.'|'.$symbol.'|'.$contract;
        if (! isset($groups[$key])) {
            $groups[$key] = [
                $peerKey => $peer,
                'symbol' => $symbol,
                'name' => $name,
                'contract' => $contract !== '' ? $contract : null,
                'amount' => '0',
                'raw' => '0',
                'decimals' => $decimals,
                'tx_count' => 0,
                'last_tx' => $txId,
                'last_at' => $timestampMs > 0 ? date('c', intdiv($timestampMs, 1000)) : null,
            ];
        }

        $groups[$key]['raw'] = $this->addDecimalStrings((string) $groups[$key]['raw'], $raw);
        $groups[$key]['amount'] = $this->formatAmount((string) $groups[$key]['raw'], $decimals);
        $groups[$key]['tx_count']++;
        if ($timestampMs > 0) {
            $at = date('c', intdiv($timestampMs, 1000));
            if (($groups[$key]['last_at'] ?? '') < $at) {
                $groups[$key]['last_at'] = $at;
                $groups[$key]['last_tx'] = $txId;
            }
        }
    }

    /**
     * @param  list<array<string, mixed>>  $transfers
     * @return array<string, array{symbol: string, decimals: int, name: string}>
     */
    private function tokenMetaFromTransfers(array $transfers): array
    {
        $meta = self::KNOWN_TRC20;
        foreach ($transfers as $tx) {
            $info = is_array($tx['token_info'] ?? null) ? $tx['token_info'] : [];
            $contract = (string) ($info['address'] ?? '');
            if ($contract === '' || isset($meta[$contract])) {
                continue;
            }
            $meta[$contract] = [
                'symbol' => (string) ($info['symbol'] ?: substr($contract, 0, 6).'…'),
                'decimals' => (int) ($info['decimals'] ?? 6),
                'name' => (string) ($info['name'] ?: $contract),
            ];
        }

        return $meta;
    }

    private function formatAmount(string $raw, int $decimals): string
    {
        if (! preg_match('/^-?\d+$/', $raw)) {
            return $raw;
        }
        if ($decimals <= 0) {
            return $raw;
        }
        $negative = str_starts_with($raw, '-');
        $raw = ltrim($raw, '-');
        $raw = str_pad($raw, $decimals + 1, '0', STR_PAD_LEFT);
        $int = substr($raw, 0, -$decimals);
        $frac = rtrim(substr($raw, -$decimals), '0');
        $formatted = $frac === '' ? $int : $int.'.'.$frac;

        return $negative ? '-'.$formatted : $formatted;
    }

    private function addDecimalStrings(string $a, string $b): string
    {
        if (! preg_match('/^-?\d+$/', $a) || ! preg_match('/^-?\d+$/', $b)) {
            return $a;
        }

        return function_exists('bcadd') ? bcadd($a, $b, 0) : (string) ((int) $a + (int) $b);
    }
}
