<?php

declare(strict_types=1);

function api_config(): array
{
    static $config = null;
    if ($config === null) {
        $path = dirname(__DIR__) . '/config.php';
        if (!is_readable($path)) {
            throw new RuntimeException('api/config.php tidak ditemukan');
        }
        $config = require $path;
    }
    return $config;
}

function api_send_cors(): void
{
    $cors = api_config()['cors_origin'] ?? null;
    if (is_string($cors) && $cors !== '') {
        header('Access-Control-Allow-Origin: ' . $cors);
        header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
        header('Access-Control-Allow-Headers: Content-Type, Accept');
    }
}

function api_json($data, int $status = 200): void
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

function api_read_json_body(): array
{
    $raw = file_get_contents('php://input');
    if (!is_string($raw) || trim($raw) === '') {
        return [];
    }
    $decoded = json_decode($raw, true);
    return is_array($decoded) ? $decoded : [];
}

function api_request_path_after_script(): string
{
    $uri = $_SERVER['REQUEST_URI'] ?? '';
    $script = $_SERVER['SCRIPT_NAME'] ?? '';
    if ($uri === '' || $script === '') {
        return '';
    }
    if (strpos($uri, $script) === 0) {
        $rest = substr($uri, strlen($script));
    } else {
        $rest = parse_url($uri, PHP_URL_PATH) ?? '';
        $base = basename($script);
        $pos = strpos($rest, $base);
        if ($pos !== false) {
            $rest = substr($rest, $pos + strlen($base));
        }
    }
    // Buang query string. Hindari strtok() karena melompati delimiter di awal
    // string (mis. "?id=x" akan salah menjadi "id=x" alih-alih "").
    $qPos = strpos($rest, '?');
    if ($qPos !== false) {
        $rest = substr($rest, 0, $qPos);
    }
    return trim($rest, '/');
}

/** ID resource dari PATH_INFO atau ?id= (kompatibel Apache/cPanel tanpa rewrite). */
function api_request_resource_id(): string
{
    $pathId = api_request_path_after_script();
    if ($pathId !== '') {
        return $pathId;
    }
    $q = $_GET['id'] ?? '';
    return is_string($q) ? trim($q) : '';
}
