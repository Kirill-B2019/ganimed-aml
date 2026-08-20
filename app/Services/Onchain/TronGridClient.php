<?php

// | KB @CerberRus00 - Nexus Invest Team
namespace App\Services\Onchain;

use App\Support\TronAddress;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;

class TronGridClient
{
    /**
     * @return array<string, mixed>
     */
    public function account(string $address): array
    {
        $payload = $this->http()
            ->get('/v1/accounts/'.$address)
            ->throw()
            ->json();

        return is_array($payload['data'][0] ?? null) ? $payload['data'][0] : [];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function incomingTrc20(string $address, int $limit = 50): array
    {
        $payload = $this->http()
            ->get('/v1/accounts/'.$address.'/transactions/trc20', [
                'limit' => $limit,
                'only_to' => 'true',
                'only_confirmed' => 'true',
            ])
            ->throw()
            ->json();

        return is_array($payload['data'] ?? null) ? array_values($payload['data']) : [];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function incomingTrx(string $address, int $limit = 50): array
    {
        $payload = $this->http()
            ->get('/v1/accounts/'.$address.'/transactions', [
                'limit' => $limit,
                'only_to' => 'true',
                'only_confirmed' => 'true',
            ])
            ->throw()
            ->json();

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
