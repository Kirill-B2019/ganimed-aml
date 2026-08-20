<?php

// | KB @CerberRus00 - Nexus Invest Team
namespace Tests\Unit;

use App\Enums\CheckType;
use App\Enums\CheckVerdict;
use App\Services\Risk\RiskScoringService;
use PHPUnit\Framework\TestCase;

class RiskScoringServiceTest extends TestCase
{
    public function test_sanctioned_address_is_blocked(): void
    {
        $result = (new RiskScoringService)->score(CheckType::Address, [
            'sanctioned' => '1',
            'money_laundering' => '0',
        ]);

        $this->assertSame(CheckVerdict::Block, $result['verdict']);
        $this->assertSame(100, $result['score']);
    }

    public function test_clean_address_is_clear(): void
    {
        $result = (new RiskScoringService)->score(CheckType::Address, [
            'sanctioned' => '0',
            'money_laundering' => '0',
            'mixer' => '0',
        ]);

        $this->assertSame(CheckVerdict::Clear, $result['verdict']);
        $this->assertSame(0, $result['score']);
    }

    public function test_mixer_address_is_review(): void
    {
        $result = (new RiskScoringService)->score(CheckType::Address, [
            'mixer' => '1',
        ]);

        $this->assertSame(CheckVerdict::Review, $result['verdict']);
    }

    public function test_phishing_site_is_blocked(): void
    {
        $result = (new RiskScoringService)->score(CheckType::Phishing, [
            'phishing_site' => 1,
        ]);

        $this->assertSame(CheckVerdict::Block, $result['verdict']);
    }
}
