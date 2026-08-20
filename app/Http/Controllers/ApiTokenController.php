<?php

// | KB @CerberRus00 - Nexus Invest Team
namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ApiTokenController extends Controller
{
    public function index(Request $request): View
    {
        return view('tokens.index', [
            'tokens' => $request->user()->tokens()->latest()->get(),
            'plainTextToken' => $request->session()->pull('plainTextToken'),
            'apiBase' => rtrim(url('/api/v1'), '/'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:80'],
        ]);

        $token = $request->user()->createToken($validated['name']);

        return back()->with('plainTextToken', $token->plainTextToken);
    }

    public function destroy(Request $request, string $tokenId): RedirectResponse
    {
        $request->user()->tokens()->where('id', $tokenId)->delete();

        return back()->with('status', __('aml.token_revoked'));
    }
}
