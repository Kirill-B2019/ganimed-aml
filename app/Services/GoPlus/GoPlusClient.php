<?php

// | KB @CerberRus00 - Nexus Invest Team
namespace App\Services\GoPlus;

use App\Support\TronAddress;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class GoPlusClient
{
    public function addressSecurity(string $address, ?string $chainId = null): array
    {
        $query = [];
        if ($chainId) {
            $query['chain_id'] = $chainId;
        }

        return $this->get('/api/v1/address_security/'.$address, $query);
    }

    public function tokenSecurity(string $chainId, string $contract): array
    {
        if ($chainId === 'solana') {
            return $this->get('/api/v1/solana/token_security', [
                'contract_addresses' => $contract,
            ]);
        }

        return $this->get('/api/v1/token_security/'.$chainId, [
            'contract_addresses' => $contract,
        ]);
    }

    public function phishingSite(string $url): array
    {
        return $this->get('/api/v1/phishing_site', ['url' => $url]);
    }

    public function dappSecurity(string $url): array
    {
        return $this->get('/api/v1/dapp_security', ['url' => $url]);
    }

    public function startAddressScan(string $chainId, string $address): array
    {
        if (in_array(strtolower($chainId), ['tron', 'solana'], true) || TronAddress::isTron($address)) {
            throw new GoPlusException((string) __('aml.goplus_chain_unsupported'), 2018);
        }

        return $this->get('/api/v1/address/scan/'.$chainId, [
            'address' => $address,
        ], true);
    }

    public function getAddressScanResult(string $requestId): array
    {
        return $this->get('/api/v1/address/result', [
            'request_id' => $requestId,
        ], true);
    }

    public function accessToken(): string
    {
        $cached = Cache::get('goplus.access_token');
        if (is_string($cached) && $cached !== '') {
            return $cached;
        }

        $key = config('goplus.app_key');
        $secret = config('goplus.app_secret');

        if (! $key || ! $secret) {
            throw new GoPlusException('GoPlus credentials are not configured.');
        }

        $time = time();
        $sign = sha1($key.$time.$secret);

        $payload = Http::baseUrl((string) config('goplus.base_url'))
            ->acceptJson()
            ->timeout((int) config('goplus.timeout', 30))
            ->asJson()
            ->post('/api/v1/token', [
                'app_key' => $key,
                'sign' => $sign,
                'time' => $time,
            ])
            ->throw()
            ->json();

        $result = $this->unwrap($payload);
        $token = $result['access_token'] ?? null;

        if (! is_string($token) || $token === '') {
            throw new GoPlusException('GoPlus did not return an access token.');
        }

        $ttl = max(60, (int) ($result['expires_in'] ?? 3600) - 60);
        Cache::put('goplus.access_token', $token, $ttl);

        return $token;
    }

    /**
     * @param  array<string, mixed>  $query
     * @return array<string, mixed>
     */
    private function get(string $path, array $query = [], bool $withToken = false): array
    {
        $payload = $this->http($withToken)
            ->get($path, $query)
            ->throw()
            ->json();

        try {
            return $this->unwrap($payload);
        } catch (GoPlusException $e) {
            if ($withToken && $e->getCode() === 4012) {
                Cache::forget('goplus.access_token');

                $payload = $this->http($withToken)
                    ->get($path, $query)
                    ->throw()
                    ->json();

                return $this->unwrap($payload);
            }

            throw $e;
        }
    }

    /**
     * @param  array<string, mixed>|null  $payload
     * @return array<string, mixed>
     */
    private function unwrap(?array $payload): array
    {
        if (! is_array($payload)) {
            throw new GoPlusException('Invalid GoPlus response.');
        }

        $code = (int) ($payload['code'] ?? 0);
        if ($code !== 1) {
            throw new GoPlusException($this->errorMessage($payload, $code), $code);
        }

        $result = $payload['result'] ?? [];

        return is_array($result) ? $result : [];
    }

    private function http(bool $withToken = false): PendingRequest
    {
        $request = Http::baseUrl((string) config('goplus.base_url'))
            ->acceptJson()
            ->timeout((int) config('goplus.timeout', 30))
            ->retry(2, 250);

        if ($withToken) {
            // GoPlus Address Scan rejects "Bearer <token>" with 4012; it expects the raw access_token.
            $request = $request->withHeaders([
                'Authorization' => $this->accessToken(),
            ]);
        }

        return $request;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function errorMessage(array $payload, int $code): string
    {
        return match ($code) {
            4012 => (string) __('aml.goplus_auth_failed'),
            2018 => (string) __('aml.goplus_chain_unsupported'),
            default => (string) ($payload['message'] ?? 'GoPlus request failed.'),
        };
    }
}
