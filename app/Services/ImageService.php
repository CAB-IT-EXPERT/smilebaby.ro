<?php

namespace App\Services;

use RuntimeException;

final class ImageService
{
    public function store(array $file, string $folder = 'products'): string
    {
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) throw new RuntimeException('Încărcarea imaginii a eșuat.');
        if (($file['size'] ?? 0) > 12 * 1024 * 1024) throw new RuntimeException('Imaginea depășește 12 MB.');
        $mime = class_exists(\finfo::class) ? (new \finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']) : (getimagesize($file['tmp_name'])['mime'] ?? '');
        $ext = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp', 'image/gif' => 'gif'][$mime] ?? null;
        if (!$ext) throw new RuntimeException('Format de imagine neacceptat.');
        $dir = 'uploads/' . trim($folder, '/') . '/' . date('Y/m');
        $absolute = BASE_PATH . '/' . $dir;
        if (!is_dir($absolute) && !mkdir($absolute, 0775, true) && !is_dir($absolute)) throw new RuntimeException('Nu s-a putut crea folderul media.');
        $name = bin2hex(random_bytes(12)) . '.' . $ext;
        if (!move_uploaded_file($file['tmp_name'], $absolute . '/' . $name)) throw new RuntimeException('Nu s-a putut salva imaginea.');
        return $dir . '/' . $name;
    }
}
