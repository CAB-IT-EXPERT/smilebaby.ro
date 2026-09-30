<?php

$credentialFile = getenv('GOOGLE_CLIENT_SECRET_FILE') ?: '';
$credentials = [];
if ($credentialFile !== '' && is_file($credentialFile)) {
    $document = json_decode((string) file_get_contents($credentialFile), true);
    $credentials = is_array($document['web'] ?? null) ? $document['web'] : (is_array($document['installed'] ?? null) ? $document['installed'] : []);
}

return [
    'client_id' => getenv('GOOGLE_CLIENT_ID') ?: ($credentials['client_id'] ?? ''),
    'client_secret' => getenv('GOOGLE_CLIENT_SECRET') ?: ($credentials['client_secret'] ?? ''),
    // Callback-ul aparține mediului în care rulează aplicația. Nu folosim automat
    // primul URI din fișierul Google, deoarece acela poate fi cel de producție
    // chiar și atunci când magazinul este testat local.
    'redirect_uri' => getenv('GOOGLE_REDIRECT_URI') ?: (rtrim((string) config('app.url'), '/') . '/autentificare/google/callback'),
    'authorize_url' => 'https://accounts.google.com/o/oauth2/v2/auth',
    'token_url' => 'https://oauth2.googleapis.com/token',
    'token_info_url' => 'https://oauth2.googleapis.com/tokeninfo',
];

