<?php

// | KB @CerberRus00 - Nexus Invest Team
namespace App\Http\Controllers;

use App\Enums\CheckType;
use App\Models\Check;
use App\Models\WatchItem;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class WatchItemController extends Controller
{
    public function index(Request $request): View
    {
        $items = WatchItem::query()
            ->where('user_id', $request->user()->id)
            ->with('lastCheck')
            ->latest()
            ->paginate(30);

        return view('watch.index', compact('items'));
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'check_id' => ['required', 'integer'],
            'interval_days' => ['nullable', 'integer', 'min:1', 'max:90'],
        ]);

        $check = Check::query()->findOrFail($validated['check_id']);
        abort_unless($request->user()->is_admin || $check->user_id === $request->user()->id, 403);

        WatchItem::query()->create([
            'user_id' => $request->user()->id,
            'case_id' => $check->case_id,
            'last_check_id' => $check->id,
            'type' => $check->type instanceof CheckType ? $check->type : CheckType::Address,
            'subject' => $check->subject,
            'chain_id' => $check->chain_id,
            'interval_days' => $validated['interval_days'] ?? 7,
            'last_verdict' => $check->verdict,
            'last_run_at' => now(),
        ]);

        return back()->with('status', __('aml.watch_add'));
    }

    public function destroy(Request $request, WatchItem $watch): RedirectResponse
    {
        abort_unless($watch->user_id === $request->user()->id, 403);
        $watch->delete();

        return back()->with('status', __('aml.watchlist'));
    }
}
