<?php

// | KB @CerberRus00 - Nexus Invest Team
return [
    'trongrid_base_url' => env('TRONGRID_BASE_URL', 'https://api.trongrid.io'),
    'trongrid_api_key' => trim((string) env('TRONGRID_API_KEY')),
    'timeout' => (int) env('ONCHAIN_TIMEOUT', 20),
    'min_interval_ms' => (int) env('ONCHAIN_MIN_INTERVAL_MS', 1300),
    'retry_attempts' => (int) env('ONCHAIN_RETRY_ATTEMPTS', 3),
    'retry_ms' => (int) env('ONCHAIN_RETRY_MS', 5500),
    'tx_limit' => (int) env('ONCHAIN_TX_LIMIT', 50),
    'scan_tx_limit' => (int) env('ONCHAIN_SCAN_TX_LIMIT', 200),
    'balance_limit' => (int) env('ONCHAIN_BALANCE_LIMIT', 15),
    'account_cache_seconds' => (int) env('ONCHAIN_ACCOUNT_CACHE', 900),
    'fingerprint_pages' => (int) env('ONCHAIN_FINGERPRINT_PAGES', 2),
    'graph_neighbor_cap' => (int) env('ONCHAIN_GRAPH_NEIGHBOR_CAP', 12),
    'graph_hop2_seeds' => (int) env('ONCHAIN_GRAPH_HOP2_SEEDS', 4),
    'graph_max_nodes' => (int) env('ONCHAIN_GRAPH_MAX_NODES', 20),
    'fx_url' => env('ONCHAIN_FX_URL', 'https://api.coingecko.com/api/v3/simple/price'),
    'fx_cache_seconds' => (int) env('ONCHAIN_FX_CACHE', 900),
    'fx_timeout' => (int) env('ONCHAIN_FX_TIMEOUT', 8),
    'fx_trx_usd' => (float) env('ONCHAIN_FX_TRX_USD', 0.12),
];
