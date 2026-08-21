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
        $fromDay = Carbon::parse($from)->startOfDay();
        $toDay = Carbon::parse($to)->endOfDay();

        $query = Check::query()
            ->when(! $user->is_admin, fn ($q) => $q->where('user_id', $user->id))
            ->whereBetween('created_at', [$fromDay, $toDay]);

        $stats = [
            'total' => (clone $query)->count(),
            'clear' => (clone $query)->whereIn('verdict', CheckVerdict::clearLike())->count(),
            'review' => (clone $query)->where('verdict', CheckVerdict::Review)->count(),
            'block' => (clone $query)->where('verdict', CheckVerdict::Block)->count(),
            'pending' => (clone $query)->where('status', CheckStatus::Pending)->count(),
        ];

        $latest = (clone $query)->latest()->limit(8)->get();
        $queue = (clone $query)
            ->whereIn('verdict', [CheckVerdict::Review, CheckVerdict::Block])
            ->latest()
            ->limit(12)
            ->get();

        return view('dashboard', [
            'stats' => $stats,
            'latest' => $latest,
            'queue' => $queue,
            'from' => $fromDay->toDateString(),
            'to' => $toDay->toDateString(),
        ]);
    }
}
