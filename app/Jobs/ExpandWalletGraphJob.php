<?php

// | KB @CerberRus00 - Nexus Invest Team
namespace App\Jobs;

use App\Models\Check;
use App\Services\Onchain\OnchainEnrichmentService;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class ExpandWalletGraphJob implements ShouldQueue, ShouldBeUnique
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 180;

    public int $uniqueFor = 180;

    public function __construct(public int $checkId) {}

    public function uniqueId(): string
    {
        return 'onchain-graph-'.$this->checkId;
    }

    public function handle(OnchainEnrichmentService $enrichment): void
    {
        $check = Check::query()->find($this->checkId);
        if (! $check) {
            return;
        }

        $enrichment->expandGraph($check);
    }
}
