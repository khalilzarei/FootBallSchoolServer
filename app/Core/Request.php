<?php

declare(strict_types=1);

namespace App\Core;

class Request
{
    private array $query;
    private array $post;
    private array $json;

   public function __construct()
    {
        $this->query = $_GET ?? [];
        $this->post = $_POST ?? [];
        
        // اگر $_POST خالی است اما Content-Type multipart است، خودمان parse می‌کنیم
        $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
        if (empty($this->post) && stripos($contentType, 'multipart/form-data') !== false) {
            $this->post = $this->parseMultipartBody();
        }
        
        $this->json = $this->parseJsonBody();
    }

    private function parseJsonBody(): array
    {
        // برای multipart، php://input خالی است
        if (stripos($_SERVER['CONTENT_TYPE'] ?? '', 'multipart/form-data') !== false) {
            return [];
        }
        
        $raw = file_get_contents('php://input');
        if (!$raw) return [];
        $decoded = json_decode($raw, true);
        return is_array($decoded) ? $decoded : [];
    }

 private function parseMultipartBody(): array
    {
        $raw = file_get_contents('php://input');
        if (empty($raw)) return [];

        // استخراج boundary از Content-Type
        $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
        if (!preg_match('/boundary=(?:"([^"]+)"|([^;]+))/i', $contentType, $matches)) {
            return [];
        }
        $boundary = $matches[1] ?: $matches[2];
        $boundary = '--' . $boundary;

        $parts = explode($boundary, $raw);
        $data = [];

        foreach ($parts as $part) {
            if (strpos($part, 'Content-Disposition') === false) continue;
            if (strpos($part, 'filename=') !== false) continue; // فایل‌ها را نادیده بگیر

            // استخراج name
            if (!preg_match('/name="([^"]+)"/', $part, $nameMatch)) continue;
            $name = $nameMatch[1];

            // استخراج value (بعد از دو خط جدید)
            $segments = explode("\r\n\r\n", $part, 2);
            if (count($segments) === 2) {
                $value = trim($segments[1]);
                $data[$name] = $value;
            }
        }

        return $data;
    }

    public function method(): string
    {
        return strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
    }

    public function path(): string
    {
        $uri = $_SERVER['REQUEST_URI'] ?? '/';
        $path = parse_url($uri, PHP_URL_PATH);

        return $path ?: '/';
    }

    public function input(): array
    {
        return array_merge($this->query, $this->post, $this->json);
    }

    public function get(string $key, mixed $default = null): mixed
    {
        $input = $this->input();

        return $input[$key] ?? $default;
    }

    public function header(string $key, ?string $default = null): ?string
    {
        $key = 'HTTP_' . strtoupper(str_replace('-', '_', $key));

        return $_SERVER[$key] ?? $default;
    }

    public function bearerToken(): ?string
    {
        $header = $_SERVER['HTTP_AUTHORIZATION']
            ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION']
            ?? '';

        if (preg_match('/Bearer\s(\S+)/i', $header, $matches)) {
            return $matches[1];
        }

        return null;
    }

    public function ip(): ?string
    {
        return $_SERVER['REMOTE_ADDR'] ?? null;
    }
}