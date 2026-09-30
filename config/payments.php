<?php

return [
    'encryption_key' => getenv('PAYMENT_ENCRYPTION_KEY') ?: getenv('APP_KEY') ?: '',
    'callback_base' => rtrim(getenv('APP_URL') ?: 'http://localhost:8080', '/') . '/plati/callback',
];
