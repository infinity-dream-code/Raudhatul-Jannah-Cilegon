<?php

namespace App\Support;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class AndroidMerchantProcedure
{
    /**
     * Tambah merchant/kantin via function AndroidAddMerchant.
     * Return: OK | DENIED
     */
    public static function add(string $namaKantin, string $username): string
    {
        $namaKantin = trim($namaKantin);
        $username = trim($username);

        if ($namaKantin === '' || $username === '') {
            throw new \InvalidArgumentException('Nama kantin dan username wajib diisi.');
        }

        $rows = DB::connection('DATA_MYSQL')->select(
            'SELECT AndroidAddMerchant(?, ?) AS result',
            [$namaKantin, $username]
        );

        $result = strtoupper(trim((string) ($rows[0]->result ?? '')));

        Log::info('android-merchant.add', [
            'nama_kantin' => $namaKantin,
            'username' => $username,
            'result' => $result,
        ]);

        return $result;
    }

    /**
     * Reset password merchant via procedure AndroidResetPassMerchant.
     * Password direset ke MD5('123').
     */
    public static function resetPassword(string $username, ?string $adminUser = null, ?string $machineName = null): void
    {
        $username = trim($username);
        if ($username === '') {
            throw new \InvalidArgumentException('Username kantin tidak valid.');
        }

        $adminUser = trim((string) ($adminUser ?? Auth::user()?->users ?? Auth::user()?->name ?? 'system'));
        $machineName = trim((string) ($machineName ?? gethostname() ?: 'web'));

        DB::connection('DATA_MYSQL')->statement(
            'CALL AndroidResetPassMerchant(?, ?, ?)',
            [$username, $adminUser !== '' ? $adminUser : 'system', $machineName !== '' ? $machineName : 'web']
        );

        Log::info('android-merchant.reset-password', [
            'username' => $username,
            'by' => $adminUser,
        ]);
    }

    public static function assertAddOk(string $result): void
    {
        if (strtoupper(trim($result)) !== 'OK') {
            throw new RuntimeException(
                'Username sudah terpakai di kantin atau cyber_key. Gunakan username lain.'
            );
        }
    }
}
