<?php

// | KB @CerberRus00 - Nexus Invest Team
namespace Tests\Feature;

use App\Enums\CheckStatus;
use App\Enums\CheckType;
use App\Enums\CheckVerdict;
use App\Jobs\ExpandWalletGraphJob;
use App\Jobs\PollAddressScanJob;
use App\Models\Check;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
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
        Queue::assertNotPushed(PollAddressScanJob::class);
        Queue::assertPushed(ExpandWalletGraphJob::class);
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
            ->assertSee('Второй хоп достраивается', false)
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
            ->assertSee(__('aml.address_placeholder'), false)
            ->assertSee('data-processing', false)
            ->assertSee(__('aml.processing_title'), false)
            ->assertSee(__('aml.deep_window'), false)
            ->assertDontSee(__('aml.tab_scan'), false);
    }

    public function test_report_puts_conclusion_before_flags(): void
    {
        $user = User::factory()->create();
        $check = Check::factory()->create([
            'user_id' => $user->id,
            'status' => CheckStatus::Completed,
            'verdict' => CheckVerdict::Review,
            'risk_score' => 20,
            'raw_response' => [
                'sanctioned' => '0',
                'mixer' => '1',
                'contract_address' => '0',
            ],
            'flags' => [['key' => 'mixer', 'value' => '1', 'severity' => 'review']],
            'enrichment' => ['skipped' => true],
        ]);

        $html = $this->actingAs($user)->get(route('checks.show', $check))->assertOk()->getContent();
        $conclusion = strpos($html, __('aml.conclusion_title'));
        $why = strpos($html, __('aml.why_title'));
        $this->assertNotFalse($conclusion);
        $this->assertNotFalse($why);
        $this->assertLessThan($why, $conclusion);
        $this->assertStringContainsString('mixer', $html);
    }

    public function test_dashboard_latest_respects_date_filter(): void
    {
        $user = User::factory()->create();
        Check::factory()->create([
            'user_id' => $user->id,
            'subject' => 'Toldwallet000000000000000000000000001',
            'verdict' => CheckVerdict::Review,
            'status' => CheckStatus::Completed,
            'created_at' => now()->subDays(40),
        ]);
        Check::factory()->create([
            'user_id' => $user->id,
            'subject' => 'Tnewwallet000000000000000000000000001',
            'verdict' => CheckVerdict::Review,
            'status' => CheckStatus::Completed,
            'created_at' => now(),
        ]);

        $this->actingAs($user)
            ->get('/dashboard?from='.now()->toDateString().'&to='.now()->toDateString())
            ->assertOk()
            ->assertSee('Tnewwallet')
            ->assertDontSee('Toldwallet')
            ->assertSee(__('aml.queue_review'), false);
    }

    public function test_pdf_file_variant_is_compact(): void
    {
        Carbon::setTestNow('2026-08-21 09:33:15');
        $user = User::factory()->create();
        $check = Check::factory()->create([
            'user_id' => $user->id,
            'subject' => 'TU72cTvdkWvoB7xgN5TXFtoXtUuWRuvUTm',
            'status' => CheckStatus::Completed,
            'verdict' => CheckVerdict::Clear,
            'enrichment' => ['skipped' => true],
        ]);

        $pdf = app(\App\Services\Reports\CheckPdfService::class);
        $file = $pdf->html($check, 'file');
        $full = $pdf->html($check, 'full');
        $this->assertStringContainsString(__('aml.conclusion_title'), $file);
        $this->assertStringContainsString(__('aml.why_title'), $file);
        $this->assertStringNotContainsString(__('aml.score_title'), $file);
        $this->assertStringContainsString(__('aml.score_title'), $full);

        $this->actingAs($user)
            ->get(route('checks.pdf', [$check, 'variant' => 'file']))
            ->assertOk()
            ->assertDownload('TU72cTvdkWvoB7xgN5TXFtoXtUuWRuvUTm_2026-08-21_09-33-15_file.pdf');
    }

    public function test_rerun_writes_previous_check_and_delta(): void
    {
        $this->fakeGoPlus();
        $user = User::factory()->create();
        $check = Check::factory()->create([
            'user_id' => $user->id,
            'type' => CheckType::Address,
            'subject' => '0x408e41876cccdc0f92210600ef50372656052a38',
            'chain_id' => 'tron',
            'status' => CheckStatus::Completed,
            'verdict' => CheckVerdict::Clear,
            'risk_score' => 0,
        ]);

        $this->actingAs($user)->post(route('checks.rerun', $check))->assertRedirect();
        $fresh = Check::query()->where('previous_check_id', $check->id)->first();
        $this->assertNotNull($fresh);
        $this->assertSame(CheckVerdict::Block, $fresh->verdict);

        $this->actingAs($user)
            ->get(route('checks.show', $fresh))
            ->assertOk()
            ->assertSee(__('aml.delta_title'), false);
    }

    public function test_history_csv_export_uses_filters(): void
    {
        $user = User::factory()->create(['name' => 'Analyst One']);
        Check::factory()->create([
            'user_id' => $user->id,
            'subject' => 'Texportme000000000000000000000000001',
            'verdict' => CheckVerdict::Clear,
            'status' => CheckStatus::Completed,
        ]);
        Check::factory()->create([
            'user_id' => $user->id,
            'subject' => 'Thideexport0000000000000000000000001',
            'verdict' => CheckVerdict::Block,
            'status' => CheckStatus::Completed,
        ]);

        $csv = $this->actingAs($user)
            ->get('/checks/export?verdict=clear')
            ->assertOk()
            ->streamedContent();

        $this->assertStringContainsString('Texportme', $csv);
        $this->assertStringContainsString('Analyst One', $csv);
        $this->assertStringNotContainsString('Thideexport', $csv);
    }

    public function test_verdict_change_is_written_to_activity_log(): void
    {
        $user = User::factory()->create();
        $check = Check::factory()->create([
            'user_id' => $user->id,
            'status' => CheckStatus::Completed,
            'verdict' => CheckVerdict::Review,
            'risk_score' => 20,
            'flags' => [['key' => 'mixer', 'value' => '1', 'severity' => 'review']],
            'raw_response' => ['mixer' => '1'],
        ]);

        $this->actingAs($user)
            ->patch(route('checks.verdict', $check), [
                'verdict' => 'block',
                'note' => 'file',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('activity_logs', [
            'check_id' => $check->id,
            'action' => 'verdict',
        ]);
    }

    public function test_admin_users_page_renders_and_created_user_can_log_in(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->get(route('users.index'))
            ->assertOk()
            ->assertSee(__('aml.create_user'), false);

        $this->actingAs($admin)
            ->post(route('users.store'), [
                'name' => 'Analyst',
                'email' => 'analyst@localhost',
                'password' => 'Password123!',
                'password_confirmation' => 'Password123!',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('users', [
            'email' => 'analyst@localhost',
            'is_admin' => 0,
        ]);

        $this->post('/logout');

        $this->post('/login', [
            'email' => 'analyst@localhost',
            'password' => 'Password123!',
        ])->assertRedirect(route('dashboard'));
    }

    public function test_non_admin_cannot_open_users(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('users.index'))
            ->assertForbidden();
    }

    public function test_pending_report_shows_processing_waiter(): void
    {
        $user = User::factory()->create();
        $check = Check::factory()->create([
            'user_id' => $user->id,
            'type' => CheckType::Scan,
            'subject' => 'TFq8GqCTiJA1PAnCJjtqDMHTRAsZgKNaYk',
            'status' => CheckStatus::Pending,
            'verdict' => null,
        ]);

        $this->actingAs($user)
            ->get(route('checks.show', $check))
            ->assertOk()
            ->assertSee('checkWaiter', false)
            ->assertSee(__('aml.processing_scan'), false)
            ->assertSee('processing-overlay', false);
    }

    public function test_admin_can_delete_a_check(): void
    {
        $admin = User::factory()->admin()->create();
        $operator = User::factory()->create();
        $check = Check::factory()->create(['user_id' => $operator->id]);

        $this->actingAs($admin)
            ->get(route('checks.show', $check))
            ->assertOk()
            ->assertSee(__('aml.delete_check'), false);

        $this->actingAs($admin)
            ->delete(route('checks.destroy', $check))
            ->assertRedirect(route('checks.index'));

        $this->assertDatabaseMissing('checks', ['id' => $check->id]);
        $this->assertDatabaseHas('activity_logs', [
            'user_id' => $admin->id,
            'action' => 'delete',
        ]);
    }

    public function test_non_admin_cannot_delete_a_check(): void
    {
        $user = User::factory()->create();
        $check = Check::factory()->create(['user_id' => $user->id]);

        $this->actingAs($user)
            ->get(route('checks.show', $check))
            ->assertOk()
            ->assertDontSee(__('aml.delete_check'), false);

        $this->actingAs($user)
            ->delete(route('checks.destroy', $check))
            ->assertForbidden();

        $this->assertDatabaseHas('checks', ['id' => $check->id]);
    }

    public function test_pdf_is_available_for_completed_check(): void
    {
        Carbon::setTestNow('2026-08-21 09:33:15');

        $user = User::factory()->create();
        $check = Check::factory()->create([
            'user_id' => $user->id,
            'subject' => 'TU72cTvdkWvoB7xgN5TXFtoXtUuWRuvUTm',
            'status' => CheckStatus::Completed,
            'verdict' => CheckVerdict::Clear,
        ]);

        $this->actingAs($user)
            ->get(route('checks.pdf', $check))
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf')
            ->assertDownload('TU72cTvdkWvoB7xgN5TXFtoXtUuWRuvUTm_2026-08-21_09-33-15_full.pdf');
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
