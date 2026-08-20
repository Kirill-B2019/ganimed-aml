<?php

// | KB @CerberRus00 - Nexus Invest Team
namespace App\Services\Onchain;

use App\Models\Check;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Throwable;

class WalletUsdValuationService
{
    /**
     * @return array{
     *     total_usd: float,
     *     formatted: string,
     *     counted: list<array<string, mixed>>,
     *     excluded: int,
     *     fx: array<string, float>,
     *     source: string,
     *     as_of: string
     * }|null
     */
    public function summarize(Check $check): ?array
    {
        $onchain = is_array($check->enrichment) ? $check->enrichment : [];
        if ($onchain === [] || ! empty($onchain['skipped']) || ! empty($onchain['error'])) {
            return null;
        }

        $fx = $this->rates();
        $narrative = app(AssetNarrativeService::class);
        $counted = [];
        $excluded = 0;
        $total = 0.0;

        foreach ($onchain['balances'] ?? [] as $row) {
            if (! is_array($row)) {
                continue;
            }
            $kind = $narrative->classify($row, $check);
            $amount = (float) str_replace(',', '', (string) ($row['amount'] ?? '0'));
            $symbol = strtoupper((string) ($row['symbol'] ?? ''));

            if (! in_array($kind, ['native', 'canonical'], true) || $amount <= 0) {
                if ($amount > 0) {
                    $excluded++;
                }
                continue;
            }

            $price = $kind === 'canonical' ? (float) ($fx[strtolower($symbol)] ?? 1.0) : (float) $fx['trx'];
            $usd = round($amount * $price, 2);
            $total += $usd;
            $counted[] = [
                'symbol' => $row['symbol'] ?? $symbol,
                'amount' => $row['amount'] ?? (string) $amount,
                'usd' => $usd,
                'kind' => $kind,
            ];
        }

        return [
            'total_usd' => round($total, 2),
            'formatted' => '$'.number_format($total, 2, '.', ' '),
            'counted' => $counted,
            'excluded' => $excluded,
            'fx' => $fx,
            'source' => (string) ($fx['_source'] ?? 'fallback'),
            'as_of' => (string) ($fx['_as_of'] ?? now()->toIso8601String()),
        ];
    }

    /**
     * @return array{trx: float, usdt: float, usdc: float, _source: string, _as_of: string}
     */
    public function rates(): array
    {
        $ttl = (int) config('onchain.fx_cache_seconds', 900);

        try {
            /** @var array{trx: float, usdt: float, usdc: float, _source: string, _as_of: string} $cached */
            $cached = Cache::remember('onchain.fx.usd', $ttl, function () {
                $payload = Http::timeout((int) config('onchain.fx_timeout', 8))
                    ->acceptJson()
                    ->get((string) config('onchain.fx_url', 'https://api.coingecko.com/api/v3/simple/price'), [
                        'ids' => 'tron,tether,usd-coin',
                        'vs_currencies' => 'usd',
                    ])
                    ->throw()
                    ->json();

                $trx = (float) data_get($payload, 'tron.usd', 0);
                $usdt = (float) data_get($payload, 'tether.usd', 1);
                $usdc = (float) data_get($payload, 'usd-coin.usd', 1);
                if ($trx <= 0) {
                    throw new \RuntimeException('TRX USD rate missing.');
                }

                return [
                    'trx' => $trx,
                    'usdt' => $usdt > 0 ? $usdt : 1.0,
                    'usdc' => $usdc > 0 ? $usdc : 1.0,
                    '_source' => 'coingecko',
                    '_as_of' => now()->toIso8601String(),
                ];
            });

            return $cached;
        } catch (Throwable) {
            return [
                'trx' => (float) config('onchain.fx_trx_usd', 0.12),
                'usdt' => 1.0,
                'usdc' => 1.0,
                '_source' => 'fallback',
                '_as_of' => now()->toIso8601String(),
            ];
        }
    }
}
