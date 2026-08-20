<?php

// | KB @CerberRus00 - Nexus Invest Team
namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class PingGoPlusCommand extends Command
{
    protected $signature = 'goplus:ping';

    protected $description = 'Verify GoPlus credentials without printing secrets';

    public function handle(): int
    {
        $key = (string) config('goplus.app_key');
        $secret = (string) config('goplus.app_secret');
        $base = (string) config('goplus.base_url');

        $this->info('GoPlus ping');
        $this->line('base_url: '.$base);
        $this->line('app_key configured: '.($key !== '' ? 'yes (len='.strlen($key).')' : 'NO'));
        $this->line('app_secret configured: '.($secret !== '' ? 'yes (len='.strlen($secret).')' : 'NO'));

        if ($key === '' || $secret === '') {
            return self::FAILURE;
        }

        $time = time();
        $sign = sha1($key.$time.$secret);
        $body = [
            'app_key' => $key,
            'sign' => $sign,
            'time' => $time,
        ];

        $json = Http::baseUrl($base)->acceptJson()->asJson()->timeout(20)->post('/api/v1/token', $body);
        $this->line('token JSON: HTTP '.$json->status().' code='.($json->json('code') ?? 'n/a').' message='.($json->json('message') ?? $json->body()));

        $form = Http::baseUrl($base)->acceptJson()->asForm()->timeout(20)->post('/api/v1/token', $body);
        $this->line('token FORM: HTTP '.$form->status().' code='.($form->json('code') ?? 'n/a').' message='.($form->json('message') ?? $form->body()));

        $open = Http::baseUrl($base)->acceptJson()->timeout(20)->get('/api/v1/address_security/TFq8GqCTiJA1PAnCJjtqDMHTRAsZgKNaYk', [
            'chain_id' => 'tron',
        ]);
        $this->line('address_security without token: HTTP '.$open->status().' code='.($open->json('code') ?? 'n/a').' message='.($open->json('message') ?? 'n/a'));
        if ((int) $open->json('code') === 1) {
            $this->line('sanctioned='.(string) ($open->json('result.sanctioned') ?? ''));
        }

        $token = $json->json('result.access_token') ?? $form->json('result.access_token');
        if (is_string($token) && $token !== '') {
            Cache::forget('goplus.access_token');
            $this->info('Access token received.');

            return self::SUCCESS;
        }

        $this->error('Access token was not issued.');

        return self::FAILURE;
    }
}
