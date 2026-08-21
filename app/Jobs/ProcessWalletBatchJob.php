<?php

// | KB @CerberRus00 - Nexus Invest Team
namespace App\Jobs;

use App\Models\User;
use App\Services\Screening\ScreeningService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class ProcessWalletBatchJob implements ShouldQueue
{
    use Queueable;

    public int $timeout = 900;

    /**
     * @param  list<string>  $addresses
     */
    public function __construct(
        public int $userId,
        public array $addresses,
        public bool $deep = false,
        public ?int $caseId = null,
    ) {}

    public function handle(ScreeningService $screening): void
    {
        $user = User::query()->find($this->userId);
        if (! $user) {
            return;
        }

        $meta = ['case_id' => $this->caseId];
        foreach ($this->addresses as $address) {
            if ($this->deep) {
                $screening->startScan($user, $address, 'tron', $meta);
            } else {
                $screening->runAddress($user, $address, 'tron', $meta);
            }
        }
    }
}
