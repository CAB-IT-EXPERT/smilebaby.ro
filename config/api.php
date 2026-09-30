<?php

return [
    'token_ttl_days' => max(1, (int) (getenv('API_TOKEN_TTL_DAYS') ?: 30)),
    'allowed_origins' => array_values(array_filter(array_map('trim', explode(',', getenv('API_ALLOWED_ORIGINS') ?: 'https://smilebaby.ro,https://www.smilebaby.ro,http://127.0.0.1:8787,http://localhost:8787')))),
];
