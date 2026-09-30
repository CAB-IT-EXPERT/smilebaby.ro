<?php

use App\Core\Auth;
use App\Core\Config;
use App\Core\Csrf;
use App\Core\Session;

function config(string $key, mixed $default = null): mixed { return Config::get($key, $default); }
function e(mixed $value): string { return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
function csrf_field(): string { return '<input type="hidden" name="_token" value="' . e(Csrf::token()) . '">'; }
function user(): ?array { return Auth::user(); }
function old(string $key, string $default = ''): string { return e(Session::get('_old', [])[$key] ?? $default); }
function money(float|int|string $value): string { return number_format((float) $value, 2, ',', '.') . ' lei'; }
function asset(string $path): string {
    $relative = ltrim($path, '/');
    $url = '/assets/' . $relative;
    $file = defined('BASE_PATH') ? BASE_PATH . '/assets/' . str_replace('/', DIRECTORY_SEPARATOR, $relative) : null;
    if (!$file || !is_file($file)) return $url;
    $version = filemtime($file);
    if ($relative === 'css/app.css') {
        foreach (glob(BASE_PATH . '/assets/css/*.css') ?: [] as $cssFile) $version = max($version, filemtime($cssFile));
    }
    return $url . '?v=' . $version;
}
function upload_url(?string $path): string {
    if (!$path) return asset('images/placeholder.svg');
    $path = str_replace('\\', '/', trim($path));
    if (preg_match('#^https?://#i', $path)) return $path;
    if (str_starts_with($path, '//')) return 'https:' . $path;
    return '/' . ltrim($path, '/');
}
function setting(string $key, mixed $default = null): mixed {
    static $cache = [];
    if (array_key_exists($key, $cache)) return $cache[$key];
    if (!App\Core\Database::available()) return $default;
    $stmt = App\Core\Database::connection()->prepare('SELECT value FROM settings WHERE `key` = ? LIMIT 1');
    $stmt->execute([$key]);
    $row = $stmt->fetch();
    return $cache[$key] = $row ? $row['value'] : $default;
}
function slugify(string $value): string {
    $value = strtr($value, ['ă'=>'a','â'=>'a','î'=>'i','ș'=>'s','ş'=>'s','ț'=>'t','ţ'=>'t','Ă'=>'A','Â'=>'A','Î'=>'I','Ș'=>'S','Ş'=>'S','Ț'=>'T','Ţ'=>'T']);
    $value = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value) ?: $value;
    $value = preg_replace('/[^a-zA-Z0-9]+/', '-', $value);
    return trim(mb_strtolower((string) $value), '-');
}
function icon(string $name): string {
    $paths = [
        'menu' => '<path d="M4 7h16M4 12h16M4 17h16"/>',
        'dashboard' => '<rect x="3" y="3" width="7" height="7" rx="1.5"/><rect x="14" y="3" width="7" height="7" rx="1.5"/><rect x="3" y="14" width="7" height="7" rx="1.5"/><rect x="14" y="14" width="7" height="7" rx="1.5"/>',
        'close' => '<path d="m6 6 12 12M18 6 6 18"/>',
        'search' => '<circle cx="11" cy="11" r="6"/><path d="m16 16 4 4"/>',
        'user' => '<circle cx="12" cy="8" r="4"/><path d="M4.5 21a7.5 7.5 0 0 1 15 0"/>',
        'heart' => '<path d="M20.8 4.6a5.5 5.5 0 0 0-7.8 0L12 5.7l-1.1-1.1a5.5 5.5 0 0 0-7.8 7.8l1.1 1.1L12 21l7.8-7.5 1.1-1.1a5.5 5.5 0 0 0-.1-7.8Z"/>',
        'bag' => '<path d="M6 8h12l1 13H5L6 8Z"/><path d="M9 9V6a3 3 0 0 1 6 0v3"/>',
        'truck' => '<path d="M3 6h11v10H3zM14 10h4l3 3v3h-7z"/><circle cx="7" cy="18" r="2"/><circle cx="18" cy="18" r="2"/>',
        'card' => '<rect x="3" y="5" width="18" height="14" rx="2"/><path d="M3 10h18"/>',
        'headset' => '<path d="M4 14v-2a8 8 0 0 1 16 0v2M4 14h3v6H5a1 1 0 0 1-1-1zM20 14h-3v6h2a1 1 0 0 0 1-1z"/>',
        'phone' => '<path d="M7.2 3.5 4.8 5.2c-.8.6-.9 1.7-.5 2.6 2.3 5.3 6.6 9.6 11.9 11.9.9.4 2 .3 2.6-.5l1.7-2.4-4.4-3-1.8 1.8a15.5 15.5 0 0 1-5.9-5.9l1.8-1.8-3-4.4Z"/>',
        'mail' => '<rect x="3" y="5" width="18" height="14" rx="2"/><path d="m4 7 8 6 8-6"/>',
        'pin' => '<path d="M20 10c0 5-8 11-8 11S4 15 4 10a8 8 0 1 1 16 0Z"/><circle cx="12" cy="10" r="2.5"/>',
        'clock' => '<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/>',
        'facebook' => '<path d="M14 21v-8h3l.5-4H14V7c0-1.2.4-2 2.3-2H18V1.5c-.7-.1-1.8-.2-3-.2-3.1 0-5 1.9-5 5.3V9H7v4h3v8"/>',
        'instagram' => '<rect x="3" y="3" width="18" height="18" rx="5"/><circle cx="12" cy="12" r="4"/><circle cx="17.4" cy="6.6" r=".7" fill="currentColor" stroke="none"/>',
        'tiktok' => '<path d="M14 3v11.2a4.3 4.3 0 1 1-3.7-4.3M14 3c.5 3 2.2 4.7 5 5"/>',
        'whatsapp' => '<path fill="currentColor" stroke="none" d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 0 1-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 0 1-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 0 1 2.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0 0 12.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 0 0 5.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 0 0-3.48-8.413Z"/>',
        'gift' => '<rect x="3" y="9" width="18" height="12" rx="1"/><path d="M12 9v12M3 13h18M12 9H8a2 2 0 1 1 2-2c0 1.1 2 2 2 2Zm0 0h4a2 2 0 1 0-2-2c0 1.1-2 2-2 2Z"/>',
        'media' => '<rect x="3" y="4" width="18" height="16" rx="2"/><circle cx="8.5" cy="9" r="1.5"/><path d="m4 17 5-5 3.5 3.5 2.5-2.5 5 4"/>',
        'leaf' => '<path d="M20 4C10 4 5 9 5 16c6 1 13-3 15-12Z"/><path d="M4 21c3-7 8-10 13-13"/>',
        'return' => '<path d="M9 7H5v-4M5 7a8 8 0 1 1-1 8"/>',
        'arrow' => '<path d="M5 12h14M14 7l5 5-5 5"/>',
        'chevron' => '<path d="m9 18 6-6-6-6"/>',
        'check' => '<path d="m5 12 4 4L19 6"/>',
        'minus' => '<path d="M5 12h14"/>', 'plus' => '<path d="M5 12h14M12 5v14"/>',
    ];
    return '<svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round">' . ($paths[$name] ?? '') . '</svg>';
}
