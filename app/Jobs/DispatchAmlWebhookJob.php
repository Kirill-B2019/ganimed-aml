<?php

// | KB @CerberRus00 - Nexus Invest Team
namespace App\Jobs;

use App\Models\Check;
use App\Models\User;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Http;
use Throwable;

class DispatchAmlWebhookJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /**
     * @param  array<string, mixed>  $payload
     */
    public function __construct(
        public int $userId,
        public array $payload,
    ) {}

    public function handle(): void
    {
        $user = User::query()->find($this->userId);
        $url = $user?->webhook_url;
        $secret = $user?->webhook_secret;
        if (! $user || ! is_string($url) || $url === '' || ! is_string($secret) || $secret === '') {
            return;
        }

        $body = json_encode($this->payload, JSON_UNESCAPED_UNICODE) ?: '{}';
        $signature = 'sha256='.hash_hmac('sha256', $body, $secret);

        try {
            Http::timeout(12)
                ->withHeaders([
                    'Content-Type' => 'application/json',
                    'X-Ganimed-Signature' => $signature,
                    'X-Ganimed-Event' => (string) ($this->payload['event'] ?? ''),
                ])
                ->withBody($body, 'application/json')
                ->post($url);
        } catch (Throwable) {
            // Retries via $tries.
            throw new \RuntimeException('Webhook delivery failed.');
        }
    }

    public static function forCheck(string $event, Check $check): void
    {
        $user = $check->user;
        if (! $user || ! $user->webhook_url || ! $user->webhook_secret) {
            return;
        }

        self::dispatch($user->id, [
            'event' => $event,
            'check_id' => $check->id,
            'status' => $check->status->value,
            'verdict' => $check->verdict?->value,
            'subject' => $check->subject,
            'type' => $check->type->value,
        ]);
    }
}
