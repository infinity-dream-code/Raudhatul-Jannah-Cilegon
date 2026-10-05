<?php

namespace App\Support\FacePay;

use Illuminate\Support\Facades\DB;
use Throwable;

class FaceStudentRepository
{
    public function connectionName(): string
    {
        return (string) config('facepay.connection', 'FACE_MYSQL');
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listWithFoto(): array
    {
        $rows = DB::connection($this->connectionName())
            ->table('siswa')
            ->where('aktif', 1)
            ->whereNotNull('foto_wajah')
            ->whereRaw('CHAR_LENGTH(foto_wajah) > 30')
            ->orderBy('nama')
            ->orderBy('nis')
            ->get(['id', 'nis', 'nisn', 'nama', 'kelas_id', 'jenis_kelamin', 'aktif', 'foto_wajah']);

        $out = [];
        foreach ($rows as $row) {
            $out[] = [
                'id' => (string) ($row->id ?? ''),
                'nis' => (string) ($row->nis ?? ''),
                'nisn' => (string) ($row->nisn ?? ''),
                'nama' => (string) ($row->nama ?? ''),
                'kelasId' => (string) ($row->kelas_id ?? ''),
                'jenisKelamin' => (string) ($row->jenis_kelamin ?? 'L'),
                'aktif' => (bool) ((int) ($row->aktif ?? 1)),
                'fotoWajah' => (string) ($row->foto_wajah ?? ''),
                'hasFoto' => true,
            ];
        }

        return $out;
    }

    public function ping(): bool
    {
        return $this->pingStatus()['ok'];
    }

    /**
     * @return array{ok: bool, error: string|null, host: string, database: string, username: string}
     */
    public function pingStatus(): array
    {
        $conn = $this->connectionName();
        $cfg = config("database.connections.{$conn}", []);
        $meta = [
            'ok' => false,
            'error' => null,
            'host' => (string) ($cfg['host'] ?? ''),
            'database' => (string) ($cfg['database'] ?? ''),
            'username' => (string) ($cfg['username'] ?? ''),
        ];

        try {
            DB::connection($conn)->select('SELECT 1 AS ok');
            // Pastikan tabel referensi wajah ada.
            DB::connection($conn)->table('siswa')->limit(1)->get(['id']);
            $meta['ok'] = true;

            return $meta;
        } catch (Throwable $e) {
            $meta['error'] = $e->getMessage();

            return $meta;
        }
    }
}
