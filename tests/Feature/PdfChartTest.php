<?php

// | KB @CerberRus00 - Nexus Invest Team
namespace Tests\Feature;

use App\Enums\CheckStatus;
use App\Enums\CheckType;
use App\Enums\CheckVerdict;
use App\Models\Check;
use App\Models\User;
use App\Services\Reports\CheckReportPresenter;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class PdfChartTest extends TestCase
{
    use RefreshDatabase;

    public function test_pdf_charts_render_shares_and_do_not_count_lookalike_usdt_as_stable(): void
    {
        Http::fake();
        app()->setLocale('ru');

        $user = User::factory()->create();
        $check = $user->checks()->create([
            'type' => CheckType::Address,
            'subject' => 'TFq8GqCTiJA1PAnCJjtqDMHTRAsZgKNaYk',
            'chain_id' => 'tron',
            'status' => CheckStatus::Completed,
            'verdict' => CheckVerdict::Review,
            'risk_score' => 20,
            'locale' => 'ru',
            'flags' => [],
            'raw_response' => [
                'sanctioned' => '0',
                'mixer' => '1',
                'phishing_activities' => '1',
                'contract_address' => '0',
            ],
            'enrichment' => [
                'source' => 'trongrid',
                'control' => ['type' => 'multisig', 'threshold' => 4],
                'balances' => [
                    ['symbol' => 'TRX', 'amount' => '828.28', 'kind' => 'native', 'name' => 'TRON'],
                    ['symbol' => 'USDT', 'amount' => '170140', 'kind' => 'trc20', 'contract' => 'TR7NHqjeKQxGTCi8q8ZY4pL8otSzgjLj6t', 'name' => 'Tether USD'],
                    ['symbol' => 'USDT', 'amount' => '3541', 'kind' => 'trc20', 'contract' => 'TYgh8XECsuM9sieDKQxbBjmAFpfVPhTUjL', 'name' => 'Tether'],
                    ['symbol' => 'ha138 com', 'amount' => '138', 'kind' => 'trc20', 'contract' => 'THxYWbzAgzQgQaYi9G4mjeL1tq1hdrZe55', 'name' => 'spam'],
                    ['symbol' => 'nasdex.pro', 'amount' => '2040', 'kind' => 'trc20', 'contract' => 'TDWLM3CMxrE6aE'],
                ],
                'inflows' => [
                    ['from' => 'TXRXj1WQ', 'symbol' => 'TRX', 'amount' => '15.1', 'tx_count' => 7],
                    ['from' => 'TDust', 'symbol' => 'TRX', 'amount' => '0.000002', 'tx_count' => 7],
                    ['from' => 'TM9QC18', 'symbol' => 'USDT', 'amount' => '10', 'tx_count' => 2, 'contract' => 'TR7NHqjeKQxGTCi8q8ZY4pL8otSzgjLj6t', 'name' => 'Tether USD'],
                    ['from' => 'TFake', 'symbol' => 'USDT', 'amount' => '3', 'tx_count' => 3, 'contract' => 'TYgh8XECsuM9sieDKQxbBjmAFpfVPhTUjL', 'name' => 'Tether'],
                ],
            ],
        ]);

        $data = app(CheckReportPresenter::class)->data($check, true);
        $pie = collect($data['tokenPieSlices'])->keyBy('key');
        $bars = collect($data['inflowBars'])->keyBy('key');

        $this->assertSame(20, $pie['native']['pct']);
        $this->assertSame(20, $pie['canonical']['pct']);
        $this->assertSame(20, $pie['lookalike']['pct']);
        $this->assertSame(40, $pie['noise']['pct']);
        $this->assertSame(100, collect($data['tokenPieSlices'])->sum('pct'));

        $this->assertSame(2, $bars['stable']['value']);
        $this->assertSame(3, $bars['spam']['value']);
        $this->assertSame(7, $bars['trx']['value']);
        $this->assertSame(7, $bars['dust']['value']);

        $html = view('reports.check', $data)->render();
        $this->assertStringContainsString('class="stackbar"', $html);
        $this->assertStringContainsString('class="hbar"', $html);
        $this->assertStringContainsString('class="sheet"', $html);
        $this->assertStringContainsString('table-layout: fixed', $html);
        $this->assertStringContainsString('word-break: break-all', $html);
        $this->assertStringContainsString('<p class="muted">', $html);
        $this->assertStringContainsString('<table class="pills">', $html);
        $this->assertStringContainsString(__('aml.pill_eoa'), $html);
        $this->assertStringNotContainsString('aml.pill_eoa', $html);
        $this->assertLessThan(
            strpos($html, '<table class="pills">'),
            strpos($html, '<p class="muted">'),
        );
        $this->assertStringContainsString('class="hbar"', $html);
        $this->assertStringContainsString('width: 40%', $html);
        $this->assertStringContainsString('width: 100%', $html);
        $this->assertStringContainsString('2 · 40%', $html);
        $this->assertStringNotContainsString('<i style="width:', $html);
        $this->assertStringContainsString(__('aml.wallet_graph'), $html);
        $this->assertStringContainsString(__('aml.inflows'), $html);
        $this->assertStringContainsString(__('aml.outflows'), $html);
        $this->assertStringContainsString(__('aml.inflow_from'), $html);

        $pdf = Pdf::loadHTML($html)->setPaper('a4')->setOption('defaultFont', 'DejaVu Sans')->output();
        $decoded = $this->decodedPdf($pdf);

        $this->assertNotSame('', $decoded);
        $this->assertGreaterThan(20, preg_match_all('/[0-9.]+ [0-9.]+ [0-9.]+ [0-9.]+ re/', $decoded));
        $this->assertMatchesRegularExpression('/0\.05[89]\d* 0\.46[23]\d* 0\.43[01]\d* rg/', $decoded);
        $this->assertMatchesRegularExpression('/0\.74[45]\d* 0\.07[01]\d* 0\.23[45]\d* rg/', $decoded);
    }

    public function test_file_pdf_omits_inflow_table_and_keeps_conclusion_first(): void
    {
        Http::fake();
        app()->setLocale('ru');

        $user = User::factory()->create();
        $check = $user->checks()->create([
            'type' => CheckType::Address,
            'subject' => 'TFq8GqCTiJA1PAnCJjtqDMHTRAsZgKNaYk',
            'chain_id' => 'tron',
            'status' => CheckStatus::Completed,
            'verdict' => CheckVerdict::Review,
            'risk_score' => 20,
            'locale' => 'ru',
            'flags' => [['key' => 'mixer', 'value' => '1', 'severity' => 'review']],
            'raw_response' => ['mixer' => '1', 'sanctioned' => '0', 'contract_address' => '0'],
            'enrichment' => [
                'source' => 'trongrid',
                'control' => ['type' => 'single'],
                'balances' => [
                    ['symbol' => 'TRX', 'amount' => '1', 'kind' => 'native', 'name' => 'TRON'],
                ],
                'inflows' => [
                    ['from' => 'TXRXj1WQ', 'symbol' => 'TRX', 'amount' => '15.1', 'tx_count' => 7],
                ],
            ],
        ]);

        $file = view('reports.check', app(CheckReportPresenter::class)->data($check, true, true))->render();
        $conclusion = strpos($file, 'Вывод для файла');
        $why = strpos($file, 'Почему такой вердикт');
        $this->assertNotFalse($conclusion);
        $this->assertNotFalse($why);
        $this->assertLessThan($why, $conclusion);
        $this->assertStringNotContainsString('class="stackbar"', $file);
        $this->assertStringNotContainsString(__('aml.inflows'), $file);
        $this->assertStringNotContainsString(__('aml.outflows'), $file);
        $this->assertStringNotContainsString(__('aml.wallet_graph'), $file);
    }

    public function test_full_pdf_renders_graph_inflows_and_outflows(): void
    {
        Http::fake();
        app()->setLocale('ru');

        $subject = 'TFq8GqCTiJA1PAnCJjtqDMHTRAsZgKNaYk';
        $peer = 'TYgh8XECsuM9sieDKQxbBjmAFpfVPhTUjL';
        $user = User::factory()->create();
        $check = $user->checks()->create([
            'type' => CheckType::Address,
            'subject' => $subject,
            'chain_id' => 'tron',
            'status' => CheckStatus::Completed,
            'verdict' => CheckVerdict::Review,
            'risk_score' => 20,
            'locale' => 'ru',
            'flags' => [],
            'raw_response' => ['sanctioned' => '0', 'contract_address' => '0'],
            'enrichment' => [
                'source' => 'trongrid',
                'control' => ['type' => 'single'],
                'balances' => [
                    ['symbol' => 'TRX', 'amount' => '1', 'kind' => 'native', 'name' => 'TRON'],
                ],
                'inflows' => [
                    ['from' => $peer, 'symbol' => 'USDT', 'amount' => '3', 'tx_count' => 3, 'contract' => $peer, 'name' => 'Tether'],
                ],
                'outflows' => [
                    ['to' => $peer, 'symbol' => 'TRX', 'amount' => '2.5', 'tx_count' => 1],
                ],
                'graph' => [
                    'nodes' => [
                        ['id' => $subject, 'kind' => 'subject', 'hop' => 0],
                        ['id' => $peer, 'kind' => 'token', 'hop' => 1, 'flags' => ['spam'], 'in_count' => 3, 'out_count' => 1],
                    ],
                    'edges' => [
                        [
                            'from' => $peer,
                            'to' => $subject,
                            'direction' => 'in',
                            'hygiene' => 'spam',
                            'asset' => 'USDT',
                            'count' => 3,
                        ],
                    ],
                ],
            ],
        ]);

        $data = app(CheckReportPresenter::class)->data($check, true);
        $this->assertStringContainsString('<img', $data['walletGraphSvg']);
        $this->assertStringContainsString('file:///', $data['walletGraphSvg']);
        $this->assertStringContainsString('.svg', $data['walletGraphSvg']);
        $this->assertSame(1, preg_match('/src="([^"]+)"/', $data['walletGraphSvg'], $matches));
        $path = str_replace('/', DIRECTORY_SEPARATOR, (string) preg_replace('#^file:///#i', '', $matches[1]));
        $this->assertFileExists($path);
        $svg = (string) file_get_contents($path);
        $this->assertStringContainsString('fill="none"', $svg);
        $this->assertStringContainsString('#be123c', $svg);
        $this->assertStringContainsString('Helvetica', $svg);
        $this->assertStringContainsString('<circle', $svg);
        $this->assertStringContainsString('<path d="M', $svg);
        $this->assertStringNotContainsString('<a ', $svg);
        $this->assertStringNotContainsString('viewBox', $svg);
        $this->assertStringNotContainsString('paint-order', $svg);

        $html = view('reports.check', $data)->render();
        $this->assertStringContainsString(__('aml.wallet_graph'), $html);
        $this->assertStringContainsString(__('aml.graph_peers'), $html);
        $this->assertStringContainsString(__('aml.inflows'), $html);
        $this->assertStringContainsString(__('aml.outflows'), $html);
        $this->assertStringContainsString(__('aml.inflow_count'), $html);
        $this->assertStringContainsString($peer, $html);
        $this->assertStringContainsString('tronscan.org/#/address/', $html);
        $this->assertStringContainsString('table-layout: fixed', $html);
        $this->assertStringContainsString('word-break: break-all', $html);
        $this->assertStringContainsString('page-break-inside: avoid', $html);

        $pdf = Pdf::loadHTML($html)->setPaper('a4')->setOption('defaultFont', 'DejaVu Sans')->output();
        $this->assertStringStartsWith('%PDF', $pdf);
        $this->assertGreaterThan(8000, strlen($pdf));
        $decoded = $this->decodedPdf($pdf);
        $this->assertNotSame('', $decoded);
        $this->assertGreaterThan(10, preg_match_all('/[0-9.]+ [0-9.]+ [0-9.]+ [0-9.]+ re/', $decoded));
    }

    private function decodedPdf(string $pdf): string
    {
        preg_match_all('/stream\r?\n(.*?)\r?\nendstream/s', $pdf, $matches);
        $decoded = '';
        foreach ($matches[1] as $stream) {
            $try = @gzuncompress($stream);
            if (is_string($try) && $try !== '') {
                $decoded .= $try;
            }
        }

        return $decoded;
    }
}
