<?php

// | KB @CerberRus00 - Nexus Invest Team
namespace Tests\Feature;

use App\Enums\CheckStatus;
use App\Enums\CheckType;
use App\Enums\CheckVerdict;
use App\Jobs\PollAddressScanJob;
use App\Models\Check;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class CheckScreeningTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_is_redirected_from_dashboard(): void
    {
        $this->get('/dashboard')->assertRedirect('/login');
    }

    public function test_user_can_run_address_check(): void
    {
        $this->fakeGoPlus();
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post('/checks/address', [
            'address' => '0x408e41876cccdc0f92210600ef50372656052a38',
            'chain_id' => 'tron',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('checks', [
            'subject' => '0x408e41876cccdc0f92210600ef50372656052a38',
            'chain_id' => 'tron',
            'status' => CheckStatus::Completed->value,
            'verdict' => CheckVerdict::Block->value,
        ]);
    }

    public function test_tron_deep_scan_completes_without_queue(): void
    {
        Queue::fake();
        Http::fake([
            'https://api.gopluslabs.io/api/v1/address_security/*' => Http::response([
                'code' => 1,
                'message' => 'ok',
                'result' => [
                    'sanctioned' => '0',
                    'money_laundering' => '0',
                    'mixer' => '0',
                    'contract_address' => '0',
                ],
            ]),
            'https://api.trongrid.io/*' => Http::response(['data' => []], 200),
        ]);

        $user = User::factory()->create();

        $response = $this->actingAs($user)->post('/checks/scan', [
            'address' => 'TFq8GqCTiJA1PAnCJjtqDMHTRAsZgKNaYk',
            'chain_id' => 'tron',
        ]);

        $response->assertRedirect();
        Queue::assertNothingPushed();
        Queue::assertNotPushed(PollAddressScanJob::class);
        $this->assertDatabaseHas('checks', [
            'subject' => 'TFq8GqCTiJA1PAnCJjtqDMHTRAsZgKNaYk',
            'type' => CheckType::Scan->value,
            'status' => CheckStatus::Completed->value,
            'verdict' => CheckVerdict::Clear->value,
        ]);
        Http::assertNotSent(fn ($request) => str_contains($request->url(), '/api/v1/address/scan/'));
        Http::assertNotSent(fn ($request) => str_contains($request->url(), '/api/v1/token'));

        $check = Check::query()->where('subject', 'TFq8GqCTiJA1PAnCJjtqDMHTRAsZgKNaYk')->first();
        $this->assertNotNull($check);
        $this->withSession(['locale' => 'ru'])
            ->actingAs($user)
            ->get(route('checks.show', $check))
            ->assertOk()
            ->assertSee('Глубокий скан Tron: Address Security + ончейн', false)
            ->assertDontSee('Deep scan is running', false)
            ->assertDontSee('Глубокое сканирование выполняется', false);
    }

    public function test_ethereum_chain_is_rejected(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post('/checks/address', [
            'address' => '0x408e41876cccdc0f92210600ef50372656052a38',
            'chain_id' => '1',
        ])->assertSessionHasErrors('chain_id');
    }

    public function test_history_can_be_filtered_by_date(): void
    {
        $user = User::factory()->create();
        Check::factory()->create([
            'user_id' => $user->id,
            'subject' => '0xoldaddress0000000000000000000000000001',
            'created_at' => now()->subDays(10),
        ]);
        Check::factory()->create([
            'user_id' => $user->id,
            'subject' => '0xnewaddress0000000000000000000000000001',
            'created_at' => now(),
        ]);

        $this->actingAs($user)
            ->get('/checks?from='.now()->toDateString().'&to='.now()->toDateString())
            ->assertOk()
            ->assertSee('0xnewaddress')
            ->assertDontSee('0xoldaddress');
    }

    public function test_history_can_be_filtered_by_status(): void
    {
        $user = User::factory()->create();
        Check::factory()->create([
            'user_id' => $user->id,
            'subject' => 'Tpendingwallet000000000000000000000001',
            'status' => CheckStatus::Pending,
        ]);
        Check::factory()->create([
            'user_id' => $user->id,
            'subject' => 'Tcompletedwallet0000000000000000000001',
            'status' => CheckStatus::Completed,
        ]);

        $this->actingAs($user)
            ->get('/checks?status=pending')
            ->assertOk()
            ->assertSee('Tpendingwallet')
            ->assertDontSee('Tcompletedwallet');
    }

    public function test_dashboard_and_create_form_are_usable(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee(__('aml.new_check'), false)
            ->assertSee('/checks?verdict=review', false);

        $this->actingAs($user)
            ->get(route('checks.create'))
            ->assertOk()
            ->assertSee(__('aml.tab_hint_address'), false)
            ->assertSee(__('aml.address_placeholder'), false);
    }

    public function test_pdf_is_available_for_completed_check(): void
    {
        $user = User::factory()->create();
        $check = Check::factory()->create([
            'user_id' => $user->id,
            'status' => CheckStatus::Completed,
            'verdict' => CheckVerdict::Clear,
        ]);

        $this->actingAs($user)
            ->get(route('checks.pdf', $check))
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');
    }

    public function test_pdf_follows_active_ui_language_not_check_locale(): void
    {
        $user = User::factory()->create();
        $check = Check::factory()->create([
            'user_id' => $user->id,
            'status' => CheckStatus::Completed,
            'verdict' => CheckVerdict::Clear,
            'locale' => 'ru',
            'enrichment' => ['skipped' => true],
        ]);

        $pdf = app(\App\Services\Reports\CheckPdfService::class);

        app()->setLocale('en');
        $html = $pdf->html($check);
        $this->assertStringContainsString('How to read this report', $html);
        $this->assertStringContainsString('File conclusion', $html);
        $this->assertStringNotContainsString('Как читать этот отчёт', $html);
        $this->assertStringNotContainsString('Вывод для файла', $html);

        $this->withSession(['locale' => 'en'])
            ->actingAs($user)
            ->get(route('checks.pdf', $check))
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');

        app()->setLocale('ru');
        $html = $pdf->html($check);
        $this->assertStringContainsString('Как читать этот отчёт', $html);
        $this->assertStringContainsString('Вывод для файла', $html);
        $this->assertStringNotContainsString('How to read this report', $html);
    }

    public function test_token_pdf_uses_token_layout(): void
    {
        $user = User::factory()->create();
        $check = Check::factory()->create([
            'user_id' => $user->id,
            'type' => \App\Enums\CheckType::Token,
            'status' => CheckStatus::Completed,
            'verdict' => CheckVerdict::Clear,
            'raw_response' => ['is_honeypot' => '0', 'is_mintable' => '0'],
            'flags' => [['key' => 'is_honeypot', 'value' => '0']],
        ]);

        $data = app(\App\Services\Reports\CheckReportPresenter::class)->data($check, true);

        $this->assertFalse($data['showRadar']);
        $this->assertFalse($data['showOnchain']);
        $this->assertNull($data['usdSummary']);
        $this->assertSame(__('aml.report_title_token'), $data['reportTitle']);
        $this->assertSame(__('aml.reading_token'), $data['readingNote']);

        $this->actingAs($user)
            ->get(route('checks.pdf', $check))
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');
    }

    private function fakeGoPlus(): void
    {
        Http::fake([
            'https://api.gopluslabs.io/api/v1/token' => Http::response([
                'code' => 1,
                'message' => 'ok',
                'result' => ['access_token' => 'test-token', 'expires_in' => 3600],
            ]),
            'https://api.gopluslabs.io/api/v1/address_security/*' => Http::response([
                'code' => 1,
                'message' => 'ok',
                'result' => [
                    'sanctioned' => '1',
                    'money_laundering' => '0',
                    'mixer' => '0',
                ],
            ]),
        ]);
    }
}
