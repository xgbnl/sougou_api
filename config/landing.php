<?php

return [
    'allowed_origins' => array_values(array_filter(array_map(
        'trim',
        explode(',', (string)env('LANDING_ALLOWED_ORIGINS', ''))
    ))),
    'account_ids' => array_values(array_filter(array_map(
        fn($id) => (int)$id,
        array_map('trim', explode(',', (string)env('LANDING_ACCOUNT_IDS', '')))
    ))),
    'ip_limit' => [
        'max_attempts' => (int)env('LANDING_IP_MAX_ATTEMPTS', 6),
        'decay_seconds' => (int)env('LANDING_IP_DECAY_SECONDS', 60),
    ],
    'phone_limit' => [
        'max_attempts' => (int)env('LANDING_PHONE_MAX_ATTEMPTS', 1),
        'decay_seconds' => (int)env('LANDING_PHONE_DECAY_SECONDS', 600),
    ],
    'token_ttl_seconds' => (int)env('LANDING_TOKEN_TTL_SECONDS', 300),
];
