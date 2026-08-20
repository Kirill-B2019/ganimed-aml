<?php

// | KB @CerberRus00 - Nexus Invest Team
namespace App\Jobs;

use App\Enums\CheckStatus;
use App\Models\Check;
use App\Services\GoPlus\GoPlusClient;
use App\Services\GoPlus\GoPlusException;
use App\Services\Screening\ScreeningService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

class PollAddressScanJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 12;

    public int $timeout = 60;

    public function __construct(public Check $check) {}

    public function backoff(): int
    {
        return (int) config('goplus.scan_poll_seconds', 10);
    }

    public function handle(GoPlusClient $client, ScreeningService $screening): void
    {
        $check = $this->check->fresh();

        if (! $check || $check->status !== CheckStatus::Pending) {
            return;
        }

        if ($screening->usesImmediateScan($check->subject, (string) $check->chain_id)) {
            $screening->runScanFallback(
                $check->user,
                $check->subject,
                (string) $check->chain_id ?: 'tron',
                $check,
            );

            return;
        }

        try {
            if (! $check->provider_request_id) {
                $started = $client->startAddressScan((string) $check->chain_id, $check->subject);
                $requestId = $started['request_id'] ?? null;
                if (! is_string($requestId) || $requestId === '') {
                    throw new GoPlusException('GoPlus scan did not return request_id.');
                }
                $check->update(['provider_request_id' => $requestId]);
            }

            $result = $client->getAddressScanResult((string) $check->provider_request_id);

            if ($this->isPendingResult($result)) {
                $this->release((int) config('goplus.scan_poll_seconds', 10));

                return;
            }

            $screening->completeScan($check, $result);
        } catch (GoPlusException $e) {
            if ($this->shouldFallback($e)) {
                $screening->runScanFallback(
                    $check->user,
                    $check->subject,
                    (string) $check->chain_id,
                    $check,
                );

                return;
            }

            if ($e->getCode() === 4012) {
                $screening->fail($check, $e->getMessage());

                return;
            }

            if ($this->attempts() < $this->tries && $this->isRetryable($e)) {
                $this->release((int) config('goplus.scan_poll_seconds', 10));

                return;
            }

            $screening->fail($check, $e->getMessage());
        }
    }

    public function failed(?Throwable $exception): void
    {
        $check = $this->check->fresh();
        if ($check && $check->status === CheckStatus::Pending) {
            $check->update([
                'status' => CheckStatus::Failed,
                'error_message' => $exception?->getMessage() ?? 'Address scan timed out.',
            ]);
        }
    }

    /**
     * @param  array<string, mixed>  $result
     */
    private function isPendingResult(array $result): bool
    {
        if ($result === []) {
            return true;
        }

        return ! isset($result['scan_time']) && ! isset($result['chain_id']);
    }

    private function shouldFallback(GoPlusException $e): bool
    {
        $message = strtolower($e->getMessage());

        return $e->getCode() === 2018
            || str_contains($message, 'chainid not supported')
            || str_contains($message, 'chain not supported');
    }

    private function isRetryable(GoPlusException $e): bool
    {
        $message = strtolower($e->getMessage());

        return str_contains($message, 'pending')
            || str_contains($message, 'not ready')
            || str_contains($message, 'processing');
    }
}
