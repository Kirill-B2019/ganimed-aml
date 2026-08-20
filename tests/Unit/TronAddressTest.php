<?php

// | KB @CerberRus00 - Nexus Invest Team
namespace Tests\Unit;

use App\Support\TronAddress;
use PHPUnit\Framework\TestCase;

class TronAddressTest extends TestCase
{
    public function test_detects_tron_base58_address(): void
    {
        $this->assertTrue(TronAddress::isTron('TFq8GqCTiJA1PAnCJjtqDMHTRAsZgKNaYk'));
        $this->assertFalse(TronAddress::isTron('0x408e41876cccdc0f92210600ef50372656052a38'));
    }

    public function test_converts_hex_to_base58(): void
    {
        $this->assertSame(
            'TFq8GqCTiJA1PAnCJjtqDMHTRAsZgKNaYk',
            TronAddress::fromHex('4140497af024c1d8ca00848de32d1d3dc4ef652598'),
        );
    }
}
