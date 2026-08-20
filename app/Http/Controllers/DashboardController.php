<?php

// | KB @CerberRus00 - Nexus Invest Team
namespace App\Http\Controllers;

use App\Enums\CheckStatus;
use App\Enums\CheckVerdict;
use App\Models\Check;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(Request $request): View
    {
        $user = $request->user();
        $from = $request->date('from') ?? now()->subDays(30)->startOfDay();
        $to = $request->date('to') ?? now()->endOfDay();

        $query = Check::query()
            ->when(! $user->is_admin, fn ($q) => $q->where('user_id', $user->id))
            ->whereBetween('created_at', [
                Carbon::parse($from)->startOfDay(),
                Carbon::parse($to)->endOfDay(),
            ]);

        $stats = [
            'total' => (clone $query)->count(),
            'clear' => (clone $query)->where('verdict', CheckVerdict::Clear)->count(),
            'review' => (clone $query)->where('verdict', CheckVerdict::Review)->count(),
            'block' => (clone $query)->where('verdict', CheckVerdict::Block)->count(),
            'pending' => (clone $query)->where('status', CheckStatus::Pending)->count(),
        ];

        $latest = Check::query()
            ->when(! $user->is_admin, fn ($q) => $q->where('user_id', $user->id))
            ->latest()
            ->limit(8)
            ->get();

        return view('dashboard', [
            'stats' => $stats,
            'latest' => $latest,
            'from' => Carbon::parse($from)->toDateString(),
            'to' => Carbon::parse($to)->toDateString(),
        ]);
    }
}
