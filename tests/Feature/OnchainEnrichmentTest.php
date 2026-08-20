<?php

// | KB @CerberRus00 - Nexus Invest Team
namespace Tests\Feature;

use App\Enums\CheckType;
use App\Models\User;
use App\Services\Onchain\OnchainEnrichmentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class OnchainEnrichmentTest extends TestCase
{
    use RefreshDatabase;

    public function test_tron_account_balances_and_inflows(): void
    {
        Http::fake([
            'https://api.trongrid.io/v1/accounts/TFq8GqCTiJA1PAnCJjtqDMHTRAsZgKNaYk/transactions/trc20*' => Http::response([
                'data' => [[
                    'transaction_id' => 'tx-usdt',
                    'from' => 'TM9QC18oJUowYyAiYtE1ZYEvyhPHnzxXXQ',
                    'to' => 'TFq8GqCTiJA1PAnCJjtqDMHTRAsZgKNaYk',
                    'value' => '10000000',
                    'block_timestamp' => 1700000000000,
                    'token_info' => [
                        'symbol' => 'USDT',
                        'address' => 'TR7NHqjeKQxGTCi8q8ZY4pL8otSzgjLj6t',
                        'decimals' => 6,
                        'name' => 'Tether USD',
                    ],
                ]],
            ]),
            'https://api.trongrid.io/v1/accounts/TFq8GqCTiJA1PAnCJjtqDMHTRAsZgKNaYk/transactions?*' => Http::response([
                'data' => [[
                    'txID' => 'tx-trx',
                    'block_timestamp' => 1700000000000,
                    'raw_data' => [
                        'contract' => [[
                            'type' => 'TransferContract',
                            'parameter' => [
                                'value' => [
                                    'amount' => 1500000,
                                    'owner_address' => '41c12276056787e8e6395a040e051ad516cd96898c',
                                    'to_address' => '4140497af024c1d8ca00848de32d1d3dc4ef652598',
                                ],
                            ],
                        ]],
                    ],
                ]],
            ]),
            'https://api.trongrid.io/v1/accounts/TFq8GqCTiJA1PAnCJjtqDMHTRAsZgKNaYk' => Http::response([
                'data' => [[
                    'balance' => 828283956,
                    'trc20' => [
                        ['TR7NHqjeKQxGTCi8q8ZY4pL8otSzgjLj6t' => '25000000'],
                    ],
                    'owner_permission' => [
                        'threshold' => 4,
                        'keys' => [
                            ['address' => 'TFq8GqCTiJA1PAnCJjtqDMHTRAsZgKNaYk', 'weight' => 1],
                            ['address' => 'TCJgrowM26ojHX8jDBSoq8J6qvRs95heBg', 'weight' => 3],
                        ],
                    ],
                ]],
            ]),
        ]);

        $result = app(OnchainEnrichmentService::class)->forAddress(
            'TFq8GqCTiJA1PAnCJjtqDMHTRAsZgKNaYk',
            'tron',
        );

        $this->assertSame('trongrid', $result['source']);
        $this->assertSame('multisig', $result['control']['type']);
        $this->assertSame('828.283956', $result['balances'][0]['amount']);
        $this->assertSame('USDT', $result['balances'][1]['symbol']);
        $this->assertSame('25', $result['balances'][1]['amount']);
        $this->assertTrue(collect($result['inflows'])->contains(
            fn ($row) => $row['symbol'] === 'USDT' && $row['amount'] === '10'
        ));
    }

    public function test_evm_address_is_skipped(): void
    {
        Http::fake();

        $result = app(OnchainEnrichmentService::class)->forAddress(
            '0x408e41876cccdc0f92210600ef50372656052a38',
            '1',
        );

        $this->assertTrue($result['skipped']);
        Http::assertNothingSent();
    }

    public function test_check_card_shows_radar(): void
    {
        Http::fake([
            'https://api.coingecko.com/*' => Http::response([
                'tron' => ['usd' => 0.12],
                'tether' => ['usd' => 1],
                'usd-coin' => ['usd' => 1],
            ]),
        ]);

        $user = User::factory()->create();
        $check = $user->checks()->create([
            'type' => CheckType::Address,
            'subject' => 'TFq8GqCTiJA1PAnCJjtqDMHTRAsZgKNaYk',
            'chain_id' => 'tron',
            'status' => 'completed',
            'verdict' => 'clear',
            'risk_score' => 0,
            'locale' => 'en',
            'raw_response' => ['sanctioned' => '0', 'mixer' => '0'],
            'enrichment' => [
                'source' => 'trongrid',
                'balances' => [
                    ['symbol' => 'TRX', 'amount' => '828.28', 'name' => 'TRON', 'kind' => 'native'],
                    ['symbol' => 'USDT', 'amount' => '10', 'name' => 'Tether USD', 'contract' => 'TR7NHqjeKQxGTCi8q8ZY4pL8otSzgjLj6t', 'kind' => 'trc20'],
                ],
            ],
        ]);

        $this->withSession(['locale' => 'ru'])
            ->actingAs($user)
            ->get(route('checks.show', $check))
            ->assertOk()
            ->assertSee('Состав активов', false)
            ->assertSee('Канонические стейблкоины', false)
            ->assertSee('Состав токенов', false)
            ->assertSee('Радар рисков', false)
            ->assertSee('50%', false)
            ->assertSee('viewBox="0 0 180 180"', false)
            ->assertSee('Баланс кошелька, USD', false)
            ->assertSee('$109.39', false)
            ->assertDontSee('Файл: на проверку', false);
    }

    public function test_onchain_review_promotes_clear_verdict(): void
    {
        Http::fake();

        $user = User::factory()->create();
        $check = $user->checks()->create([
            'type' => CheckType::Address,
            'subject' => 'TFq8GqCTiJA1PAnCJjtqDMHTRAsZgKNaYk',
            'chain_id' => 'tron',
            'status' => 'completed',
            'verdict' => 'clear',
            'risk_score' => 0,
            'locale' => 'ru',
            'flags' => [],
            'raw_response' => ['sanctioned' => '0', 'mixer' => '0'],
            'enrichment' => [
                'source' => 'trongrid',
                'control' => ['type' => 'multisig', 'threshold' => 4],
                'balances' => [
                    ['symbol' => 'TRX', 'amount' => '10', 'kind' => 'native'],
                    ['symbol' => 'USDT', 'amount' => '1', 'contract' => 'TYgh8XECsuM9sieDKQxbBjmAFpfVPhTUjL'],
                ],
            ],
        ]);

        app(OnchainEnrichmentService::class)->fill($check);

        $check->refresh();
        $this->assertSame('review', $check->verdict->value);
        $this->assertGreaterThanOrEqual(20, $check->risk_score);
        $this->assertTrue(collect($check->flags)->contains(fn ($flag) => ($flag['key'] ?? '') === 'onchain_hygiene'));

        $this->withSession(['locale' => 'ru'])
            ->actingAs($user)
            ->get(route('checks.show', $check))
            ->assertOk()
            ->assertSee('Файл: на проверку', false)
            ->assertSee('Ончейн: на проверку', false)
            ->assertSee('GoPlus: Чисто', false)
            ->assertSee('На проверке', false);
    }

    public function test_onchain_review_does_not_demote_block(): void
    {
        $user = User::factory()->create();
        $check = $user->checks()->create([
            'type' => CheckType::Address,
            'subject' => 'TFq8GqCTiJA1PAnCJjtqDMHTRAsZgKNaYk',
            'chain_id' => 'tron',
            'status' => 'completed',
            'verdict' => 'block',
            'risk_score' => 90,
            'locale' => 'ru',
            'flags' => [['key' => 'sanctioned', 'value' => '1', 'severity' => 'block']],
            'raw_response' => ['sanctioned' => '1'],
            'enrichment' => [
                'source' => 'trongrid',
                'control' => ['type' => 'multisig', 'threshold' => 4],
            ],
        ]);

        app(OnchainEnrichmentService::class)->fill($check);

        $check->refresh();
        $this->assertSame('block', $check->verdict->value);
        $this->assertSame(90, $check->risk_score);
    }
}
