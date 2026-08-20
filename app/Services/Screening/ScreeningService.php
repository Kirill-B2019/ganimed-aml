<?php

// | KB @CerberRus00 - Nexus Invest Team
namespace App\Services\Screening;

use App\Enums\CheckStatus;
use App\Enums\CheckType;
use App\Jobs\PollAddressScanJob;
use App\Models\Check;
use App\Models\User;
use App\Support\TronAddress;
use App\Services\GoPlus\GoPlusClient;
use App\Services\GoPlus\GoPlusException;
use App\Services\Onchain\OnchainEnrichmentService;
use App\Services\Risk\RiskScoringService;
use Throwable;

class ScreeningService
{
    public function __construct(
        private GoPlusClient $client,
        private RiskScoringService $scoring,
        private OnchainEnrichmentService $enrichment,
    ) {}

    public function runAddress(User $user, string $address, ?string $chainId): Check
    {
        return $this->runSync($user, CheckType::Address, $address, $chainId, fn () => $this->client->addressSecurity($address, $chainId));
    }

    public function runToken(User $user, string $contract, string $chainId): Check
    {
        return $this->runSync($user, CheckType::Token, $contract, $chainId, fn () => $this->client->tokenSecurity($chainId, $contract));
    }

    public function runPhishing(User $user, string $url): Check
    {
        return $this->runSync($user, CheckType::Phishing, $url, null, fn () => $this->client->phishingSite($url));
    }

    public function runDapp(User $user, string $url): Check
    {
        return $this->runSync($user, CheckType::Dapp, $url, null, fn () => $this->client->dappSecurity($url));
    }

    public function startScan(User $user, string $address, string $chainId): Check
    {
        if ($this->usesImmediateScan($address, $chainId)) {
            return $this->runScanFallback($user, $address, $chainId);
        }

        $check = Check::query()->create([
            'user_id' => $user->id,
            'type' => CheckType::Scan,
            'subject' => $address,
            'chain_id' => $chainId,
            'status' => CheckStatus::Pending,
            'locale' => app()->getLocale(),
        ]);

        PollAddressScanJob::dispatch($check);

        return $check;
    }

    /**
     * GoPlus Address Scan (approvals / NFT / depeg) is EVM-only.
     * Tron deep scan is synchronous: Address Security + on-chain, no queue, no 4012.
     */
    public function usesImmediateScan(string $address, string $chainId): bool
    {
        $chain = strtolower($chainId);

        return $chain === 'tron'
            || $chain === 'solana'
            || TronAddress::isTron($address);
    }

    public function addressScanUnsupported(string $chainId): bool
    {
        return $this->usesImmediateScan('', $chainId);
    }

    public function runScanFallback(User $user, string $address, string $chainId, ?Check $check = null): Check
    {
        $check ??= Check::query()->create([
            'user_id' => $user->id,
            'type' => CheckType::Scan,
            'subject' => $address,
            'chain_id' => $chainId,
            'status' => CheckStatus::Pending,
            'locale' => app()->getLocale(),
        ]);

        try {
            $result = $this->fallbackPayload(
                $this->client->addressSecurity($address, $chainId),
                $chainId,
            );
            $scored = $this->scoring->score(CheckType::Scan, $result);
            $check->update([
                'status' => CheckStatus::Completed,
                'verdict' => $scored['verdict'],
                'risk_score' => $scored['score'],
                'flags' => $scored['flags'],
                'raw_response' => $result,
                'error_message' => null,
            ]);
            $this->enrichment->fill($check->refresh());
        } catch (GoPlusException|Throwable $e) {
            $this->fail($check, $e->getMessage());
        }

        return $check->refresh();
    }

    /**
     * @param  array<string, mixed>  $addressSecurity
     * @return array<string, mixed>
     */
    public function fallbackPayload(array $addressSecurity, string $chainId): array
    {
        return [
            'scan_mode' => 'address_security_fallback',
            'scan_time' => now()->toDateTimeString(),
            'chain_id' => $chainId,
            ...$addressSecurity,
        ];
    }

    /**
     * @param  array<string, mixed>  $result
     */
    public function completeScan(Check $check, array $result): Check
    {
        $scored = $this->scoring->score(CheckType::Scan, $result);

        $check->update([
            'status' => CheckStatus::Completed,
            'verdict' => $scored['verdict'],
            'risk_score' => $scored['score'],
            'flags' => $scored['flags'],
            'raw_response' => $result,
            'error_message' => null,
        ]);

        return $this->enrichment->fill($check->refresh());
    }

    public function fail(Check $check, string $message): Check
    {
        $check->update([
            'status' => CheckStatus::Failed,
            'error_message' => $message,
        ]);

        return $check->refresh();
    }

    /**
     * @param  callable(): array<string, mixed>  $resolver
     */
    private function runSync(User $user, CheckType $type, string $subject, ?string $chainId, callable $resolver): Check
    {
        $check = Check::query()->create([
            'user_id' => $user->id,
            'type' => $type,
            'subject' => $subject,
            'chain_id' => $chainId,
            'status' => CheckStatus::Pending,
            'locale' => app()->getLocale(),
        ]);

        try {
            $result = $resolver();
            $scored = $this->scoring->score($type, $result);
            $check->update([
                'status' => CheckStatus::Completed,
                'verdict' => $scored['verdict'],
                'risk_score' => $scored['score'],
                'flags' => $scored['flags'],
                'raw_response' => $result,
            ]);
            $this->enrichment->fill($check->refresh());
        } catch (GoPlusException|Throwable $e) {
            $check->update([
                'status' => CheckStatus::Failed,
                'error_message' => $e->getMessage(),
            ]);
        }

        return $check->refresh();
    }
}
