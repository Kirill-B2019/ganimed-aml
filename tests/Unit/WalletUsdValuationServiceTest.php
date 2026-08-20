<?php

// | KB @CerberRus00 - Nexus Invest Team
namespace Tests\Unit;

use App\Enums\CheckType;
use App\Models\Check;
use App\Services\Onchain\WalletUsdValuationService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class WalletUsdValuationServiceTest extends TestCase
{
    public function test_counts_native_and_canonical_only(): void
    {
        Cache::flush();
        Http::fake([
            'https://api.coingecko.com/*' => Http::response([
                'tron' => ['usd' => 0.10],
                'tether' => ['usd' => 1],
                'usd-coin' => ['usd' => 1],
            ]),
        ]);

        $check = new Check([
            'type' => CheckType::Address,
            'enrichment' => [
                'source' => 'trongrid',
                'balances' => [
                    ['symbol' => 'TRX', 'amount' => '100', 'kind' => 'native'],
                    ['symbol' => 'USDT', 'amount' => '25', 'contract' => 'TR7NHqjeKQxGTCi8q8ZY4pL8otSzgjLj6t', 'kind' => 'trc20'],
                    ['symbol' => 'USDT', 'amount' => '9999', 'contract' => 'TYgh8XECsuM9sieDKQxbBjmAFpfVPhTUjL', 'kind' => 'trc20'],
                    ['symbol' => 'ha138 com', 'amount' => '138', 'kind' => 'trc20'],
                ],
            ],
        ]);

        $summary = app(WalletUsdValuationService::class)->summarize($check);

        $this->assertNotNull($summary);
        $this->assertSame(35.0, $summary['total_usd']);
        $this->assertSame('$35.00', $summary['formatted']);
        $this->assertSame(2, $summary['excluded']);
        $this->assertSame('coingecko', $summary['source']);
    }

    public function test_falls_back_when_fx_is_unavailable(): void
    {
        Cache::flush();
        Http::fake([
            'https://api.coingecko.com/*' => Http::response([], 500),
        ]);

        $check = new Check([
            'type' => CheckType::Address,
            'enrichment' => [
                'source' => 'trongrid',
                'balances' => [
                    ['symbol' => 'TRX', 'amount' => '10', 'kind' => 'native'],
                    ['symbol' => 'USDT', 'amount' => '1', 'contract' => 'TR7NHqjeKQxGTCi8q8ZY4pL8otSzgjLj6t'],
                ],
            ],
        ]);

        $summary = app(WalletUsdValuationService::class)->summarize($check);

        $this->assertNotNull($summary);
        $this->assertSame('fallback', $summary['source']);
        $this->assertSame(round(10 * (float) config('onchain.fx_trx_usd') + 1, 2), $summary['total_usd']);
    }
}
