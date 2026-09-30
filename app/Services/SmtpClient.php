<?php

namespace App\Services;

use App\Core\Crypto;
use RuntimeException;

final class SmtpClient
{
    private $socket;

    public function send(string $to, string $subject, string $html, string $profile = 'customer'): bool
    {
        $mail = $this->settings($profile);
        $encodedBody = rtrim(chunk_split(base64_encode($html), 76, "\r\n"));
        $host = $mail['host'];
        if ($host === '') {
            return mail($to, $subject, $encodedBody, $this->headers($mail));
        }
        $port = $mail['port'];
        $prefix = $mail['encryption'] === 'ssl' ? 'ssl://' : '';
        $this->socket = stream_socket_client($prefix . $host . ':' . $port, $errno, $errstr, 15);
        if (!$this->socket) throw new RuntimeException("Conexiunea SMTP a eșuat: $errstr");
        $this->expect(220); $this->command('EHLO smilebaby.ro', 250);
        if ($mail['encryption'] === 'tls') { $this->command('STARTTLS', 220); stream_socket_enable_crypto($this->socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT); $this->command('EHLO smilebaby.ro', 250); }
        if ($mail['username'] !== '') { $this->command('AUTH LOGIN', 334); $this->command(base64_encode($mail['username']), 334); $this->command(base64_encode($mail['password']), 235); }
        $from = $mail['from_email'];
        $this->command('MAIL FROM:<' . $from . '>', 250); $this->command('RCPT TO:<' . $to . '>', 250); $this->command('DATA', 354);
        $message = $this->headers($mail, $to, $subject) . "\r\n\r\n" . $encodedBody . "\r\n.";
        $this->command($message, 250); $this->command('QUIT', 221); fclose($this->socket);
        return true;
    }

    private function settings(string $profile): array
    {
        $profileSettings = (array) config('mail.profiles.' . $profile, []);
        return [
            'host' => trim((string) setting('smtp_host', config('mail.host', ''))),
            'port' => max(1, (int) setting('smtp_port', config('mail.port', 587))),
            'username' => trim((string) ($profileSettings['username'] ?? setting('smtp_username', config('mail.username', '')))),
            'password' => (string) ($profileSettings['password'] ?? (($encrypted = (string) setting('smtp_password_encrypted', '')) !== '' ? Crypto::decrypt($encrypted) : config('mail.password', ''))),
            'encryption' => in_array($encryption = (string) setting('smtp_encryption', config('mail.encryption', 'tls')), ['tls', 'ssl', 'none'], true) ? $encryption : 'tls',
            'from_email' => trim((string) ($profileSettings['from_email'] ?? setting('smtp_from_email', config('mail.from_email', 'contact@smilebaby.ro')))),
            'from_name' => trim((string) ($profileSettings['from_name'] ?? setting('smtp_from_name', config('mail.from_name', 'SmileBaby')))),
        ];
    }

    private function headers(array $mail, ?string $to = null, ?string $subject = null): string
    {
        $domain = substr(strrchr((string) $mail['from_email'], '@') ?: '@smilebaby.ro', 1);
        $headers = [
            'Date: ' . date(DATE_RFC2822),
            'Message-ID: <' . bin2hex(random_bytes(16)) . '@' . $domain . '>',
            'MIME-Version: 1.0',
            'Content-Type: text/html; charset=UTF-8',
            'Content-Transfer-Encoding: base64',
            'From: =?UTF-8?B?' . base64_encode((string) $mail['from_name']) . '?= <' . $mail['from_email'] . '>',
            'Reply-To: <' . $mail['from_email'] . '>',
            'Auto-Submitted: auto-generated',
            'X-Auto-Response-Suppress: All',
        ];
        if ($to) array_unshift($headers, 'To: <' . $to . '>', 'Subject: =?UTF-8?B?' . base64_encode((string) $subject) . '?=');
        return implode("\r\n", $headers);
    }

    private function command(string $command, int $code): void { fwrite($this->socket, $command . "\r\n"); $this->expect($code); }
    private function expect(int $code): void
    {
        $response = '';
        while (($line = fgets($this->socket, 515)) !== false) { $response .= $line; if (strlen($line) < 4 || $line[3] === ' ') break; }
        if ((int) substr($response, 0, 3) !== $code) throw new RuntimeException('Răspuns SMTP neașteptat.');
    }
}
