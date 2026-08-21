<?php

// | KB @CerberRus00 - Nexus Invest Team
namespace Tests\Unit;

use App\Enums\RiskGrade;
use Tests\TestCase;

class RiskGradeTest extends TestCase
{
    public function test_from_score_maps_formula_steps(): void
    {
        $this->assertSame(RiskGrade::Low, RiskGrade::fromScore(0));
        $this->assertSame(RiskGrade::Low, RiskGrade::fromScore(-1));
        $this->assertSame(RiskGrade::Moderate, RiskGrade::fromScore(1));
        $this->assertSame(RiskGrade::Moderate, RiskGrade::fromScore(20));
        $this->assertSame(RiskGrade::Moderate, RiskGrade::fromScore(34));
        $this->assertSame(RiskGrade::Elevated, RiskGrade::fromScore(35));
        $this->assertSame(RiskGrade::Elevated, RiskGrade::fromScore(50));
        $this->assertSame(RiskGrade::Elevated, RiskGrade::fromScore(64));
        $this->assertSame(RiskGrade::High, RiskGrade::fromScore(65));
        $this->assertSame(RiskGrade::High, RiskGrade::fromScore(89));
        $this->assertSame(RiskGrade::Critical, RiskGrade::fromScore(90));
        $this->assertSame(RiskGrade::Critical, RiskGrade::fromScore(100));
    }

    public function test_legend_lists_all_grades(): void
    {
        app()->setLocale('en');

        $legend = RiskGrade::legend();

        $this->assertCount(5, $legend);
        $this->assertSame(['low', 'moderate', 'elevated', 'high', 'critical'], array_column($legend, 'key'));
        $this->assertSame('Low', $legend[0]['label']);
        $this->assertSame('1–34', $legend[1]['range']);
    }
}
