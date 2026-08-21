<?php

// | KB @CerberRus00 - Nexus Invest Team
namespace App\Services\Onchain;

use App\Enums\CheckType;
use App\Enums\CheckStatus;
use App\Enums\CheckVerdict;
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

        if ($this->shouldRefetch(is_array($check->enrichment) ? $check->enrichment : null)) {
            try {
                $check->update(['enrichment' => $this->forAddress(
                    $check->subject,
                    $check->chain_id,
                    $check->type === CheckType::Scan,
                )]);
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

        return $this->promoteOnchainReview($check);
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
        if ($check->verdict === CheckVerdict::Block || $check->verdict === CheckVerdict::Review) {
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
        $trc20Tx = $this->tron->incomingTrc20($address, $limit);
        $trxTx = $this->tron->incomingTrx($address, $limit);
        $tokenMeta = $this->tokenMetaFromTransfers($trc20Tx);

        return [
            'source' => 'trongrid',
            'network' => 'tron',
            'fetched_at' => now()->toIso8601String(),
            'tx_window' => $limit,
            'control' => $this->control($account),
            'balances' => $this->balances($account, $tokenMeta),
            'inflows' => $this->inflows($trxTx, $trc20Tx),
        ];
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
            $this->addInflow($groups, $from, 'TRX', 6, $raw, (string) ($tx['txID'] ?? ''), (int) ($tx['block_timestamp'] ?? 0));
        }

        foreach ($trc20Tx as $tx) {
            $info = is_array($tx['token_info'] ?? null) ? $tx['token_info'] : [];
            $symbol = (string) ($info['symbol'] ?? 'TRC20');
            $decimals = (int) ($info['decimals'] ?? 6);
            $from = (string) ($tx['from'] ?? 'unknown');
            $this->addInflow(
                $groups,
                $from,
                $symbol,
                $decimals,
                (string) ($tx['value'] ?? '0'),
                (string) ($tx['transaction_id'] ?? ''),
                (int) ($tx['block_timestamp'] ?? 0),
                (string) ($info['address'] ?? ''),
                (string) ($info['name'] ?? ''),
            );
        }

        $rows = array_values($groups);
        usort($rows, fn ($a, $b) => ($b['last_at'] ?? '') <=> ($a['last_at'] ?? ''));

        return array_slice($rows, 0, 20);
    }

    /**
     * @param  array<string, array<string, mixed>>  $groups
     */
    private function addInflow(
        array &$groups,
        string $from,
        string $symbol,
        int $decimals,
        string $raw,
        string $txId,
        int $timestampMs,
        ?string $contract = null,
        ?string $name = null,
    ): void {
        $contract = (string) $contract;
        $key = $from.'|'.$symbol.'|'.$contract;
        if (! isset($groups[$key])) {
            $groups[$key] = [
                'from' => $from,
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
