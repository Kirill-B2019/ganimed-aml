<?php

// | KB @CerberRus00 - Nexus Invest Team
namespace Tests\Feature;

use App\Services\GoPlus\GoPlusClient;
use App\Services\GoPlus\GoPlusException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class GoPlusClientTest extends TestCase
{
    use RefreshDatabase;

    public function test_address_security_does_not_require_token(): void
    {
        Http::fake([
            'https://api.gopluslabs.io/api/v1/address_security/*' => Http::response([
                'code' => 1,
                'message' => 'ok',
                'result' => [
                    'sanctioned' => '1',
                    'money_laundering' => '0',
                ],
            ]),
        ]);

        $result = app(GoPlusClient::class)->addressSecurity(
            '0x408e41876cccdc0f92210600ef50372656052a38',
            '1',
        );

        $this->assertSame('1', $result['sanctioned']);

        Http::assertNotSent(fn ($request) => $request->url() === 'https://api.gopluslabs.io/api/v1/token');
        Http::assertSent(fn ($request) => str_contains($request->url(), '/api/v1/address_security/')
            && ! $request->hasHeader('Authorization'));
    }

    public function test_address_scan_uses_cached_token(): void
    {
        Http::fake([
            'https://api.gopluslabs.io/api/v1/token' => Http::response([
                'code' => 1,
                'message' => 'ok',
                'result' => ['access_token' => 'test-token', 'expires_in' => 3600],
            ]),
            'https://api.gopluslabs.io/api/v1/address/scan/*' => Http::response([
                'code' => 1,
                'message' => 'ok',
                'result' => ['request_id' => 'req-1', 'status' => true],
            ]),
        ]);

        $result = app(GoPlusClient::class)->startAddressScan(
            '1',
            '0x408e41876cccdc0f92210600ef50372656052a38',
        );

        $this->assertSame('req-1', $result['request_id']);

        Http::assertSent(fn ($request) => $request->url() === 'https://api.gopluslabs.io/api/v1/token'
            && $request->method() === 'POST');

        Http::assertSent(fn ($request) => str_contains($request->url(), '/api/v1/address/scan/')
            && $request->header('Authorization') === ['test-token']);
    }

    public function test_address_scan_is_not_called_for_tron(): void
    {
        Http::fake();

        try {
            app(GoPlusClient::class)->startAddressScan(
                'tron',
                'TFq8GqCTiJA1PAnCJjtqDMHTRAsZgKNaYk',
            );
            $this->fail('Expected GoPlusException for Tron Address Scan.');
        } catch (GoPlusException $e) {
            $this->assertSame(2018, $e->getCode());
        }

        Http::assertNothingSent();
    }
}
