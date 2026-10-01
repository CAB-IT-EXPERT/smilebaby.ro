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
        $info = @getimagesize($file['tmp_name']);
        if (!$info || empty($info[0]) || empty($info[1])) throw new RuntimeException('Fișierul nu conține o imagine validă.');
        if ((int) $info[0] * (int) $info[1] > 50_000_000) throw new RuntimeException('Imaginea are o rezoluție prea mare. Redimensioneaz-o înainte de încărcare.');
        $dir = 'uploads/' . trim($folder, '/') . '/' . date('Y/m');
        $absolute = BASE_PATH . '/' . $dir;
        if (!is_dir($absolute) && !mkdir($absolute, 0775, true) && !is_dir($absolute)) throw new RuntimeException('Nu s-a putut crea folderul media.');
        $name = bin2hex(random_bytes(12)) . '.' . $ext;
        $stored = $absolute . '/' . $name;
        if (!move_uploaded_file($file['tmp_name'], $stored)) throw new RuntimeException('Nu s-a putut salva imaginea.');
        $base = substr($stored, 0, -(strlen($ext) + 1));
        $this->createWebpVariant($stored, $base . '.display.webp', 1600, 82);
        $this->createWebpVariant($stored, $base . '.card.webp', 720, 78);
        return $dir . '/' . $name;
    }

    private function createWebpVariant(string $source, string $destination, int $maxDimension, int $quality): void
    {
        if (!function_exists('imagecreatefromstring') || !function_exists('imagewebp')) return;
        $binary = @file_get_contents($source);
        if ($binary === false) return;
        $image = @imagecreatefromstring($binary);
        if (!$image) return;

        $width = imagesx($image);
        $height = imagesy($image);
        if ($width < 1 || $height < 1) { imagedestroy($image); return; }
        $scale = min(1, $maxDimension / max($width, $height));
        $targetWidth = max(1, (int) round($width * $scale));
        $targetHeight = max(1, (int) round($height * $scale));
        $canvas = imagecreatetruecolor($targetWidth, $targetHeight);
        if (!$canvas) { imagedestroy($image); return; }
        imagealphablending($canvas, false);
        imagesavealpha($canvas, true);
        $transparent = imagecolorallocatealpha($canvas, 0, 0, 0, 127);
        imagefill($canvas, 0, 0, $transparent);
        imagecopyresampled($canvas, $image, 0, 0, 0, 0, $targetWidth, $targetHeight, $width, $height);
        @imagewebp($canvas, $destination, $quality);
        imagedestroy($canvas);
        imagedestroy($image);
    }
}
