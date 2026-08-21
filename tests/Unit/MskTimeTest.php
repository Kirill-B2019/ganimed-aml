<?php

// | KB @CerberRus00 - Nexus Invest Team
namespace Tests\Unit;

use App\Support\MskTime;
use PHPUnit\Framework\TestCase;

class MskTimeTest extends TestCase
{
    public function test_converts_utc_iso_to_moscow(): void
    {
        $this->assertSame('21.08.2026 12:33', MskTime::format('2026-08-21T09:33:15Z'));
        $this->assertNull(MskTime::format(null));
    }
}
