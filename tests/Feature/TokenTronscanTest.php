<?php

// | KB @CerberRus00 - Nexus Invest Team
namespace Tests\Feature;

use App\Enums\CheckStatus;
use App\Enums\CheckType;
use App\Enums\CheckVerdict;
use App\Models\Check;
use App\Models\User;
use App\Services\Onchain\OnchainEnrichmentService;
use App\Services\Reports\CheckPdfService;
use App\Services\Reports\CheckReportPresenter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class TokenTronscanTest extends TestCase
{
    use RefreshDatabase;

    public function test_token_check_shows_tronscan_status_passport_and_pdf_links(): void
    {
        Http::fake([
            'https://api.gopluslabs.io/api/v1/token_security/*' => Http::response([
                'code' => 1,
                'message' => 'ok',
                'result' => [
                    'TR7NHqjeKQxGTCi8q8ZY4pL8otSzgjLj6t' => [
                        'token_name' => 'Tether USD',
                        'token_symbol' => 'USDT',
                        'total_supply' => '1000000',
                        'holder_count' => '42',
                        'owner_address' => 'THPvaUhoh2Qn2y9THCZML3H815hhFhn5YC',
                        'creator_address' => 'THPvaUhoh2Qn2y9THCZML3H815hhFhn5YC',
                        'buy_tax' => '0',
                        'sell_tax' => '0',
                        'is_open_source' => '1',
                        'is_in_dex' => '1',
                        'is_proxy' => '0',
                        'is_mintable' => '0',
                        'is_honeypot' => '0',
                    ],
                ],
            ]),
            'https://apilist.tronscanapi.com/api/contract*' => Http::response([
                'address' => 'TR7NHqjeKQxGTCi8q8ZY4pL8otSzgjLj6t',
                'name' => 'TetherToken',
                'verify_status' => 2,
                'vip' => true,
                'tag1' => 'USDT Token',
                'date_created' => 1557748800000,
                'trxCount' => 99,
                'creator' => ['address' => 'THPvaUhoh2Qn2y9THCZML3H815hhFhn5YC'],
            ]),
        ]);

        $user = User::factory()->create();
        app()->setLocale('ru');

        $this->actingAs($user)
            ->post(route('checks.token'), [
                'contract' => 'TR7NHqjeKQxGTCi8q8ZY4pL8otSzgjLj6t',
                'chain_id' => 'tron',
            ])
            ->assertRedirect();

        $check = Check::query()->where('type', CheckType::Token)->first();
        $this->assertNotNull($check);
        $this->assertSame(CheckStatus::Completed, $check->status);
        $this->assertSame('tronscan', $check->enrichment['source'] ?? null);
        $this->assertTrue($check->enrichment['contract']['verified'] ?? false);

        $html = $this->actingAs($user)
            ->get(route('checks.show', $check))
            ->assertOk()
            ->assertSee(__('aml.tronscan_verified'), false)
            ->assertSee('TetherToken', false)
            ->assertSee('USDT Token', false)
            ->assertSee(__('aml.pill_canonical_usdt'), false)
            ->assertSee(__('aml.pill_tronscan_verified'), false)
            ->assertSee('https://tronscan.org/#/contract/TR7NHqjeKQxGTCi8q8ZY4pL8otSzgjLj6t', false)
            ->assertSee('https://tronscan.org/#/token20/TR7NHqjeKQxGTCi8q8ZY4pL8otSzgjLj6t', false)
            ->assertSee('Tether USD', false)
            ->assertSee(__('aml.pdf_file'), false)
            ->assertDontSee(__('aml.pdf_full'), false)
            ->assertDontSee(__('aml.radar_title'), false)
            ->getContent();

        $this->assertStringContainsString('GoPlus · Tronscan', $html);

        $pdf = app(CheckPdfService::class)->html($check, 'full');
        $this->assertStringContainsString(__('aml.tronscan_verified'), $pdf);
        $this->assertStringContainsString('https://tronscan.org/#/contract/TR7NHqjeKQxGTCi8q8ZY4pL8otSzgjLj6t', $pdf);
        $this->assertStringContainsString('https://tronscan.org/#/token20/TR7NHqjeKQxGTCi8q8ZY4pL8otSzgjLj6t', $pdf);
        $this->assertStringContainsString(__('aml.token_passport'), $pdf);
        $this->assertSame(CheckPdfService::VARIANT_FILE, app(CheckPdfService::class)->normalize('full', $check));

        $file = app(CheckPdfService::class)->html($check, 'file');
        $this->assertStringContainsString(__('aml.tronscan_verified'), $file);
    }

    public function test_tronscan_failure_keeps_goplus_report(): void
    {
        Http::fake([
            'https://api.gopluslabs.io/api/v1/token_security/*' => Http::response([
                'code' => 1,
                'message' => 'ok',
                'result' => [
                    'TYgh8XECsuM9sieDKQxbBjmAFpfVPhTUjL' => [
                        'token_name' => 'Lookalike',
                        'token_symbol' => 'USDT',
                        'is_honeypot' => '0',
                        'is_mintable' => '1',
                    ],
                ],
            ]),
            'https://apilist.tronscanapi.com/api/contract*' => Http::response('unavailable', 503),
        ]);

        $user = User::factory()->create();
        app()->setLocale('ru');

        $this->actingAs($user)
            ->post(route('checks.token'), [
                'contract' => 'TYgh8XECsuM9sieDKQxbBjmAFpfVPhTUjL',
                'chain_id' => 'tron',
            ])
            ->assertRedirect();

        $check = Check::query()->where('type', CheckType::Token)->first();
        $this->assertNotNull($check);
        $this->assertSame(CheckStatus::Completed, $check->status);
        $this->assertSame(CheckVerdict::Review, $check->verdict);
        $this->assertNotEmpty($check->enrichment['error'] ?? null);

        $this->actingAs($user)
            ->get(route('checks.show', $check))
            ->assertOk()
            ->assertSee(__('aml.tronscan_error'), false)
            ->assertSee('Lookalike', false)
            ->assertSee(__('aml.flags.is_mintable'), false)
            ->assertDontSee(__('aml.pill_canonical_usdt'), false);
    }

    public function test_wallet_fill_does_not_call_tronscan(): void
    {
        Http::fake([
            'https://api.trongrid.io/*' => Http::response([
                'data' => [['balance' => 0, 'trc20' => [], 'owner_permission' => ['threshold' => 1, 'keys' => []]]],
            ]),
            'https://apilist.tronscanapi.com/*' => Http::response(['should' => 'not-run']),
        ]);

        $user = User::factory()->create();
        $check = $user->checks()->create([
            'type' => CheckType::Address,
            'subject' => 'TFq8GqCTiJA1PAnCJjtqDMHTRAsZgKNaYk',
            'chain_id' => 'tron',
            'status' => CheckStatus::Completed,
            'verdict' => CheckVerdict::Clear,
            'risk_score' => 0,
            'raw_response' => ['sanctioned' => '0'],
        ]);

        app(OnchainEnrichmentService::class)->fill($check);

        Http::assertNotSent(fn ($request) => str_contains($request->url(), 'tronscanapi.com'));
        $this->assertSame('trongrid', $check->refresh()->enrichment['source'] ?? null);
    }

    public function test_presenter_marks_unverified_contract(): void
    {
        $user = User::factory()->create();
        $check = $user->checks()->create([
            'type' => CheckType::Token,
            'subject' => 'TYgh8XECsuM9sieDKQxbBjmAFpfVPhTUjL',
            'chain_id' => 'tron',
            'status' => CheckStatus::Completed,
            'verdict' => CheckVerdict::Clear,
            'raw_response' => [
                'TYgh8XECsuM9sieDKQxbBjmAFpfVPhTUjL' => [
                    'token_name' => 'Spam',
                    'is_open_source' => '0',
                ],
            ],
            'enrichment' => [
                'source' => 'tronscan',
                'fetched_at' => now()->toIso8601String(),
                'contract' => [
                    'name' => 'SpamToken',
                    'verified' => false,
                    'vip' => false,
                    'tag' => '',
                    'creator' => '',
                    'created_at' => null,
                    'trx_count' => 0,
                ],
            ],
        ]);

        app()->setLocale('ru');
        $data = app(CheckReportPresenter::class)->data($check);
        $labels = collect($data['pills'])->pluck('label');
        $this->assertTrue($labels->contains(__('aml.pill_tronscan_unverified')));
        $this->assertFalse($labels->contains(__('aml.pill_canonical_usdt')));
        $this->assertTrue(collect($data['tronscanContract'])->contains(
            fn ($row) => ($row['value'] ?? '') === __('aml.tronscan_unverified')
        ));
    }
}
