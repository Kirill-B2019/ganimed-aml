<?php

// | KB @CerberRus00 - Nexus Invest Team
namespace App\Http\Controllers;

use App\Models\ScreeningCase;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ScreeningCaseController extends Controller
{
    public function index(Request $request): View
    {
        $cases = ScreeningCase::query()
            ->where('user_id', $request->user()->id)
            ->withCount('checks')
            ->latest()
            ->paginate(20);

        return view('cases.index', compact('cases'));
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:160'],
            'note' => ['nullable', 'string', 'max:2000'],
        ]);

        ScreeningCase::query()->create([
            'user_id' => $request->user()->id,
            'name' => $validated['name'],
            'note' => $validated['note'] ?? null,
        ]);

        return back()->with('status', __('aml.create_case'));
    }

    public function show(Request $request, ScreeningCase $case): View
    {
        abort_unless($case->user_id === $request->user()->id || $request->user()->is_admin, 403);
        $case->load(['checks' => fn ($q) => $q->latest()]);

        return view('cases.show', ['case' => $case]);
    }
}
