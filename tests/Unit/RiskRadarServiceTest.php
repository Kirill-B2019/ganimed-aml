<?php

// | KB @CerberRus00 - Nexus Invest Team
namespace Tests\Unit;

use App\Enums\CheckType;
use App\Models\Check;
use App\Services\Risk\RiskRadarService;
use Tests\TestCase;

class RiskRadarServiceTest extends TestCase
{
    public function test_clean_address_has_zero_axes(): void
    {
        $check = new Check([
            'type' => CheckType::Address,
            'raw_response' => [
                'sanctioned' => '0',
                'money_laundering' => '0',
                'mixer' => '0',
            ],
        ]);

        $axes = (new RiskRadarService)->axes($check);

        $this->assertCount(8, $axes);
        $this->assertTrue(collect($axes)->every(fn ($axis) => $axis['value'] === 0));
    }

    public function test_sanctioned_axis_is_max(): void
    {
        $check = new Check([
            'type' => CheckType::Address,
            'raw_response' => ['sanctioned' => '1'],
        ]);

        $axes = collect((new RiskRadarService)->axes($check))->keyBy('key');

        $this->assertSame(100, $axes['sanctions']['value']);
        $this->assertSame(0, $axes['mixer']['value']);
    }

    public function test_svg_is_generated(): void
    {
        $check = new Check([
            'type' => CheckType::Address,
            'raw_response' => ['mixer' => '1'],
        ]);
        $radar = new RiskRadarService;
        $svg = $radar->svg($radar->axes($check));

        $this->assertStringContainsString('<svg', $svg);
        $this->assertStringContainsString('polygon', $svg);
    }
}
