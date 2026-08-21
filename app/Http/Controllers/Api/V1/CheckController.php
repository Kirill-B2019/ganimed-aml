<?php

// | KB @CerberRus00 - Nexus Invest Team
namespace App\Http\Controllers\Api\V1;

use App\Enums\CheckStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreAddressCheckRequest;
use App\Http\Requests\StoreScanCheckRequest;
use App\Http\Requests\StoreTokenCheckRequest;
use App\Http\Requests\StoreUrlCheckRequest;
use App\Http\Resources\CheckResource;
use App\Models\Check;
use App\Services\Onchain\OnchainEnrichmentService;
use App\Services\Reports\CheckPdfService;
use App\Services\Screening\ScreeningService;
use Illuminate\Http\Request;

class CheckController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();

        $checks = Check::query()
            ->when(! $user->is_admin, fn ($q) => $q->where('user_id', $user->id))
            ->latest()
            ->paginate(50);

        return CheckResource::collection($checks);
    }

    public function show(Request $request, Check $check, OnchainEnrichmentService $enrichment)
    {
        $this->authorizeCheck($request, $check);
        $enrichment->fill($check);

        return new CheckResource($check);
    }

    public function pdf(Request $request, Check $check, CheckPdfService $pdf, OnchainEnrichmentService $enrichment)
    {
        $this->authorizeCheck($request, $check);
        abort_unless($check->status === CheckStatus::Completed, 409);
        $enrichment->fill($check);

        return $pdf->download($check, $pdf->normalize((string) $request->query('variant', CheckPdfService::VARIANT_FULL)));
    }

    public function address(StoreAddressCheckRequest $request, ScreeningService $screening)
    {
        $check = $screening->runAddress(
            $request->user(),
            $request->validated('address'),
            $request->validated('chain_id'),
            ['case_id' => $request->integer('case_id') ?: null],
        );

        return (new CheckResource($check))->response()->setStatusCode(
            $check->status === CheckStatus::Failed ? 502 : 201
        );
    }

    public function token(StoreTokenCheckRequest $request, ScreeningService $screening)
    {
        $check = $screening->runToken(
            $request->user(),
            $request->validated('contract'),
            $request->validated('chain_id'),
            ['case_id' => $request->integer('case_id') ?: null],
        );

        return (new CheckResource($check))->response()->setStatusCode(
            $check->status === CheckStatus::Failed ? 502 : 201
        );
    }

    public function phishing(StoreUrlCheckRequest $request, ScreeningService $screening)
    {
        $check = $screening->runPhishing(
            $request->user(),
            $request->validated('url'),
            ['case_id' => $request->integer('case_id') ?: null],
        );

        return (new CheckResource($check))->response()->setStatusCode(
            $check->status === CheckStatus::Failed ? 502 : 201
        );
    }

    public function dapp(StoreUrlCheckRequest $request, ScreeningService $screening)
    {
        $check = $screening->runDapp(
            $request->user(),
            $request->validated('url'),
            ['case_id' => $request->integer('case_id') ?: null],
        );

        return (new CheckResource($check))->response()->setStatusCode(
            $check->status === CheckStatus::Failed ? 502 : 201
        );
    }

    public function scan(StoreScanCheckRequest $request, ScreeningService $screening)
    {
        $check = $screening->startScan(
            $request->user(),
            $request->validated('address'),
            $request->validated('chain_id'),
            ['case_id' => $request->integer('case_id') ?: null],
        );

        return (new CheckResource($check))->response()->setStatusCode(
            $check->status === CheckStatus::Pending ? 202 : ($check->status === CheckStatus::Failed ? 502 : 201)
        );
    }

    public function batch(Request $request)
    {
        $validated = $request->validate([
            'addresses' => ['required', 'array', 'min:1', 'max:50'],
            'addresses.*' => ['required', 'string', 'min:8', 'max:128'],
            'deep' => ['sometimes', 'boolean'],
            'case_id' => ['nullable', 'integer'],
        ]);

        $addresses = array_values(array_unique($validated['addresses']));
        \App\Jobs\ProcessWalletBatchJob::dispatch(
            $request->user()->id,
            $addresses,
            (bool) ($validated['deep'] ?? false),
            $validated['case_id'] ?? null,
        );

        return response()->json([
            'queued' => count($addresses),
            'deep' => (bool) ($validated['deep'] ?? false),
            'case_id' => $validated['case_id'] ?? null,
        ], 202);
    }

    private function authorizeCheck(Request $request, Check $check): void
    {
        abort_unless($request->user()->is_admin || $check->user_id === $request->user()->id, 403);
    }
}
