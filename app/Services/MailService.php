<?php

namespace App\Services;

use App\Core\Database;

final class MailService
{
    public function orderReceived(array $order, array $customer): void
    {
        $snapshot = $this->orderSnapshot((int) $order['id']);
        $order = $snapshot['order']; $items = $snapshot['items'];
        $this->send($customer['email'], 'Am primit comanda ' . $order['order_number'], 'emails/order', compact('order', 'items', 'customer'), 'customer');
        $recipient = trim((string) config('mail.order_recipient', 'contact@smilebaby.ro'));
        if ($recipient !== '') $this->send($recipient, 'Comandă nouă ' . $order['order_number'] . ' · ' . money($order['total']), 'emails/order-internal', compact('order', 'items', 'customer'), 'internal');
    }

    public function orderStatus(int $orderId): bool
    {
        $snapshot = $this->orderSnapshot($orderId);
        $order = $snapshot['order']; $items = $snapshot['items'];
        return $order
            ? $this->send($order['email'], $this->statusSubject($order), 'emails/status', compact('order', 'items'), 'customer')
            : false;
    }

    public function send(string $to, string $subject, string $template, array $data, string $profile = 'customer'): bool
    {
        extract($data, EXTR_SKIP);
        ob_start();
        require BASE_PATH . '/views/' . $template . '.php';
        $html = ob_get_clean();
        $status = 'failed'; $error = null;
        try {
            $ok = (new SmtpClient())->send($to, $subject, $html, $profile);
            $status = $ok ? 'sent' : 'failed';
        } catch (\Throwable $e) { $ok = false; $error = mb_substr($e->getMessage(), 0, 500); }
        if (Database::available()) Database::connection()->prepare('INSERT INTO email_logs (recipient,subject,template,status,error_message,sent_at) VALUES (?,?,?,?,?,?)')->execute([$to, $subject, $template, $status, $error, $ok ? date('Y-m-d H:i:s') : null]);
        return $ok;
    }

    private function orderSnapshot(int $orderId): array
    {
        $stmt = Database::connection()->prepare('SELECT * FROM orders WHERE id=? LIMIT 1');
        $stmt->execute([$orderId]);
        $order = $stmt->fetch();
        if (!$order) return ['order' => null, 'items' => []];
        $items = Database::connection()->prepare('SELECT * FROM order_items WHERE order_id=? ORDER BY id');
        $items->execute([$orderId]);
        return ['order' => $order, 'items' => $items->fetchAll()];
    }

    private function statusSubject(array $order): string
    {
        $label = match ($order['status']) {
            'confirmed' => ($order['payment_status'] ?? '') === 'paid' ? 'Plata și comanda au fost confirmate' : 'Comanda ta a fost confirmată',
            'processing' => 'Comanda ta este în lucru',
            'prepared' => 'Comanda ta este pregătită',
            'shipped' => 'Comanda ta a plecat spre tine',
            'delivered' => 'Comanda ta a fost livrată',
            'cancelled' => 'Comanda ta a fost anulată',
            'returned' => 'Returul comenzii a fost înregistrat',
            default => 'Am primit comanda ta',
        };
        return $label . ' · ' . $order['order_number'];
    }
}
