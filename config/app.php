<?php

return [
    'name' => getenv('APP_NAME') ?: 'SmileBaby',
    'url' => rtrim(getenv('APP_URL') ?: 'http://localhost:8080', '/'),
    'env' => getenv('APP_ENV') ?: 'production',
    'debug' => filter_var(getenv('APP_DEBUG') ?: false, FILTER_VALIDATE_BOOL),
    'timezone' => 'Europe/Bucharest',
    'currency' => 'RON',
    'session_name' => 'smilebaby_session',
    'key' => getenv('APP_KEY') ?: '',
];
