<?php

// | KB @CerberRus00 - Nexus Invest Team
namespace App\Http\Controllers;

use App\Enums\CheckType;
use App\Models\Check;
use App\Models\ScreeningCase;
use App\Models\WatchItem;
use App\Services\Ops\DefaultScreeningCases;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\View\View;

class WatchItemController extends Controller
{
    public function index(Request $request, DefaultScreeningCases $defaults): View
    {
        $defaults->ensureFor($request->user());

        try {
            $items = WatchItem::query()
                ->where('user_id', $request->user()->id)
                ->with(['lastCheck', 'screeningCase'])
                ->latest()
                ->paginate(30);

            $cases = ScreeningCase::query()
                ->where('user_id', $request->user()->id)
                ->orderBy('name')
                ->get();
        } catch (QueryException $e) {
            report($e);
            $items = new LengthAwarePaginator([], 0, 30, 1, [
                'path' => $request->url(),
            ]);
            $cases = collect();
        }

        return view('watch.index', compact('items', 'cases'));
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'check_id' => ['nullable', 'integer'],
            'subject' => ['required_without:check_id', 'nullable', 'string', 'min:8', 'max:128'],
            'interval_days' => ['nullable', 'integer', 'min:1', 'max:90'],
            'case_id' => ['nullable', 'integer'],
        ]);

        $user = $request->user();
        $interval = (int) ($validated['interval_days'] ?? 7);
        $caseId = $this->ownedCaseId($user->id, $validated['case_id'] ?? null);

        if (! empty($validated['check_id'])) {
            $check = Check::query()->findOrFail($validated['check_id']);
            abort_unless($user->is_admin || $check->user_id === $user->id, 403);
            $subject = $check->subject;
            $type = $check->type instanceof CheckType ? $check->type : CheckType::Address;
            $chainId = $check->chain_id;
            $caseId = $caseId ?? $check->case_id;
            $lastCheckId = $check->id;
            $lastVerdict = $check->verdict;
        } else {
            $subject = trim((string) $validated['subject']);
            $type = CheckType::Address;
            $chainId = 'tron';
            $lastCheckId = null;
            $lastVerdict = null;
        }

        $item = WatchItem::query()->firstOrNew([
            'user_id' => $user->id,
            'subject' => $subject,
        ]);
        $item->fill([
            'case_id' => $caseId,
            'type' => $type,
            'chain_id' => $chainId,
            'interval_days' => $interval,
            'last_check_id' => $lastCheckId ?? $item->last_check_id,
            'last_verdict' => $lastVerdict ?? $item->last_verdict,
        ]);
        $item->save();

        return back()->with('status', __('aml.watch_saved'));
    }

    public function update(Request $request, WatchItem $watch): RedirectResponse
    {
        abort_unless($watch->user_id === $request->user()->id || $request->user()->is_admin, 403);
        $validated = $request->validate([
            'interval_days' => ['required', 'integer', 'min:1', 'max:90'],
            'case_id' => ['nullable', 'integer'],
        ]);

        $watch->update([
            'interval_days' => $validated['interval_days'],
            'case_id' => $this->ownedCaseId($request->user()->id, $validated['case_id'] ?? $watch->case_id),
        ]);

        return back()->with('status', __('aml.watch_interval_updated'));
    }

    public function destroy(Request $request, WatchItem $watch): RedirectResponse
    {
        abort_unless($watch->user_id === $request->user()->id || $request->user()->is_admin, 403);
        $watch->delete();

        return back()->with('status', __('aml.watchlist'));
    }

    private function ownedCaseId(int $userId, mixed $caseId): ?int
    {
        $id = (int) $caseId;
        if ($id < 1) {
            return null;
        }

        $owned = ScreeningCase::query()->where('id', $id)->where('user_id', $userId)->exists();

        return $owned ? $id : null;
    }
}
