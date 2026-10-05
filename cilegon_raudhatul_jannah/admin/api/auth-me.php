<?php

declare(strict_types=1);

/**
 * Status sesi admin. GET → { ok, data } bila login, atau 401 bila belum.
 */

require_once __DIR__ . '/lib/bootstrap.php';
require_once __DIR__ . '/lib/Database.php';
require_once __DIR__ . '/lib/AdminAuth.php';

AdminAuth::sendCors();
header('Content-Type: application/json; charset=utf-8');

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'OPTIONS') {
    http_response_code(204);
    exit;
}

try {
    $user = AdminAuth::currentUser();
    if (!$user) {
        api_json(['ok' => false, 'error' => 'Belum login'], 401);
    }
    api_json(['ok' => true, 'data' => $user]);
} catch (Throwable $e) {
    api_json(['ok' => false, 'error' => $e->getMessage()], 500);
}
