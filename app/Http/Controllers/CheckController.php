<?php

// | KB @CerberRus00 - Nexus Invest Team
namespace App\Http\Controllers;

use App\Enums\CheckStatus;
use App\Http\Requests\StoreAddressCheckRequest;
use App\Http\Requests\StoreScanCheckRequest;
use App\Http\Requests\StoreTokenCheckRequest;
use App\Http\Requests\StoreUrlCheckRequest;
use App\Http\Requests\UpdateCheckVerdictRequest;
use App\Jobs\ProcessWalletBatchJob;
use App\Models\Check;
use App\Models\ScreeningCase;
use App\Services\Checks\CheckOverrideService;
use App\Services\Onchain\OnchainEnrichmentService;
use App\Services\Ops\ActivityLogger;
use App\Services\Reports\CheckPdfService;
use App\Services\Reports\CheckReportPresenter;
use App\Services\Screening\ScreeningService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CheckController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();

        $checks = $this->filteredChecks($request)
            ->with('user')
            ->latest()
            ->paginate(20)
            ->withQueryString();

        return view('checks.index', compact('checks'));
    }

    public function export(Request $request): StreamedResponse
    {
        $rows = $this->filteredChecks($request)->with('user')->latest()->get();
        $filename = 'checks_'.now()->format('Y-m-d_H-i-s').'.csv';

        return response()->streamDownload(function () use ($rows) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['id', 'subject', 'type', 'status', 'verdict', 'score', 'operator', 'created_at']);
            foreach ($rows as $check) {
                fputcsv($out, [
                    $check->id,
                    $check->subject,
                    $check->type->value,
                    $check->status->value,
                    $check->verdict?->value,
                    $check->risk_score,
                    $check->user?->name,
                    \App\Support\MskTime::format($check->created_at, 'Y-m-d H:i:s'),
                ]);
            }
            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv']);
    }

    public function create(): View
    {
        $cases = ScreeningCase::query()
            ->where('user_id', request()->user()->id)
            ->latest()
            ->get();

        $tab = request('tab', 'address');
        if (! in_array($tab, ['address', 'token', 'phishing', 'dapp'], true)) {
            $tab = 'address';
        }

        return view('checks.create', [
            'tab' => $tab,
            'cases' => $cases,
        ]);
    }

    public function show(Request $request, Check $check, OnchainEnrichmentService $enrichment, CheckReportPresenter $presenter, ActivityLogger $logger): View
    {
        $this->authorizeCheck($request, $check);
        $check->load('user', 'previousCheck');
        $activityLogs = $check->activityLogs()
            ->with('user')
            ->where('action', '!=', 'view')
            ->latest()
            ->limit(30)
            ->get();
        $logger->record($request->user(), 'view', $check);

        return view('checks.show', $presenter->data($check) + [
            'needsOnchainFetch' => $enrichment->needsFetch($check),
            'activityLogs' => $activityLogs,
        ]);
    }

    public function status(Request $request, Check $check)
    {
        $this->authorizeCheck($request, $check);

        return response()->json([
            'id' => $check->id,
            'status' => $check->status->value,
            'verdict' => $check->verdict?->value,
            'risk_score' => $check->risk_score,
        ]);
    }

    public function enrich(Request $request, Check $check, OnchainEnrichmentService $enrichment, ActivityLogger $logger)
    {
        $this->authorizeCheck($request, $check);
        $check = $enrichment->fill($check);
        $onchain = is_array($check->enrichment) ? $check->enrichment : [];
        $logger->record($request->user(), 'enrich', $check);

        return response()->json([
            'ok' => empty($onchain['error']) && empty($onchain['skipped']),
            'status' => $check->status->value,
            'error' => $onchain['error'] ?? null,
        ]);
    }

    public function rerun(Request $request, Check $check, ScreeningService $screening, ActivityLogger $logger): RedirectResponse
    {
        $this->authorizeCheck($request, $check);
        $fresh = $screening->rerun($check, $request->user());
        $logger->record($request->user(), 'rerun', $fresh, ['from' => $check->id]);

        return redirect()
            ->route('checks.show', $fresh)
            ->with('status', __('aml.check_completed'));
    }

    public function updateVerdict(UpdateCheckVerdictRequest $request, Check $check, CheckOverrideService $overrides): RedirectResponse
    {
        $this->authorizeCheck($request, $check);
        $overrides->apply(
            $check,
            $request->user(),
            $request->validated('verdict'),
            $request->validated('note'),
            $request->validated('tokens') ?? [],
        );

        return redirect()
            ->route('checks.show', $check)
            ->with('status', __('aml.override_saved'));
    }

    public function pdf(Request $request, Check $check, CheckPdfService $pdf, OnchainEnrichmentService $enrichment, ActivityLogger $logger)
    {
        $this->authorizeCheck($request, $check);
        abort_unless($check->status === CheckStatus::Completed, 409, __('aml.pdf_not_ready'));
        $enrichment->fill($check);
        $variant = $pdf->normalize((string) $request->query('variant', CheckPdfService::VARIANT_FULL), $check);
        $logger->record($request->user(), 'pdf', $check, ['variant' => $variant]);

        return $pdf->download($check, $variant);
    }

    public function destroy(Request $request, Check $check, ActivityLogger $logger): RedirectResponse
    {
        abort_unless((bool) $request->user()?->is_admin, 403);
        $this->authorizeCheck($request, $check);

        $logger->record($request->user(), 'delete', $check, [
            'subject' => $check->subject,
            'type' => $check->type->value,
            'check_id' => $check->id,
        ]);
        $check->delete();

        return redirect()
            ->route('checks.index')
            ->with('status', __('aml.check_deleted'));
    }

    public function storeAddress(StoreAddressCheckRequest $request, ScreeningService $screening): RedirectResponse
    {
        $meta = ['case_id' => $request->integer('case_id') ?: null];
        $addresses = $this->batchAddresses($request);
        if (count($addresses) > 1) {
            ProcessWalletBatchJob::dispatch(
                $request->user()->id,
                $addresses,
                false,
                $meta['case_id'],
            );

            return redirect()
                ->route('checks.index')
                ->with('status', __('aml.batch_queued'));
        }

        $check = $screening->runAddress(
            $request->user(),
            $request->validated('address'),
            $request->validated('chain_id'),
            $meta,
        );

        return $this->afterStore($check);
    }

    public function storeToken(StoreTokenCheckRequest $request, ScreeningService $screening): RedirectResponse
    {
        $check = $screening->runToken(
            $request->user(),
            $request->validated('contract'),
            $request->validated('chain_id'),
            ['case_id' => $request->integer('case_id') ?: null],
        );

        return $this->afterStore($check);
    }

    public function storePhishing(StoreUrlCheckRequest $request, ScreeningService $screening): RedirectResponse
    {
        $check = $screening->runPhishing(
            $request->user(),
            $request->validated('url'),
            ['case_id' => $request->integer('case_id') ?: null],
        );

        return $this->afterStore($check);
    }

    public function storeDapp(StoreUrlCheckRequest $request, ScreeningService $screening): RedirectResponse
    {
        $check = $screening->runDapp(
            $request->user(),
            $request->validated('url'),
            ['case_id' => $request->integer('case_id') ?: null],
        );

        return $this->afterStore($check);
    }

    public function storeScan(StoreScanCheckRequest $request, ScreeningService $screening): RedirectResponse
    {
        $meta = ['case_id' => $request->integer('case_id') ?: null];
        $addresses = $this->batchAddresses($request);
        if (count($addresses) > 1) {
            ProcessWalletBatchJob::dispatch(
                $request->user()->id,
                $addresses,
                true,
                $meta['case_id'],
            );

            return redirect()
                ->route('checks.index')
                ->with('status', __('aml.batch_queued'));
        }

        $check = $screening->startScan(
            $request->user(),
            $request->validated('address'),
            $request->validated('chain_id'),
            $meta,
        );

        return redirect()
            ->route('checks.show', $check)
            ->with('status', $check->isPending() ? __('aml.scan_queued') : __('aml.check_completed'));
    }

    private function afterStore(Check $check): RedirectResponse
    {
        $message = $check->status === CheckStatus::Failed
            ? __('aml.check_failed')
            : __('aml.check_completed');

        return redirect()
            ->route('checks.show', $check)
            ->with($check->status === CheckStatus::Failed ? 'error' : 'status', $message);
    }

    private function authorizeCheck(Request $request, Check $check): void
    {
        abort_unless($request->user()->is_admin || $check->user_id === $request->user()->id, 403);
    }

    private function filteredChecks(Request $request)
    {
        $user = $request->user();

        return Check::query()
            ->when(! $user->is_admin, fn ($q) => $q->where('user_id', $user->id))
            ->when($request->filled('type'), fn ($q) => $q->where('type', $request->string('type')))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')))
            ->when($request->filled('verdict'), fn ($q) => $q->where('verdict', $request->string('verdict')))
            ->when($request->filled('q'), fn ($q) => $q->where('subject', 'like', '%'.$request->string('q').'%'))
            ->when($request->filled('from'), fn ($q) => $q->whereDate('created_at', '>=', $request->date('from')))
            ->when($request->filled('to'), fn ($q) => $q->whereDate('created_at', '<=', $request->date('to')));
    }

    /**
     * @return list<string>
     */
    private function batchAddresses(Request $request): array
    {
        $raw = (string) $request->input('addresses', '');
        if (trim($raw) === '') {
            return [(string) $request->input('address')];
        }

        $lines = preg_split('/\R+/', $raw) ?: [];
        $addresses = [];
        foreach ($lines as $line) {
            $line = trim($line);
            if ($line !== '') {
                $addresses[] = $line;
            }
        }

        return array_values(array_unique(array_slice($addresses, 0, 50)));
    }
}
