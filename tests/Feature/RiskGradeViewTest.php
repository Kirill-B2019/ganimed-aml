<?php

// | KB @CerberRus00 - Nexus Invest Team
namespace Tests\Feature;

use App\Enums\CheckStatus;
use App\Enums\CheckType;
use App\Enums\CheckVerdict;
use App\Enums\RiskGrade;
use App\Models\Check;
use App\Models\User;
use App\Services\Reports\CheckPdfService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RiskGradeViewTest extends TestCase
{
    use RefreshDatabase;

    public function test_wallet_card_and_pdf_show_grade_and_legend(): void
    {
        $user = User::factory()->create();
        $check = Check::factory()->create([
            'user_id' => $user->id,
            'type' => CheckType::Address,
            'status' => CheckStatus::Completed,
            'verdict' => CheckVerdict::Review,
            'risk_score' => 50,
            'flags' => [
                ['key' => 'mixer', 'value' => '1', 'severity' => 'review'],
                ['key' => 'phishing_activities', 'value' => '1', 'severity' => 'review'],
            ],
            'enrichment' => ['skipped' => true],
        ]);

        $html = $this->actingAs($user)
            ->get(route('checks.show', $check))
            ->assertOk()
            ->assertSee(__('aml.risk_grades.elevated'), false)
            ->assertSee(__('aml.pill_risk_grade', ['grade' => __('aml.risk_grades.elevated')]), false)
            ->assertSee(__('aml.risk_grade_legend'), false)
            ->assertSee(__('aml.risk_grades.low'), false)
            ->assertSee(__('aml.risk_grades.critical'), false)
            ->assertSee(__('aml.risk_grade_hints.elevated'), false)
            ->assertSee(__('aml.conclusion_risk_grade', [
                'grade' => __('aml.risk_grades.elevated'),
                'range' => __('aml.risk_grade_ranges.elevated'),
                'score' => '50',
            ]), false)
            ->getContent();
        $this->assertStringContainsString('#d97706', $html);
        $this->assertStringContainsString('#fffbeb', $html);

        $pdf = app(CheckPdfService::class)->html($check, 'file');
        $this->assertStringContainsString(__('aml.risk_grades.elevated'), $pdf);
        $this->assertStringContainsString(__('aml.risk_grade_legend'), $pdf);
        $this->assertStringContainsString(__('aml.risk_grade_ranges.low'), $pdf);
        $this->assertStringContainsString(__('aml.risk_grade_ranges.critical'), $pdf);
        $this->assertStringContainsString('#d97706', $pdf);
        $this->assertSame(RiskGrade::Elevated, RiskGrade::fromScore((int) $check->risk_score));
    }

    public function test_clear_wallet_shows_low_grade(): void
    {
        $user = User::factory()->create();
        $check = Check::factory()->create([
            'user_id' => $user->id,
            'type' => CheckType::Address,
            'status' => CheckStatus::Completed,
            'verdict' => CheckVerdict::Clear,
            'risk_score' => 0,
            'enrichment' => ['skipped' => true],
        ]);

        $this->actingAs($user)
            ->get(route('checks.show', $check))
            ->assertOk()
            ->assertSee(__('aml.risk_grades.low'), false)
            ->assertSee(__('aml.risk_grade_legend'), false);
    }

    public function test_token_report_omits_wallet_risk_scale(): void
    {
        $user = User::factory()->create();
        $check = Check::factory()->create([
            'user_id' => $user->id,
            'type' => CheckType::Token,
            'status' => CheckStatus::Completed,
            'verdict' => CheckVerdict::Review,
            'risk_score' => 50,
            'enrichment' => ['skipped' => true],
        ]);

        $this->actingAs($user)
            ->get(route('checks.show', $check))
            ->assertOk()
            ->assertDontSee(__('aml.risk_grade_legend'), false)
            ->assertDontSee(__('aml.pill_risk_grade', ['grade' => __('aml.risk_grades.elevated')]), false);

        $pdf = app(CheckPdfService::class)->html($check, 'file');
        $this->assertStringNotContainsString(__('aml.risk_grade_legend'), $pdf);
    }
}
