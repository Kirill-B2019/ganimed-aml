<?php

// | KB @CerberRus00 - Nexus Invest Team
namespace App\Services\Onchain;

use App\Support\TronAddress;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Throwable;

class TronGridClient
{
    /**
     * @return array<string, mixed>
     */
    public function account(string $address, bool $soft = false): array
    {
        $seconds = (int) config('onchain.account_cache_seconds', 900);
        $fetch = function () use ($address, $soft) {
            $payload = $this->getJson('/v1/accounts/'.$address, [], $soft);
            if ($this->isRateLimited($payload)) {
                return ['_rate_limited' => true];
            }

            return is_array($payload['data'][0] ?? null) ? $payload['data'][0] : [];
        };

        if ($seconds <= 0) {
            return $fetch();
        }

        $cached = Cache::get($this->accountCacheKey($address));
        if (is_array($cached) && empty($cached['_rate_limited'])) {
            return $cached;
        }

        $account = $fetch();
        if (! $this->isRateLimited($account)) {
            Cache::put($this->accountCacheKey($address), $account, $seconds);
        }

        return $account;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function incomingTrc20(string $address, int $limit = 50, int $pages = 1, bool $soft = false, ?string $fingerprint = null): array
    {
        return $this->collectPages('/v1/accounts/'.$address.'/transactions/trc20', [
            'limit' => $limit,
            'only_to' => 'true',
            'only_confirmed' => 'true',
        ], $pages, $soft, $fingerprint);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function outgoingTrc20(string $address, int $limit = 50, int $pages = 1, bool $soft = false): array
    {
        return $this->collectPages('/v1/accounts/'.$address.'/transactions/trc20', [
            'limit' => $limit,
            'only_from' => 'true',
            'only_confirmed' => 'true',
        ], $pages, $soft);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function incomingTrx(string $address, int $limit = 50, int $pages = 1, bool $soft = false): array
    {
        $rows = $this->collectPages('/v1/accounts/'.$address.'/transactions', [
            'limit' => $limit,
            'only_to' => 'true',
            'only_confirmed' => 'true',
        ], $pages, $soft);

        return $this->onlyTransfers($rows);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function outgoingTrx(string $address, int $limit = 50, int $pages = 1, bool $soft = false): array
    {
        $rows = $this->collectPages('/v1/accounts/'.$address.'/transactions', [
            'limit' => $limit,
            'only_from' => 'true',
            'only_confirmed' => 'true',
        ], $pages, $soft);

        return $this->onlyTransfers($rows);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function internalTransactions(string $address, int $limit = 50, bool $soft = true): array
    {
        return $this->collectPages('/v1/accounts/'.$address.'/internal-transactions', [
            'limit' => $limit,
            'only_confirmed' => 'true',
        ], 1, $soft);
    }

    public function lastFingerprint(): ?string
    {
        return $this->lastFingerprint;
    }

    public function lastWasRateLimited(): bool
    {
        return $this->lastRateLimited;
    }

    public function hexToBase58(string $hex): string
    {
        return TronAddress::fromHex($hex);
    }

    private ?string $lastFingerprint = null;

    private bool $lastRateLimited = false;

    /**
     * @param  array<string, mixed>  $query
     * @return list<array<string, mixed>>
     */
    private function collectPages(string $path, array $query, int $pages, bool $soft, ?string $startFingerprint = null): array
    {
        $pages = max(1, $pages);
        $all = [];
        $fingerprint = $startFingerprint;
        $this->lastFingerprint = null;
        $this->lastRateLimited = false;

        for ($page = 0; $page < $pages; $page++) {
            $pageQuery = $query;
            if (is_string($fingerprint) && $fingerprint !== '') {
                $pageQuery['fingerprint'] = $fingerprint;
            }
            $payload = $this->getJson($path, $pageQuery, $soft);
            if ($this->isRateLimited($payload)) {
                $this->lastRateLimited = true;
                break;
            }
            $rows = is_array($payload['data'] ?? null) ? array_values($payload['data']) : [];
            $all = array_merge($all, $rows);
            $fingerprint = $this->fingerprintFrom($payload);
            $this->lastFingerprint = $fingerprint;
            if ($fingerprint === null || $rows === []) {
                break;
            }
        }

        return $all;
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @return list<array<string, mixed>>
     */
    private function onlyTransfers(array $rows): array
    {
        return array_values(array_filter($rows, function ($row) {
            if (! empty($row['_rate_limited'])) {
                return false;
            }
            $type = $row['raw_data']['contract'][0]['type'] ?? '';

            return $type === 'TransferContract';
        }));
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function fingerprintFrom(array $payload): ?string
    {
        $meta = is_array($payload['meta'] ?? null) ? $payload['meta'] : [];
        $value = $meta['fingerprint'] ?? $payload['fingerprint'] ?? null;

        return is_string($value) && $value !== '' ? $value : null;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function isRateLimited(array $payload): bool
    {
        return ! empty($payload['_rate_limited']);
    }

    /**
     * @param  array<string, mixed>  $query
     * @return array<string, mixed>
     */
    private function getJson(string $path, array $query = [], bool $soft = false): array
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

        if ($response->status() === 429) {
            if ($soft) {
                $this->lastRateLimited = true;

                return ['_rate_limited' => true, 'data' => []];
            }
            $response->throw();
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

        if (preg_match('/suspended for (\d+)\s*s/i', (string) $response->body(), $match)) {
            return max($configured, ((int) $match[1]) * 1000 + 400);
        }

        return $configured;
    }

    private function http(): PendingRequest
    {
        $request = Http::baseUrl((string) config('onchain.trongrid_base_url'))
            ->acceptJson()
            ->timeout((int) config('onchain.timeout', 20));

        $key = (string) config('onchain.trongrid_api_key');
        if ($key !== '') {
            $request = $request->withHeaders(['TRON-PRO-API-KEY' => $key]);
        }

        return $request;
    }

    private function accountCacheKey(string $address): string
    {
        return 'onchain:tron:account:'.$address;
    }
}
