<?php

namespace App\Core;

final class Response
{
    public static function redirect(string $url, int $status = 302): never
    {
        header('Location: ' . $url, true, $status);
        exit;
    }

    public static function json(array $data, int $status = 200): never
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    public static function xml(string $content): never
    {
        header('Content-Type: application/xml; charset=utf-8');
        echo $content;
        exit;
    }

    public static function text(string $content, string $contentType = 'text/plain'): never
    {
        header('Content-Type: ' . $contentType . '; charset=utf-8');
        header('Cache-Control: public, max-age=3600');
        echo $content;
        exit;
    }
}
