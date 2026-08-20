<?php

// | KB @CerberRus00 - Nexus Invest Team
return [
    'trongrid_base_url' => env('TRONGRID_BASE_URL', 'https://api.trongrid.io'),
    'trongrid_api_key' => trim((string) env('TRONGRID_API_KEY')),
    'timeout' => (int) env('ONCHAIN_TIMEOUT', 20),
    'tx_limit' => (int) env('ONCHAIN_TX_LIMIT', 50),
    'scan_tx_limit' => (int) env('ONCHAIN_SCAN_TX_LIMIT', 200),
    'balance_limit' => (int) env('ONCHAIN_BALANCE_LIMIT', 15),
    'fx_url' => env('ONCHAIN_FX_URL', 'https://api.coingecko.com/api/v3/simple/price'),
    'fx_cache_seconds' => (int) env('ONCHAIN_FX_CACHE', 900),
    'fx_timeout' => (int) env('ONCHAIN_FX_TIMEOUT', 8),
    'fx_trx_usd' => (float) env('ONCHAIN_FX_TRX_USD', 0.12),
];
