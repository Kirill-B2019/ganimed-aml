<?php

// | KB @CerberRus00 - Nexus Invest Team
namespace Tests\Feature;

use App\Enums\CheckStatus;
use App\Enums\CheckType;
use App\Enums\CheckVerdict;
use App\Models\Check;
use App\Models\User;
use App\Services\Onchain\OnchainEnrichmentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CheckOverrideTest extends TestCase
{
    use RefreshDatabase;

    public function test_review_can_be_changed_to_block(): void
    {
        $user = User::factory()->create();
        $check = $this->reviewCheck($user);

        $this->actingAs($user)
            ->patch(route('checks.verdict', $check), [
                'verdict' => 'block',
                'note' => 'mixer confirmed',
            ])
            ->assertRedirect(route('checks.show', $check));

        $check->refresh();
        $this->assertSame(CheckVerdict::Block, $check->verdict);
        $this->assertSame(100, $check->risk_score);
        $this->assertTrue($check->verdictIsLocked());
        $this->assertSame('mixer confirmed', $check->override['note']);
    }

    public function test_clear_cannot_be_overridden(): void
    {
        $user = User::factory()->create();
        $check = Check::factory()->create([
            'user_id' => $user->id,
            'status' => CheckStatus::Completed,
            'verdict' => CheckVerdict::Clear,
            'risk_score' => 0,
        ]);

        $this->actingAs($user)
            ->patch(route('checks.verdict', $check), ['verdict' => 'review'])
            ->assertStatus(422);
    }

    public function test_clear_is_rejected_as_target_verdict(): void
    {
        $user = User::factory()->create();
        $check = $this->reviewCheck($user);

        $this->actingAs($user)
            ->patch(route('checks.verdict', $check), ['verdict' => 'clear'])
            ->assertSessionHasErrors('verdict');
    }

    public function test_other_user_cannot_override(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $check = $this->reviewCheck($owner);

        $this->actingAs($other)
            ->patch(route('checks.verdict', $check), ['verdict' => 'block'])
            ->assertForbidden();
    }

    public function test_lookalike_token_can_be_ignored_but_not_promoted_to_canonical(): void
    {
        $user = User::factory()->create();
        $check = $this->reviewCheck($user, [
            'enrichment' => [
                'source' => 'trongrid',
                'control' => ['type' => 'single'],
                'balances' => [
                    ['symbol' => 'TRX', 'amount' => '10', 'kind' => 'native', 'contract' => null],
                    ['symbol' => 'USDT', 'amount' => '1', 'name' => 'Tether', 'contract' => 'TYgh8XECsuM9sieDKQxbBjmAFpfVPhTUjL'],
                    ['symbol' => 'USDT', 'amount' => '5', 'name' => 'Tether USD', 'contract' => 'TR7NHqjeKQxGTCi8q8ZY4pL8otSzgjLj6t', 'kind' => 'trc20'],
                ],
            ],
        ]);

        $this->actingAs($user)
            ->patch(route('checks.verdict', $check), [
                'verdict' => 'review',
                'tokens' => [
                    'TYgh8XECsuM9sieDKQxbBjmAFpfVPhTUjL' => 'ignore',
                    'TR7NHqjeKQxGTCi8q8ZY4pL8otSzgjLj6t' => 'ignore',
                ],
            ])
            ->assertRedirect();

        $check->refresh();
        $this->assertSame('ignore', $check->tokenOverrides()['TYgh8XECsuM9sieDKQxbBjmAFpfVPhTUjL']);
        $this->assertArrayNotHasKey('TR7NHqjeKQxGTCi8q8ZY4pL8otSzgjLj6t', $check->tokenOverrides());
        $this->assertSame(CheckVerdict::Review, $check->verdict);
        $this->assertGreaterThanOrEqual(20, $check->risk_score);
    }

    public function test_review_can_be_changed_to_manual(): void
    {
        $user = User::factory()->create();
        $check = $this->reviewCheck($user);

        $this->actingAs($user)
            ->patch(route('checks.verdict', $check), [
                'verdict' => 'manual',
                'note' => 'false positive',
            ])
            ->assertRedirect(route('checks.show', $check));

        $check->refresh();
        $this->assertSame(CheckVerdict::Manual, $check->verdict);
        $this->assertSame(0, $check->risk_score);
        $this->assertTrue($check->verdictIsLocked());
        $this->assertTrue($check->verdict->isClearLike());
        $this->assertSame(Check::verdictRank(CheckVerdict::Clear), Check::verdictRank($check->verdict));
        $this->assertTrue($check->canOverrideVerdict());
        $this->assertSame('false positive', $check->override['note']);
        $this->assertSame(0, app(\App\Services\Risk\RiskScoringService::class)->breakdown($check)['total']);
        $this->assertDatabaseHas('activity_logs', [
            'check_id' => $check->id,
            'user_id' => $user->id,
            'action' => 'manual',
        ]);

        $this->actingAs($user)
            ->get(route('checks.show', $check))
            ->assertOk()
            ->assertSee(__('aml.activity_manual'), false)
            ->assertSee('false positive', false)
            ->assertSee($user->name, false);

        $this->actingAs($user)
            ->get(route('activity.index'))
            ->assertOk()
            ->assertSee(__('aml.activity_manual'), false)
            ->assertSee('false positive', false);
    }

    public function test_manual_check_is_counted_as_clear_and_not_queued(): void
    {
        $user = User::factory()->create();
        Check::factory()->create([
            'user_id' => $user->id,
            'subject' => 'Tmanualwallet000000000000000000000001',
            'status' => CheckStatus::Completed,
            'verdict' => CheckVerdict::Manual,
            'risk_score' => 0,
        ]);
        Check::factory()->create([
            'user_id' => $user->id,
            'subject' => 'Treviewwallet000000000000000000000001',
            'status' => CheckStatus::Completed,
            'verdict' => CheckVerdict::Review,
            'risk_score' => 35,
        ]);

        $html = $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk()
            ->getContent();

        $queueStart = strpos($html, __('aml.queue_review'));
        $latestStart = strpos($html, __('aml.latest_checks'));
        $this->assertNotFalse($queueStart);
        $this->assertNotFalse($latestStart);
        $queueHtml = substr($html, $queueStart, $latestStart - $queueStart);
        $this->assertStringContainsString('Treviewwallet', $queueHtml);
        $this->assertStringNotContainsString('Tmanualwallet', $queueHtml);
    }

    public function test_locked_verdict_is_not_promoted_again_on_fill(): void
    {
        $user = User::factory()->create();
        $check = $this->reviewCheck($user);
        $check->update([
            'verdict' => CheckVerdict::Block,
            'risk_score' => 100,
            'override' => [
                'verdict_locked' => true,
                'provider_verdict' => 'review',
                'tokens' => [],
            ],
            'enrichment' => [
                'source' => 'trongrid',
                'control' => ['type' => 'multisig', 'threshold' => 4],
                'balances' => [
                    ['symbol' => 'USDT', 'contract' => 'TYgh8XECsuM9sieDKQxbBjmAFpfVPhTUjL'],
                ],
            ],
        ]);

        app(OnchainEnrichmentService::class)->fill($check->fresh());

        $check->refresh();
        $this->assertSame(CheckVerdict::Block, $check->verdict);
        $this->assertSame(100, $check->risk_score);
    }

    public function test_report_shows_score_formula_and_override_form(): void
    {
        $user = User::factory()->create();
        $check = $this->reviewCheck($user);

        $this->withSession(['locale' => 'ru'])
            ->actingAs($user)
            ->get(route('checks.show', $check))
            ->assertOk()
            ->assertSee('Как считается балл', false)
            ->assertSee('20 + 15', false)
            ->assertSee('Статус файла и токенов', false)
            ->assertSee('value="manual"', false)
            ->assertDontSee('value="clear"', false);
    }

    /**
     * @param  array<string, mixed>  $extra
     */
    private function reviewCheck(User $user, array $extra = []): Check
    {
        return Check::factory()->create(array_merge([
            'user_id' => $user->id,
            'type' => CheckType::Address,
            'subject' => 'TFq8GqCTiJA1PAnCJjtqDMHTRAsZgKNaYk',
            'chain_id' => 'tron',
            'status' => CheckStatus::Completed,
            'verdict' => CheckVerdict::Review,
            'risk_score' => 35,
            'flags' => [
                ['key' => 'mixer', 'value' => '1', 'severity' => 'review'],
            ],
            'raw_response' => ['mixer' => '1', 'sanctioned' => '0'],
            'enrichment' => ['skipped' => true, 'reason' => 'test'],
        ], $extra));
    }
}
