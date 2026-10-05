<?php

declare(strict_types=1);

/**
 * API multi account ortu — ortu_akun_anak + ortu_akun_kelompok
 *
 * POST { action, username, ... }
 *   list   → daftar akun terhubung dari username login
 *   save   → simpan akun + hubungkan (linkWithUsername opsional)
 *   sync   → upsert profil tanpa mengubah relasi kelompok
 *   link   → hubungkan username ke kelompok anchor (linkWithUsername)
 *   remove → lepas akun dari kelompok
 */
require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/OrtuMultiAccountService.php';

ortu_api_bootstrap();
ortu_require_post();

$input = ortu_read_json_body();
$action = strtolower(trim((string) ($input['action'] ?? '')));
$username = ortu_norm_username((string) ($input['username'] ?? $input['USERNAME'] ?? ''));

if ($username === '') {
    http_response_code(422);
    echo json_encode(['ok' => false, 'message' => 'Username wajib diisi'], JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    $service = new OrtuMultiAccountService();

    if ($action === 'list') {
        $accounts = $service->getLinkedAccounts($username);
        ortu_send_json_ok(['accounts' => $accounts, 'username' => $username]);
        exit;
    }

    if ($action === 'save') {
        $password = trim((string) ($input['password'] ?? $input['PASSWORD'] ?? ''));
        $profile = is_array($input['profile'] ?? null) ? $input['profile'] : [];
        $linkWith = ortu_norm_username((string) ($input['linkWithUsername'] ?? $input['link_with'] ?? ''));

        if ($password === '') {
            http_response_code(422);
            echo json_encode(['ok' => false, 'message' => 'Password wajib diisi'], JSON_UNESCAPED_UNICODE);
            exit;
        }

        $accounts = $service->saveAndLink(
            $username,
            $password,
            $profile,
            $linkWith !== '' ? $linkWith : null
        );
        ortu_send_json_ok(['accounts' => $accounts, 'username' => $username]);
        exit;
    }

    if ($action === 'sync') {
        $password = trim((string) ($input['password'] ?? $input['PASSWORD'] ?? ''));
        $profile = is_array($input['profile'] ?? null) ? $input['profile'] : [];

        if ($password === '') {
            http_response_code(422);
            echo json_encode(['ok' => false, 'message' => 'Password wajib diisi'], JSON_UNESCAPED_UNICODE);
            exit;
        }

        $accounts = $service->syncAccount($username, $password, $profile);
        ortu_send_json_ok(['accounts' => $accounts, 'username' => $username]);
        exit;
    }

    if ($action === 'link') {
        $anchor = ortu_norm_username((string) ($input['linkWithUsername'] ?? $input['anchorUsername'] ?? ''));
        if ($anchor === '') {
            http_response_code(422);
            echo json_encode(['ok' => false, 'message' => 'Akun anchor (linkWithUsername) wajib diisi'], JSON_UNESCAPED_UNICODE);
            exit;
        }
        $service->attachToKelompok($anchor, $username);
        $accounts = $service->getLinkedAccounts($username);
        ortu_send_json_ok(['accounts' => $accounts, 'username' => $username, 'anchor' => $anchor]);
        exit;
    }

    if ($action === 'remove') {
        $target = ortu_norm_username((string) ($input['targetUsername'] ?? $input['target'] ?? ''));
        if ($target === '') {
            http_response_code(422);
            echo json_encode(['ok' => false, 'message' => 'Akun target wajib diisi'], JSON_UNESCAPED_UNICODE);
            exit;
        }
        $service->deleteSavedAccount($target);
        ortu_send_json_ok([
            'deleted' => $target,
            'message' => 'Akun dihapus. Silakan login kembali.',
        ]);
        exit;
    }

    http_response_code(400);
    echo json_encode(['ok' => false, 'message' => 'Action tidak valid'], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'ok' => false,
        'message' => $e->getMessage(),
    ], JSON_UNESCAPED_UNICODE);
}
