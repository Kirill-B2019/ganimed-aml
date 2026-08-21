<?php

// | KB @CerberRus00 - Nexus Invest Team
namespace App\Console\Commands;

use App\Jobs\DispatchAmlWebhookJob;
use App\Models\WatchItem;
use App\Services\Ops\ActivityLogger;
use App\Services\Screening\ScreeningService;
use App\Models\Check;
use Illuminate\Console\Command;

class RunWatchlistCommand extends Command
{
    protected $signature = 'aml:watch-run';

    protected $description = 'Re-screen due watchlist subjects';

    public function handle(ScreeningService $screening, ActivityLogger $logger): int
    {
        $due = WatchItem::query()->with(['user', 'lastCheck'])->get()->filter(fn (WatchItem $item) => $item->isDue());

        foreach ($due as $item) {
            $user = $item->user;
            if (! $user) {
                continue;
            }

            $source = $item->lastCheck;
            $fresh = $source
                ? $screening->rerun($source, $user)
                : $screening->runAddress($user, $item->subject, $item->chain_id ?: 'tron', [
                    'case_id' => $item->case_id,
                ]);

            $previous = $item->last_verdict;
            $item->update([
                'last_check_id' => $fresh->id,
                'last_verdict' => $fresh->verdict,
                'last_run_at' => now(),
            ]);

            if (Check::verdictRank($fresh->verdict) > Check::verdictRank($previous)) {
                $logger->record($user, 'watch', $fresh, ['watch_id' => $item->id]);
                DispatchAmlWebhookJob::forCheck('watch.alerted', $fresh->loadMissing('user'));
            }
        }

        $this->info('Watchlist run complete ('.$due->count().').');

        return self::SUCCESS;
    }
}
