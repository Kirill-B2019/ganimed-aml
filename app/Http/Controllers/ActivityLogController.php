<?php

// | KB @CerberRus00 - Nexus Invest Team
namespace App\Http\Controllers;

use App\Models\ActivityLog;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ActivityLogController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();
        $logs = ActivityLog::query()
            ->with(['user', 'check'])
            ->when(! $user->is_admin, fn ($q) => $q->where('user_id', $user->id))
            ->latest()
            ->paginate(40);

        return view('activity.index', compact('logs'));
    }
}
