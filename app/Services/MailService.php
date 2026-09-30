<?php

namespace App\Services;

use App\Core\Database;

final class MailService
{
    public function orderReceived(array $order, array $customer): void
    {
        $this->send($customer['email'], 'Comanda ' . $order['order_number'] . ' a fost primită', 'emails/order', compact('order', 'customer'));
    }

    public function orderStatus(int $orderId): void
    {
        $stmt = Database::connection()->prepare('SELECT * FROM orders WHERE id=?');
        $stmt->execute([$orderId]);
        $order = $stmt->fetch();
        if ($order) $this->send($order['email'], 'Actualizare pentru comanda ' . $order['order_number'], 'emails/status', compact('order'));
    }

    public function send(string $to, string $subject, string $template, array $data): bool
    {
        extract($data, EXTR_SKIP);
        ob_start();
        require BASE_PATH . '/views/' . $template . '.php';
        $html = ob_get_clean();
        $status = 'failed'; $error = null;
        try {
            $ok = (new SmtpClient())->send($to, $subject, $html);
            $status = $ok ? 'sent' : 'failed';
        } catch (\Throwable $e) { $ok = false; $error = mb_substr($e->getMessage(), 0, 500); }
        if (Database::available()) Database::connection()->prepare('INSERT INTO email_logs (recipient,subject,template,status,error_message,sent_at) VALUES (?,?,?,?,?,?)')->execute([$to, $subject, $template, $status, $error, $ok ? date('Y-m-d H:i:s') : null]);
        return $ok;
    }
}
