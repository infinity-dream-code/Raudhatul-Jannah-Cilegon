<?php

declare(strict_types=1);

/**
 * Bootstrap API kantin — memakai lib admin (config, JWT, DB).
 */

$adminLib = dirname(__DIR__, 2) . '/admin/api/lib';
require_once $adminLib . '/bootstrap.php';
require_once $adminLib . '/MobileMerchantClient.php';
require_once __DIR__ . '/KantinAuth.php';

function kantin_api_json($data, int $status = 200): void
{
    api_json($data, $status);
}

function kantin_send_headers(): void
{
    api_send_cors();
    header('Content-Type: application/json; charset=utf-8');
}

function kantin_handle_options(): void
{
    if (($_SERVER['REQUEST_METHOD'] ?? '') === 'OPTIONS') {
        http_response_code(204);
        exit;
    }
}
