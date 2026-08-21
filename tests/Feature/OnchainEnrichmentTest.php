<?php

// | KB @CerberRus00 - Nexus Invest Team
namespace Tests\Feature;

use App\Enums\CheckType;
use App\Enums\CheckStatus;
use App\Jobs\ExpandWalletGraphJob;
use App\Models\Check;
use App\Models\User;
use App\Services\Onchain\OnchainEnrichmentService;
use App\Services\Onchain\WalletGraphChart;
use App\Support\TronAddress;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class OnchainEnrichmentTest extends TestCase
{
    use RefreshDatabase;

    public function test_tron_account_balances_and_inflows(): void
    {
        Http::fake([
            'https://api.trongrid.io/v1/accounts/TFq8GqCTiJA1PAnCJjtqDMHTRAsZgKNaYk/internal-transactions*' => Http::response(['data' => []]),
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
        $this->assertFalse($result['graph']['pending']);
        $this->assertTrue(collect($result['graph']['nodes'])->contains(
            fn ($node) => ($node['id'] ?? '') === 'TM9QC18oJUowYyAiYtE1ZYEvyhPHnzxXXQ'
        ));
        Http::assertNotSent(fn ($request) => str_contains($request->url(), 'TM9QC18oJUowYyAiYtE1ZYEvyhPHnzxXXQ')
            && ! str_contains($request->url(), 'transactions'));
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
                'inflows' => [
                    ['from' => 'TM9QC18oJUowYyAiYtE1ZYEvyhPHnzxXXQ', 'symbol' => 'USDT', 'amount' => '10', 'tx_count' => 1, 'contract' => 'TR7NHqjeKQxGTCi8q8ZY4pL8otSzgjLj6t', 'name' => 'Tether USD'],
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
            ->assertSee('https://tronscan.org/#/address/TFq8GqCTiJA1PAnCJjtqDMHTRAsZgKNaYk', false)
            ->assertSee('https://tronscan.org/#/address/TM9QC18oJUowYyAiYtE1ZYEvyhPHnzxXXQ', false)
            ->assertSee('https://tronscan.org/#/address/TR7NHqjeKQxGTCi8q8ZY4pL8otSzgjLj6t', false)
            ->assertSee('target="_blank"', false)
            ->assertSee('Связи поступлений', false)
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

    public function test_trongrid_retries_account_after_429(): void
    {
        Http::fake([
            'https://api.trongrid.io/v1/accounts/TFq8GqCTiJA1PAnCJjtqDMHTRAsZgKNaYk/internal-transactions*' => Http::response(['data' => []]),
            'https://api.trongrid.io/v1/accounts/TFq8GqCTiJA1PAnCJjtqDMHTRAsZgKNaYk/transactions/trc20*' => Http::response(['data' => []]),
            'https://api.trongrid.io/v1/accounts/TFq8GqCTiJA1PAnCJjtqDMHTRAsZgKNaYk/transactions?*' => Http::response(['data' => []]),
            'https://api.trongrid.io/v1/accounts/TFq8GqCTiJA1PAnCJjtqDMHTRAsZgKNaYk' => Http::sequence()
                ->push(['Error' => 'request rate of (getAccount) exceeded the allowed_rps(1)'], 429)
                ->push([
                    'data' => [[
                        'balance' => 1000000,
                        'trc20' => [],
                        'owner_permission' => [
                            'threshold' => 1,
                            'keys' => [['address' => 'TFq8GqCTiJA1PAnCJjtqDMHTRAsZgKNaYk', 'weight' => 1]],
                        ],
                    ]],
                ]),
        ]);

        $result = app(OnchainEnrichmentService::class)->forAddress(
            'TFq8GqCTiJA1PAnCJjtqDMHTRAsZgKNaYk',
            'tron',
        );

        $this->assertSame('trongrid', $result['source']);
        $this->assertSame('1', $result['balances'][0]['amount']);
        $this->assertArrayNotHasKey('error', $result);
        Http::assertSentCount(7);
    }

    public function test_rate_limit_enrichment_is_refetched_on_fill(): void
    {
        Http::fake([
            'https://api.trongrid.io/v1/accounts/TFq8GqCTiJA1PAnCJjtqDMHTRAsZgKNaYk/internal-transactions*' => Http::response(['data' => []]),
            'https://api.trongrid.io/v1/accounts/TFq8GqCTiJA1PAnCJjtqDMHTRAsZgKNaYk/transactions/trc20*' => Http::response(['data' => []]),
            'https://api.trongrid.io/v1/accounts/TFq8GqCTiJA1PAnCJjtqDMHTRAsZgKNaYk/transactions?*' => Http::response(['data' => []]),
            'https://api.trongrid.io/v1/accounts/TFq8GqCTiJA1PAnCJjtqDMHTRAsZgKNaYk' => Http::response([
                'data' => [[
                    'balance' => 2500000,
                    'trc20' => [],
                    'owner_permission' => ['threshold' => 1, 'keys' => []],
                ]],
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
            'locale' => 'ru',
            'raw_response' => ['sanctioned' => '0'],
            'enrichment' => [
                'source' => 'trongrid',
                'error' => 'HTTP request returned status code 429: {"Error":"allowed_rps(1)"}',
            ],
        ]);

        app(OnchainEnrichmentService::class)->fill($check);
        $check->refresh();

        $this->assertArrayNotHasKey('error', $check->enrichment);
        $this->assertSame('2.5', $check->enrichment['balances'][0]['amount']);
    }

    public function test_enrich_endpoint_loads_missing_onchain_data(): void
    {
        Http::fake([
            'https://api.trongrid.io/v1/accounts/TFq8GqCTiJA1PAnCJjtqDMHTRAsZgKNaYk/internal-transactions*' => Http::response(['data' => []]),
            'https://api.trongrid.io/v1/accounts/TFq8GqCTiJA1PAnCJjtqDMHTRAsZgKNaYk/transactions/trc20*' => Http::response(['data' => []]),
            'https://api.trongrid.io/v1/accounts/TFq8GqCTiJA1PAnCJjtqDMHTRAsZgKNaYk/transactions?*' => Http::response(['data' => []]),
            'https://api.trongrid.io/v1/accounts/TFq8GqCTiJA1PAnCJjtqDMHTRAsZgKNaYk' => Http::response([
                'data' => [[
                    'balance' => 3000000,
                    'trc20' => [],
                    'owner_permission' => ['threshold' => 1, 'keys' => []],
                ]],
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
            'locale' => 'ru',
            'raw_response' => ['sanctioned' => '0'],
        ]);

        $this->actingAs($user)
            ->get(route('checks.show', $check))
            ->assertOk()
            ->assertSee(__('aml.processing_onchain'), false);

        $this->actingAs($user)
            ->postJson(route('checks.enrich', $check))
            ->assertOk()
            ->assertJsonPath('ok', true);

        $check->refresh();
        $this->assertSame('3', $check->enrichment['balances'][0]['amount']);
    }

    public function test_address_fill_does_not_queue_hop2(): void
    {
        Queue::fake();
        Http::fake([
            'https://api.trongrid.io/v1/accounts/TFq8GqCTiJA1PAnCJjtqDMHTRAsZgKNaYk/internal-transactions*' => Http::response(['data' => []]),
            'https://api.trongrid.io/v1/accounts/TFq8GqCTiJA1PAnCJjtqDMHTRAsZgKNaYk/transactions/trc20*' => Http::response([
                'data' => [[
                    'from' => 'TM9QC18oJUowYyAiYtE1ZYEvyhPHnzxXXQ',
                    'to' => 'TFq8GqCTiJA1PAnCJjtqDMHTRAsZgKNaYk',
                    'value' => '1',
                    'token_info' => [
                        'symbol' => 'USDT',
                        'address' => 'TR7NHqjeKQxGTCi8q8ZY4pL8otSzgjLj6t',
                        'decimals' => 6,
                    ],
                ]],
            ]),
            'https://api.trongrid.io/v1/accounts/TFq8GqCTiJA1PAnCJjtqDMHTRAsZgKNaYk/transactions?*' => Http::response(['data' => []]),
            'https://api.trongrid.io/v1/accounts/TFq8GqCTiJA1PAnCJjtqDMHTRAsZgKNaYk' => Http::response([
                'data' => [['balance' => 0, 'trc20' => [], 'owner_permission' => ['threshold' => 1, 'keys' => []]]],
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
            'locale' => 'ru',
            'raw_response' => ['sanctioned' => '0'],
        ]);

        app(OnchainEnrichmentService::class)->fill($check);

        Queue::assertNotPushed(ExpandWalletGraphJob::class);
        Http::assertNotSent(fn ($request) => str_contains($request->url(), '/accounts/TM9QC18oJUowYyAiYtE1ZYEvyhPHnzxXXQ')
            && ! str_contains($request->url(), 'transactions'));
    }

    public function test_expand_graph_marks_neighbor_as_contract(): void
    {
        Http::fake(function ($request) {
            $url = $request->url();
            if (str_contains($url, '/accounts/TM9QC18oJUowYyAiYtE1ZYEvyhPHnzxXXQ') && ! str_contains($url, 'transactions')) {
                return Http::response([
                    'data' => [[
                        'type' => 2,
                        'owner_permission' => ['threshold' => 1, 'keys' => []],
                    ]],
                ]);
            }
            if (str_contains($url, 'transactions/trc20') && str_contains($url, 'TM9QC18')) {
                return Http::response(['data' => []]);
            }

            return Http::response(['data' => [['type' => 0]]], 200);
        });

        $user = User::factory()->create();
        $check = $user->checks()->create([
            'type' => CheckType::Scan,
            'subject' => 'TFq8GqCTiJA1PAnCJjtqDMHTRAsZgKNaYk',
            'chain_id' => 'tron',
            'status' => CheckStatus::Completed,
            'verdict' => 'clear',
            'risk_score' => 0,
            'locale' => 'ru',
            'raw_response' => ['sanctioned' => '0'],
            'enrichment' => [
                'source' => 'trongrid',
                'tx_window' => 50,
                'graph' => [
                    'nodes' => [
                        ['id' => 'TFq8GqCTiJA1PAnCJjtqDMHTRAsZgKNaYk', 'kind' => 'eoa', 'hop' => 0],
                        ['id' => 'TM9QC18oJUowYyAiYtE1ZYEvyhPHnzxXXQ', 'kind' => 'unknown', 'hop' => 1],
                    ],
                    'edges' => [[
                        'from' => 'TM9QC18oJUowYyAiYtE1ZYEvyhPHnzxXXQ',
                        'to' => 'TFq8GqCTiJA1PAnCJjtqDMHTRAsZgKNaYk',
                        'asset' => 'USDT',
                        'count' => 2,
                        'direction' => 'in',
                    ]],
                    'truncated' => false,
                    'pending' => true,
                    'hop2_queued' => true,
                ],
            ],
        ]);

        app(OnchainEnrichmentService::class)->expandGraph($check);
        $check->refresh();

        $node = collect($check->enrichment['graph']['nodes'])->first(
            fn ($row) => ($row['id'] ?? '') === 'TM9QC18oJUowYyAiYtE1ZYEvyhPHnzxXXQ'
        );
        $this->assertSame('contract', $node['kind'] ?? null);
        $this->assertFalse($check->enrichment['graph']['pending']);
    }

    public function test_expand_graph_degrades_on_neighbor_429(): void
    {
        Http::fake(function ($request) {
            $url = $request->url();
            if (str_contains($url, '/accounts/TM9QC18oJUowYyAiYtE1ZYEvyhPHnzxXXQ') && ! str_contains($url, 'transactions')) {
                return Http::response(['Error' => 'allowed_rps(1)'], 429);
            }

            return Http::response(['data' => []], 200);
        });

        $user = User::factory()->create();
        $check = $user->checks()->create([
            'type' => CheckType::Scan,
            'subject' => 'TFq8GqCTiJA1PAnCJjtqDMHTRAsZgKNaYk',
            'chain_id' => 'tron',
            'status' => CheckStatus::Completed,
            'verdict' => 'clear',
            'risk_score' => 0,
            'locale' => 'ru',
            'raw_response' => ['sanctioned' => '0'],
            'enrichment' => [
                'source' => 'trongrid',
                'graph' => [
                    'nodes' => [
                        ['id' => 'TFq8GqCTiJA1PAnCJjtqDMHTRAsZgKNaYk', 'kind' => 'eoa', 'hop' => 0],
                        ['id' => 'TM9QC18oJUowYyAiYtE1ZYEvyhPHnzxXXQ', 'kind' => 'unknown', 'hop' => 1],
                    ],
                    'edges' => [],
                    'truncated' => false,
                    'pending' => true,
                    'hop2_queued' => true,
                ],
            ],
        ]);

        app(OnchainEnrichmentService::class)->expandGraph($check);
        $check->refresh();

        $this->assertTrue($check->enrichment['graph']['truncated']);
        $this->assertFalse($check->enrichment['graph']['pending']);
        $node = collect($check->enrichment['graph']['nodes'])->first(
            fn ($row) => ($row['id'] ?? '') === 'TM9QC18oJUowYyAiYtE1ZYEvyhPHnzxXXQ'
        );
        $this->assertSame('unknown', $node['kind'] ?? null);
        $this->assertArrayNotHasKey('error', $check->enrichment);
    }

    public function test_graph_marks_dust_and_spam_and_links_tronscan(): void
    {
        Http::fake([
            'https://api.trongrid.io/v1/accounts/TFq8GqCTiJA1PAnCJjtqDMHTRAsZgKNaYk/internal-transactions*' => Http::response(['data' => []]),
            'https://api.trongrid.io/v1/accounts/TFq8GqCTiJA1PAnCJjtqDMHTRAsZgKNaYk/transactions/trc20*' => Http::response([
                'data' => [[
                    'from' => 'TYgh8XECsuM9sieDKQxbBjmAFpfVPhTUjL',
                    'to' => 'TFq8GqCTiJA1PAnCJjtqDMHTRAsZgKNaYk',
                    'value' => '3000000',
                    'token_info' => [
                        'symbol' => 'USDT',
                        'address' => 'TYgh8XECsuM9sieDKQxbBjmAFpfVPhTUjL',
                        'decimals' => 6,
                        'name' => 'Tether',
                    ],
                ]],
            ]),
            'https://api.trongrid.io/v1/accounts/TFq8GqCTiJA1PAnCJjtqDMHTRAsZgKNaYk/transactions?*' => Http::response([
                'data' => [[
                    'txID' => 'tx-dust',
                    'block_timestamp' => 1700000000000,
                    'raw_data' => [
                        'contract' => [[
                            'type' => 'TransferContract',
                            'parameter' => [
                                'value' => [
                                    'amount' => 2,
                                    'owner_address' => '41c12276056787e8e6395a040e051ad516cd96898c',
                                    'to_address' => '4140497af024c1d8ca00848de32d1d3dc4ef652598',
                                ],
                            ],
                        ]],
                    ],
                ]],
            ]),
            'https://api.trongrid.io/v1/accounts/TFq8GqCTiJA1PAnCJjtqDMHTRAsZgKNaYk' => Http::response([
                'data' => [['balance' => 0, 'trc20' => [], 'owner_permission' => ['threshold' => 1, 'keys' => []]]],
            ]),
        ]);

        $result = app(OnchainEnrichmentService::class)->forAddress(
            'TFq8GqCTiJA1PAnCJjtqDMHTRAsZgKNaYk',
            'tron',
        );

        $spam = collect($result['graph']['nodes'])->first(
            fn ($node) => ($node['id'] ?? '') === 'TYgh8XECsuM9sieDKQxbBjmAFpfVPhTUjL'
        );
        $this->assertNotNull($spam);
        $this->assertContains('spam', $spam['flags'] ?? []);
        $this->assertGreaterThan(0, (int) ($spam['in_count'] ?? 0));

        $dustEdge = collect($result['graph']['edges'])->first(
            fn ($edge) => ($edge['hygiene'] ?? '') === 'dust'
        );
        $this->assertNotNull($dustEdge);
        $dustNode = collect($result['graph']['nodes'])->first(
            fn ($node) => in_array('dust', $node['flags'] ?? [], true)
        );
        $this->assertNotNull($dustNode);

        $svg = app(WalletGraphChart::class)->svg($result['graph']);
        $this->assertStringContainsString('tronscan.org/#/address/', $svg);
        $this->assertStringContainsString('target="_blank"', $svg);
        $this->assertStringContainsString('#be123c', $svg);
        $this->assertStringContainsString('#d97706', $svg);
        $this->assertStringNotContainsString('USDT×', $svg);
        $this->assertStringContainsString(TronAddress::short((string) $spam['id']), $svg);
        $this->assertStringContainsString(TronAddress::short((string) $dustNode['id']), $svg);

        $peers = app(WalletGraphChart::class)->peers($result['graph']);
        $this->assertTrue(collect($peers)->contains(fn ($row) => in_array('spam', $row['status'] ?? [], true)));
        $this->assertTrue(collect($peers)->contains(fn ($row) => in_array('dust', $row['status'] ?? [], true)));
        $this->assertSame(1, $peers[0]['n'] ?? null);

        $html = view('checks.partials.inflow-graph', [
            'walletGraphSvg' => $svg,
            'walletGraphPeers' => $peers,
            'walletGraphLegend' => app(WalletGraphChart::class)->legend($peers),
            'walletGraph' => $result['graph'],
            'walletGraphPending' => false,
        ])->render();
        $this->assertStringContainsString(__('aml.graph_peers'), $html);
        $this->assertStringContainsString(__('aml.graph_peer'), $html);
        $this->assertStringContainsString('tronscan.org/#/address/', $html);

        Http::assertNotSent(fn ($request) => str_contains($request->url(), 'TYgh8XECsuM9sieDKQxbBjmAFpfVPhTUjL')
            && ! str_contains($request->url(), 'transactions'));
    }

    public function test_graph_keeps_dust_flag_when_trx_sum_exceeds_threshold(): void
    {
        $peerHex = '41c12276056787e8e6395a040e051ad516cd96898c';
        $subjectHex = '4140497af024c1d8ca00848de32d1d3dc4ef652598';
        $trx = function (int $amount) use ($peerHex, $subjectHex) {
            return [
                'txID' => 'tx-'.$amount,
                'block_timestamp' => 1700000000000,
                'raw_data' => [
                    'contract' => [[
                        'type' => 'TransferContract',
                        'parameter' => [
                            'value' => [
                                'amount' => $amount,
                                'owner_address' => $peerHex,
                                'to_address' => $subjectHex,
                            ],
                        ],
                    ]],
                ],
            ];
        };

        Http::fake([
            'https://api.trongrid.io/v1/accounts/TFq8GqCTiJA1PAnCJjtqDMHTRAsZgKNaYk/internal-transactions*' => Http::response(['data' => []]),
            'https://api.trongrid.io/v1/accounts/TFq8GqCTiJA1PAnCJjtqDMHTRAsZgKNaYk/transactions/trc20*' => Http::response(['data' => []]),
            'https://api.trongrid.io/v1/accounts/TFq8GqCTiJA1PAnCJjtqDMHTRAsZgKNaYk/transactions?*' => Http::response([
                'data' => [$trx(2), $trx(1000000)],
            ]),
            'https://api.trongrid.io/v1/accounts/TFq8GqCTiJA1PAnCJjtqDMHTRAsZgKNaYk' => Http::response([
                'data' => [['balance' => 0, 'trc20' => [], 'owner_permission' => ['threshold' => 1, 'keys' => []]]],
            ]),
        ]);

        $result = app(OnchainEnrichmentService::class)->forAddress(
            'TFq8GqCTiJA1PAnCJjtqDMHTRAsZgKNaYk',
            'tron',
        );

        $edge = collect($result['graph']['edges'])->first(
            fn ($row) => ($row['asset'] ?? '') === 'TRX' && (int) ($row['count'] ?? 0) >= 2
        );
        $this->assertNotNull($edge);
        $this->assertSame('trx', $edge['hygiene'] ?? null);
        $this->assertTrue((bool) ($edge['any_dust'] ?? false));

        $dustNode = collect($result['graph']['nodes'])->first(
            fn ($node) => in_array('dust', $node['flags'] ?? [], true) && (int) ($node['hop'] ?? 1) === 1
        );
        $this->assertNotNull($dustNode);

        $svg = app(WalletGraphChart::class)->svg($result['graph']);
        $this->assertStringContainsString('#d97706', $svg);
        $this->assertStringContainsString(TronAddress::short((string) $dustNode['id']), $svg);
    }
}
