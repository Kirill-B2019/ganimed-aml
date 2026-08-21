<?php

// | KB @CerberRus00 - Nexus Invest Team
namespace App\Services\Onchain;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Throwable;

class TronscanClient
{
    /**
     * @return array<string, mixed>
     */
    public function contract(string $address): array
    {
        $payload = $this->getJson('/api/contract', ['contract' => $address]);

        return $this->unwrapContract($payload, $address);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function unwrapContract(array $payload, string $address): array
    {
        $row = $payload;
        if (is_array($payload['data'][0] ?? null)) {
            $row = $payload['data'][0];
        } elseif (is_array($payload['data'] ?? null) && isset($payload['data'][$address]) && is_array($payload['data'][$address])) {
            $row = $payload['data'][$address];
        } elseif (is_array($payload['contractInfo'][$address] ?? null)) {
            $row = $payload['contractInfo'][$address];
        }

        if (! is_array($row) || $row === []) {
            return [];
        }

        $creator = $row['creator'] ?? null;
        $creatorAddress = '';
        if (is_array($creator)) {
            $creatorAddress = (string) ($creator['address'] ?? $creator['creator_address'] ?? '');
        } elseif (is_string($creator)) {
            $creatorAddress = $creator;
        }

        $createdRaw = $row['date_created'] ?? $row['timestamp'] ?? null;
            $createdAt = null;
            if (is_numeric($createdRaw) && (int) $createdRaw > 0) {
                $ms = (int) $createdRaw;
                $seconds = $ms > 20_000_000_000 ? intdiv($ms, 1000) : $ms;
                $createdAt = \Illuminate\Support\Carbon::createFromTimestamp($seconds)->toIso8601String();
            }

        $tag = trim((string) ($row['tag1'] ?? $row['tag'] ?? ''));

        return [
            'address' => (string) ($row['address'] ?? $address),
            'name' => (string) ($row['name'] ?? ''),
            'verified' => $this->isVerified($row),
            'vip' => $this->isTruthy($row['vip'] ?? false),
            'tag' => $tag,
            'creator' => $creatorAddress,
            'created_at' => $createdAt,
            'trx_count' => (int) ($row['trxCount'] ?? $row['trx_count'] ?? 0),
        ];
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function isVerified(array $row): bool
    {
        if ($this->isTruthy($row['verified'] ?? null)) {
            return true;
        }

        $status = $row['verify_status'] ?? $row['verifyStatus'] ?? null;

        return in_array($status, [1, 2, '1', '2', true], true);
    }

    private function isTruthy(mixed $value): bool
    {
        return $value === true || $value === 1 || $value === '1' || $value === 'true';
    }

    /**
     * @param  array<string, mixed>  $query
     * @return array<string, mixed>
     */
    private function getJson(string $path, array $query = []): array
    {
        $attempts = max(1, (int) config('onchain.retry_attempts', 3));
        $response = null;

        for ($attempt = 1; $attempt <= $attempts; $attempt++) {
            $this->throttle();
            $response = $this->http()->get($path, $query);
            if ($response->status() !== 429 || $attempt === $attempts) {
                break;
            }
            $delay = $this->retryDelayMs($response);
            if ($delay > 0) {
                usleep($delay * 1000);
            }
        }

        $response->throw();
        $payload = $response->json();

        return is_array($payload) ? $payload : [];
    }

    private function throttle(): void
    {
        $minMs = (int) config('onchain.min_interval_ms', 1300);
        if ($minMs <= 0) {
            return;
        }

        $run = function () use ($minMs) {
            $now = (int) round(microtime(true) * 1000);
            $last = (int) Cache::get('onchain:trongrid:last_ms', 0);
            $waitMs = $last + $minMs - $now;
            if ($waitMs > 0) {
                usleep($waitMs * 1000);
            }
            Cache::put('onchain:trongrid:last_ms', (int) round(microtime(true) * 1000), 120);
        };

        try {
            Cache::lock('onchain:trongrid:gate', 15)->block(12, $run);
        } catch (Throwable) {
            $run();
        }
    }

    private function retryDelayMs(Response $response): int
    {
        $configured = (int) config('onchain.retry_ms', 5500);
        if ($configured <= 0) {
            return 0;
        }

        $header = $response->header('Retry-After');
        if (is_numeric($header)) {
            return max($configured, ((int) $header) * 1000);
        }

        return $configured;
    }

    private function http(): PendingRequest
    {
        $request = Http::baseUrl((string) config('onchain.tronscan_base_url'))
            ->acceptJson()
            ->timeout((int) config('onchain.timeout', 20));

        $key = (string) config('onchain.trongrid_api_key');
        if ($key !== '') {
            $request = $request->withHeaders(['TRON-PRO-API-KEY' => $key]);
        }

        return $request;
    }
}
