<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SmBatasan extends Model
{
    protected $connection = 'DATA_MYSQL';

    protected $table = 'sm_batasan';

    protected $primaryKey = 'urut';

    public $timestamps = false;

    protected $fillable = [
        'periode',
        'batas_belanja_hari',
        'batas_cash',
        'batasan_utang',
        'aktif',
        'kelompok_kantin',
    ];

    protected $casts = [
        'batas_belanja_hari' => 'float',
        'batas_cash' => 'float',
        'batasan_utang' => 'float',
        'aktif' => 'integer',
    ];

    public static function activeBatasanUtang(): int
    {
        try {
            return (int) (static::query()->where('aktif', 1)->orderByDesc('urut')->value('batasan_utang') ?? 0);
        } catch (\Throwable) {
            return 0;
        }
    }

    public static function activeLimitHarian(): int
    {
        try {
            return (int) (static::query()->where('aktif', 1)->orderByDesc('urut')->value('batas_belanja_hari') ?? 0);
        } catch (\Throwable) {
            return 0;
        }
    }

    /**
     * Sama dengan aturan VPSPaymentBUY2: limit harian hanya menghitung belanja hari ini
     * yang dibayar dari saldo (METODE selain 'UTANG'). limit_harian 0 = tanpa limit.
     *
     * @return array{limit_harian: int, terpakai_hari_ini: int, sisa_limit_harian: int|null, batasan_utang: int}
     */
    public static function limitInfoFor(string|int $custId): array
    {
        $limitHarian = static::activeLimitHarian();

        $terpakai = 0;
        try {
            $terpakai = (int) \Illuminate\Support\Facades\DB::connection('DATA_MYSQL')
                ->table('sccttran_cashless')
                ->where('CUSTID', $custId)
                ->where('FIDBANK', 'BUY')
                ->whereRaw("UPPER(TRIM(COALESCE(METODE, ''))) <> 'UTANG'")
                ->where('TRXDATE', '>=', now()->startOfDay())
                ->where('TRXDATE', '<', now()->startOfDay()->addDay())
                ->sum('DEBET');
        } catch (\Throwable) {
            $terpakai = 0;
        }

        return [
            'limit_harian' => $limitHarian,
            'terpakai_hari_ini' => $terpakai,
            'sisa_limit_harian' => $limitHarian > 0 ? max(0, $limitHarian - $terpakai) : null,
            'batasan_utang' => static::activeBatasanUtang(),
        ];
    }
}
