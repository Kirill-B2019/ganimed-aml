<?php

// | KB @CerberRus00 - Nexus Invest Team
namespace Tests\Feature;

use App\Enums\CheckStatus;
use App\Enums\CheckType;
use App\Enums\CheckVerdict;
use App\Jobs\PollAddressScanJob;
use App\Models\Check;
use App\Models\User;
use App\Services\GoPlus\GoPlusClient;
use App\Services\Screening\ScreeningService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ApiCheckTest extends TestCase
{
    use RefreshDatabase;

    public function test_api_requires_token(): void
    {
        $this->postJson('/api/v1/checks/address', [
            'address' => '0x408e41876cccdc0f92210600ef50372656052a38',
        ])->assertUnauthorized();
    }

    public function test_api_page_shows_documentation(): void
    {
        $user = User::factory()->create();

        $this->withSession(['locale' => 'ru'])
            ->actingAs($user)
            ->get(route('tokens.index'))
            ->assertOk()
            ->assertSee('Документация API', false)
            ->assertSee('/api/v1', false)
            ->assertSee('/checks/address', false)
            ->assertSee('Authorization: Bearer', false)
            ->assertSee('Accept-Language', false);

        $this->withSession(['locale' => 'en'])
            ->actingAs($user)
            ->get(route('tokens.index'))
            ->assertOk()
            ->assertSee('API documentation', false)
            ->assertSee('POST', false)
            ->assertSee('/checks/{id}/pdf', false);
    }

    public function test_api_can_create_address_check(): void
    {
        Http::fake([
            'https://api.gopluslabs.io/api/v1/token' => Http::response([
                'code' => 1,
                'result' => ['access_token' => 'test-token', 'expires_in' => 3600],
            ]),
            'https://api.gopluslabs.io/api/v1/address_security/*' => Http::response([
                'code' => 1,
                'result' => ['sanctioned' => '0', 'money_laundering' => '0'],
            ]),
        ]);

        $user = User::factory()->create();
        $token = $user->createToken('tests')->plainTextToken;

        $this->withToken($token)
            ->postJson('/api/v1/checks/address', [
                'address' => '0x408e41876cccdc0f92210600ef50372656052a38',
                'chain_id' => 'tron',
            ])
            ->assertCreated()
            ->assertJsonPath('data.verdict', CheckVerdict::Clear->value);
    }

    public function test_scan_job_completes_when_result_is_ready(): void
    {
        Http::fake([
            'https://api.gopluslabs.io/api/v1/token' => Http::response([
                'code' => 1,
                'result' => ['access_token' => 'test-token', 'expires_in' => 3600],
            ]),
            'https://api.gopluslabs.io/api/v1/address/scan/*' => Http::response([
                'code' => 1,
                'result' => ['request_id' => 'req-1', 'status' => true],
            ]),
            'https://api.gopluslabs.io/api/v1/address/result*' => Http::response([
                'code' => 1,
                'result' => [
                    'request_id' => 'req-1',
                    'chain_id' => '1',
                    'scan_time' => '2026-08-20 00:00:00',
                    'approval_risk' => ['risk_num' => 0],
                    'risky_address_interaction' => ['risk_num' => 2],
                ],
            ]),
        ]);

        $user = User::factory()->create();
        $check = Check::factory()->create([
            'user_id' => $user->id,
            'type' => CheckType::Scan,
            'subject' => '0x408e41876cccdc0f92210600ef50372656052a38',
            'chain_id' => '1',
            'status' => CheckStatus::Pending,
            'verdict' => null,
            'raw_response' => null,
        ]);

        (new PollAddressScanJob($check))->handle(
            app(GoPlusClient::class),
            app(ScreeningService::class),
        );

        $check->refresh();
        $this->assertSame(CheckStatus::Completed, $check->status);
        $this->assertSame(CheckVerdict::Review, $check->verdict);
    }

    public function test_tron_scan_falls_back_to_address_security(): void
    {
        Http::fake([
            'https://api.gopluslabs.io/api/v1/address_security/*' => Http::response([
                'code' => 1,
                'result' => ['sanctioned' => '0', 'money_laundering' => '0', 'mixer' => '0'],
            ]),
            'https://api.trongrid.io/*' => Http::response(['data' => []], 200),
        ]);

        $user = User::factory()->create();
        $check = app(ScreeningService::class)->startScan(
            $user,
            'TFq8GqCTiJA1PAnCJjtqDMHTRAsZgKNaYk',
            'tron',
        );

        $this->assertSame(CheckStatus::Completed, $check->status);
        $this->assertSame(CheckVerdict::Clear, $check->verdict);
        $this->assertSame('address_security_fallback', $check->raw_response['scan_mode'] ?? null);
        Http::assertNotSent(fn ($request) => str_contains($request->url(), '/api/v1/address/scan/'));
        Http::assertNotSent(fn ($request) => str_contains($request->url(), '/api/v1/token'));
    }

    public function test_pending_tron_scan_job_does_not_call_address_scan(): void
    {
        Http::fake([
            'https://api.gopluslabs.io/api/v1/address_security/*' => Http::response([
                'code' => 1,
                'result' => ['sanctioned' => '0', 'money_laundering' => '0', 'mixer' => '0'],
            ]),
            'https://api.trongrid.io/*' => Http::response(['data' => []], 200),
        ]);

        $user = User::factory()->create();
        $check = Check::factory()->create([
            'user_id' => $user->id,
            'type' => CheckType::Scan,
            'subject' => 'TFq8GqCTiJA1PAnCJjtqDMHTRAsZgKNaYk',
            'chain_id' => 'tron',
            'status' => CheckStatus::Pending,
            'verdict' => null,
            'raw_response' => null,
        ]);

        (new PollAddressScanJob($check))->handle(
            app(GoPlusClient::class),
            app(ScreeningService::class),
        );

        $check->refresh();
        $this->assertSame(CheckStatus::Completed, $check->status);
        $this->assertSame('address_security_fallback', $check->raw_response['scan_mode'] ?? null);
        Http::assertNotSent(fn ($request) => str_contains($request->url(), '/api/v1/address/scan/'));
        Http::assertNotSent(fn ($request) => str_contains($request->url(), '/api/v1/token'));
    }
}
