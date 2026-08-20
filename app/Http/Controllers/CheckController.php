<?php

// | KB @CerberRus00 - Nexus Invest Team
namespace App\Http\Controllers;

use App\Enums\CheckStatus;
use App\Http\Requests\StoreAddressCheckRequest;
use App\Http\Requests\StoreScanCheckRequest;
use App\Http\Requests\StoreTokenCheckRequest;
use App\Http\Requests\StoreUrlCheckRequest;
use App\Http\Requests\UpdateCheckVerdictRequest;
use App\Models\Check;
use App\Services\Checks\CheckOverrideService;
use App\Services\Onchain\OnchainEnrichmentService;
use App\Services\Reports\CheckPdfService;
use App\Services\Reports\CheckReportPresenter;
use App\Services\Screening\ScreeningService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CheckController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();

        $checks = Check::query()
            ->with('user')
            ->when(! $user->is_admin, fn ($q) => $q->where('user_id', $user->id))
            ->when($request->filled('type'), fn ($q) => $q->where('type', $request->string('type')))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')))
            ->when($request->filled('verdict'), fn ($q) => $q->where('verdict', $request->string('verdict')))
            ->when($request->filled('q'), fn ($q) => $q->where('subject', 'like', '%'.$request->string('q').'%'))
            ->when($request->filled('from'), fn ($q) => $q->whereDate('created_at', '>=', $request->date('from')))
            ->when($request->filled('to'), fn ($q) => $q->whereDate('created_at', '<=', $request->date('to')))
            ->latest()
            ->paginate(20)
            ->withQueryString();

        return view('checks.index', compact('checks'));
    }

    public function create(): View
    {
        return view('checks.create', [
            'tab' => request('tab', 'address'),
        ]);
    }

    public function show(Request $request, Check $check, OnchainEnrichmentService $enrichment, CheckReportPresenter $presenter): View
    {
        $this->authorizeCheck($request, $check);
        $check->load('user');
        $enrichment->fill($check);

        return view('checks.show', $presenter->data($check));
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

    public function pdf(Request $request, Check $check, CheckPdfService $pdf, OnchainEnrichmentService $enrichment)
    {
        $this->authorizeCheck($request, $check);
        abort_unless($check->status === CheckStatus::Completed, 409, __('aml.pdf_not_ready'));
        $enrichment->fill($check);

        return $pdf->download($check);
    }

    public function storeAddress(StoreAddressCheckRequest $request, ScreeningService $screening): RedirectResponse
    {
        $check = $screening->runAddress(
            $request->user(),
            $request->validated('address'),
            $request->validated('chain_id'),
        );

        return $this->afterStore($check);
    }

    public function storeToken(StoreTokenCheckRequest $request, ScreeningService $screening): RedirectResponse
    {
        $check = $screening->runToken(
            $request->user(),
            $request->validated('contract'),
            $request->validated('chain_id'),
        );

        return $this->afterStore($check);
    }

    public function storePhishing(StoreUrlCheckRequest $request, ScreeningService $screening): RedirectResponse
    {
        $check = $screening->runPhishing($request->user(), $request->validated('url'));

        return $this->afterStore($check);
    }

    public function storeDapp(StoreUrlCheckRequest $request, ScreeningService $screening): RedirectResponse
    {
        $check = $screening->runDapp($request->user(), $request->validated('url'));

        return $this->afterStore($check);
    }

    public function storeScan(StoreScanCheckRequest $request, ScreeningService $screening): RedirectResponse
    {
        $check = $screening->startScan(
            $request->user(),
            $request->validated('address'),
            $request->validated('chain_id'),
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
}
