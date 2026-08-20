<?php

// | KB @CerberRus00 - Nexus Invest Team
namespace Tests\Unit;

use App\Enums\CheckType;
use App\Models\Check;
use App\Services\Onchain\AssetNarrativeService;
use Tests\TestCase;

class AssetNarrativeServiceTest extends TestCase
{
    public function test_describes_canonical_lookalike_and_spam(): void
    {
        app()->setLocale('ru');

        $check = new Check([
            'type' => CheckType::Address,
            'enrichment' => [
                'source' => 'trongrid',
                'balances' => [
                    ['symbol' => 'TRX', 'name' => 'TRON', 'amount' => '828.28', 'contract' => null, 'kind' => 'native'],
                    ['symbol' => 'USDT', 'name' => 'Tether USD', 'amount' => '170140.29', 'contract' => 'TR7NHqjeKQxGTCi8q8ZY4pL8otSzgjLj6t', 'kind' => 'trc20'],
                    ['symbol' => 'USDT', 'name' => 'Tether', 'amount' => '3541', 'contract' => 'TYgh8XECsuM9sieDKQxbBjmAFpfVPhTUjL', 'kind' => 'trc20'],
                    ['symbol' => 'UDST', 'name' => 'UDST', 'amount' => '23.2', 'contract' => 'TJCDneQevgfGFkU1rpCxVofksbeiGLhMCi', 'kind' => 'trc20'],
                    ['symbol' => 'ha138 com', 'name' => 'Hash gambling', 'amount' => '138', 'contract' => 'THxYWbzAgzQgQaYi9G4mjeL1tq1hdrZe55', 'kind' => 'trc20'],
                ],
            ],
        ]);

        $text = (new AssetNarrativeService)->describe($check);

        $this->assertNotNull($text);
        $this->assertStringContainsString('Нативный баланс: 828.28 TRX', $text);
        $this->assertStringContainsString('Канонические стейблкоины: 170140.29 USDT', $text);
        $this->assertStringContainsString('похожим тикером', $text);
        $this->assertStringContainsString('3541 USDT', $text);
        $this->assertStringContainsString('спам-типа', $text);
        $this->assertStringContainsString('ha138', $text);
    }

    public function test_pie_chart_groups_token_types(): void
    {
        $check = new Check([
            'type' => CheckType::Address,
            'enrichment' => [
                'balances' => [
                    ['symbol' => 'TRX', 'kind' => 'native'],
                    ['symbol' => 'USDT', 'contract' => 'TR7NHqjeKQxGTCi8q8ZY4pL8otSzgjLj6t'],
                    ['symbol' => 'UDST', 'name' => 'UDST'],
                    ['symbol' => 'ha138 com', 'name' => 'spam'],
                    ['symbol' => 'nasdex.pro', 'name' => 'spam'],
                ],
            ],
        ]);

        $chart = app(\App\Services\Onchain\TokenCompositionChart::class);
        $slices = collect($chart->slices($check))->keyBy('key');

        $this->assertSame(1, $slices['native']['value']);
        $this->assertSame(1, $slices['canonical']['value']);
        $this->assertSame(1, $slices['lookalike']['value']);
        $this->assertSame(2, $slices['noise']['value']);
        $this->assertStringContainsString('<path', $chart->svg($chart->slices($check)));
    }

    public function test_returns_null_when_enrichment_skipped(): void
    {
        $check = new Check([
            'type' => CheckType::Address,
            'enrichment' => ['skipped' => true],
        ]);

        $this->assertNull((new AssetNarrativeService)->describe($check));
    }

    public function test_needs_review_for_multisig_lookalike_and_noise(): void
    {
        $service = new AssetNarrativeService;

        $this->assertTrue($service->needsReview([
            'control' => ['type' => 'multisig'],
            'balances' => [['symbol' => 'TRX', 'kind' => 'native']],
        ]));
        $this->assertTrue($service->needsReview([
            'control' => ['type' => 'single'],
            'balances' => [
                ['symbol' => 'TRX', 'kind' => 'native'],
                ['symbol' => 'USDT', 'name' => 'Tether', 'contract' => 'TYgh8XECsuM9sieDKQxbBjmAFpfVPhTUjL'],
            ],
        ]));
        $this->assertTrue($service->needsReview([
            'balances' => [
                ['symbol' => 'ha138 com', 'name' => 'Hash gambling', 'contract' => 'THxYWbzAgzQgQaYi9G4mjeL1tq1hdrZe55'],
            ],
        ]));
        $this->assertFalse($service->needsReview([
            'control' => ['type' => 'single'],
            'balances' => [
                ['symbol' => 'TRX', 'kind' => 'native'],
                ['symbol' => 'USDT', 'contract' => 'TR7NHqjeKQxGTCi8q8ZY4pL8otSzgjLj6t', 'kind' => 'trc20'],
            ],
        ]));
        $this->assertFalse($service->needsReview(['skipped' => true]));
    }
}
