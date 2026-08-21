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
    public function account(string $address): array
    {
        $payload = $this->getJson('/v1/accounts/'.$address);

        return is_array($payload['data'][0] ?? null) ? $payload['data'][0] : [];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function incomingTrc20(string $address, int $limit = 50): array
    {
        $payload = $this->getJson('/v1/accounts/'.$address.'/transactions/trc20', [
            'limit' => $limit,
            'only_to' => 'true',
            'only_confirmed' => 'true',
        ]);

        return is_array($payload['data'] ?? null) ? array_values($payload['data']) : [];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function incomingTrx(string $address, int $limit = 50): array
    {
        $payload = $this->getJson('/v1/accounts/'.$address.'/transactions', [
            'limit' => $limit,
            'only_to' => 'true',
            'only_confirmed' => 'true',
        ]);

        $rows = is_array($payload['data'] ?? null) ? $payload['data'] : [];

        return array_values(array_filter($rows, function ($row) {
            $type = $row['raw_data']['contract'][0]['type'] ?? '';

            return $type === 'TransferContract';
        }));
    }

    public function hexToBase58(string $hex): string
    {
        return TronAddress::fromHex($hex);
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
        $minMs = (int) config('onchain.min_interval_ms', 1100);
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
}
