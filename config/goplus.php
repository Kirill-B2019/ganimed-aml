<?php

// | KB @CerberRus00 - Nexus Invest Team
return [
    'base_url' => env('GOPLUS_BASE_URL', 'https://api.gopluslabs.io'),
    'app_key' => trim((string) env('GOPLUS_APP_KEY')),
    'app_secret' => trim((string) env('GOPLUS_APP_SECRET')),
    'timeout' => (int) env('GOPLUS_TIMEOUT', 30),
    'scan_max_attempts' => (int) env('GOPLUS_SCAN_MAX_ATTEMPTS', 12),
    'scan_poll_seconds' => (int) env('GOPLUS_SCAN_POLL_SECONDS', 10),

    'chains' => [
        'tron' => 'Tron',
    ],

    'chain_names' => [
        '1' => 'Ethereum',
        '56' => 'BNB Smart Chain',
        '42161' => 'Arbitrum',
        '137' => 'Polygon',
        '8453' => 'Base',
        '10' => 'Optimism',
        '43114' => 'Avalanche',
        '250' => 'Fantom',
        '25' => 'Cronos',
        '324' => 'zkSync Era',
        '59144' => 'Linea',
        '534352' => 'Scroll',
        '204' => 'opBNB',
        '5000' => 'Mantle',
        '100' => 'Gnosis',
        '128' => 'HECO',
        '321' => 'KCC',
        '201022' => 'FON',
        'solana' => 'Solana',
        'tron' => 'Tron',
    ],
];
