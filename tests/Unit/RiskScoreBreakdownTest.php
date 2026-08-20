<?php

// | KB @CerberRus00 - Nexus Invest Team
namespace Tests\Unit;

use App\Enums\CheckType;
use App\Enums\CheckVerdict;
use App\Models\Check;
use App\Services\Onchain\AssetNarrativeService;
use App\Services\Risk\RiskScoringService;
use Tests\TestCase;

class RiskScoreBreakdownTest extends TestCase
{
    public function test_review_score_is_base_plus_fifteen_per_flag(): void
    {
        app()->setLocale('en');

        $check = new Check([
            'type' => CheckType::Address,
            'verdict' => CheckVerdict::Review,
            'flags' => [
                ['key' => 'mixer', 'value' => '1', 'severity' => 'review'],
            ],
            'enrichment' => ['skipped' => true],
        ]);

        $breakdown = (new RiskScoringService)->breakdown($check);

        $this->assertSame(35, $breakdown['total']);
        $this->assertSame(20, $breakdown['lines'][0]['points']);
        $this->assertSame(15, $breakdown['lines'][1]['points']);
    }

    public function test_block_flag_is_one_hundred_not_summed(): void
    {
        $check = new Check([
            'type' => CheckType::Address,
            'verdict' => CheckVerdict::Block,
            'flags' => [
                ['key' => 'sanctioned', 'value' => '1', 'severity' => 'block'],
                ['key' => 'mixer', 'value' => '1', 'severity' => 'review'],
            ],
        ]);

        $this->assertSame(100, (new RiskScoringService)->breakdown($check)['total']);
    }

    public function test_onchain_hygiene_is_floor_not_plus_fifteen(): void
    {
        $check = new Check([
            'type' => CheckType::Address,
            'verdict' => CheckVerdict::Review,
            'flags' => [
                ['key' => 'onchain_hygiene', 'value' => 'review', 'severity' => 'review'],
            ],
            'enrichment' => [
                'control' => ['type' => 'single'],
                'balances' => [
                    ['symbol' => 'USDT', 'name' => 'Tether', 'contract' => 'TYgh8XECsuM9sieDKQxbBjmAFpfVPhTUjL'],
                ],
            ],
        ]);

        $this->assertSame(20, (new RiskScoringService)->breakdown($check)['total']);
    }

    public function test_ignored_tokens_do_not_keep_onchain_review(): void
    {
        $check = new Check([
            'type' => CheckType::Address,
            'override' => [
                'tokens' => ['TYgh8XECsuM9sieDKQxbBjmAFpfVPhTUjL' => 'ignore'],
            ],
            'enrichment' => [
                'control' => ['type' => 'single'],
                'balances' => [
                    ['symbol' => 'TRX', 'kind' => 'native'],
                    ['symbol' => 'USDT', 'name' => 'Tether', 'contract' => 'TYgh8XECsuM9sieDKQxbBjmAFpfVPhTUjL'],
                ],
            ],
        ]);

        $service = new AssetNarrativeService;
        $this->assertFalse($service->needsReview($check->enrichment, $check));
        $this->assertSame('ignore', $service->classify($check->enrichment['balances'][1], $check));
        $this->assertTrue($service->isStatusLocked($check->enrichment['balances'][0]));
    }
}
