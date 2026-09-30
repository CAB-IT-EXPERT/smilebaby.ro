<?php

return [
    'encryption_key' => getenv('PAYMENT_ENCRYPTION_KEY') ?: getenv('APP_KEY') ?: '',
    'callback_base' => rtrim(getenv('APP_URL') ?: 'http://localhost:8080', '/') . '/plati/callback',
    'stripe' => [
        'secret_key' => getenv('STRIPE_SECRET_KEY') ?: '',
        'publishable_key' => getenv('STRIPE_PUBLISHABLE_KEY') ?: '',
        'webhook_secret' => getenv('STRIPE_WEBHOOK_SECRET') ?: '',
        'webhook_url' => getenv('STRIPE_WEBHOOK_URL') ?: 'https://smilebaby.ro/plati/callback/stripe',
        'api_version' => getenv('STRIPE_API_VERSION') ?: '2025-08-27.basil',
        'currency' => 'ron',
    ],
];
