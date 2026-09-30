<?php

return [
    'host' => getenv('SMTP_HOST') ?: '',
    'port' => (int) (getenv('SMTP_PORT') ?: 465),
    'username' => getenv('SMTP_CONTACT_USERNAME') ?: getenv('SMTP_USERNAME') ?: '',
    'password' => getenv('SMTP_CONTACT_PASSWORD') ?: getenv('SMTP_PASSWORD') ?: '',
    'encryption' => getenv('SMTP_ENCRYPTION') ?: 'ssl',
    'from_email' => getenv('MAIL_FROM_EMAIL') ?: 'contact@smilebaby.ro',
    'from_name' => getenv('MAIL_FROM_NAME') ?: 'SmileBaby',
    'order_recipient' => getenv('MAIL_ORDER_RECIPIENT') ?: 'contact@smilebaby.ro',
    'profiles' => [
        'customer' => [
            'username' => getenv('SMTP_CONTACT_USERNAME') ?: getenv('SMTP_USERNAME') ?: '',
            'password' => getenv('SMTP_CONTACT_PASSWORD') ?: getenv('SMTP_PASSWORD') ?: '',
            'from_email' => getenv('MAIL_CUSTOMER_FROM_EMAIL') ?: 'contact@smilebaby.ro',
            'from_name' => getenv('MAIL_CUSTOMER_FROM_NAME') ?: 'SmileBaby',
        ],
        'internal' => [
            'username' => getenv('SMTP_SITE_USERNAME') ?: '',
            'password' => getenv('SMTP_SITE_PASSWORD') ?: '',
            'from_email' => getenv('MAIL_INTERNAL_FROM_EMAIL') ?: 'site@smilebaby.ro',
            'from_name' => getenv('MAIL_INTERNAL_FROM_NAME') ?: 'SmileBaby — Magazin online',
        ],
    ],
];
