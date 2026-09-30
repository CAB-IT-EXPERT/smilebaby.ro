<?php

declare(strict_types=1);

require dirname(__DIR__) . '/app/bootstrap.php';

use App\Core\Database;

if (PHP_SAPI !== 'cli') { http_response_code(403); exit('CLI only'); }
[$script, $email, $password, $firstName, $lastName] = array_pad($argv, 5, null);
if (!$email || !$password || strlen($password) < 12) {
    fwrite(STDERR, "Utilizare: php tools/create-admin.php email parola-minim-12 Prenume Nume\n"); exit(1);
}
$stmt = Database::connection()->prepare('INSERT INTO users (email,password_hash,first_name,last_name,role,status) VALUES (?,?,?,?,"admin","active") ON DUPLICATE KEY UPDATE password_hash=VALUES(password_hash),first_name=VALUES(first_name),last_name=VALUES(last_name),role="admin",status="active"');
$stmt->execute([mb_strtolower($email), password_hash($password, PASSWORD_DEFAULT), $firstName ?: 'Admin', $lastName ?: 'SmileBaby']);
echo "Contul de administrator este pregătit.\n";
