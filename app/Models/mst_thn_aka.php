<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class mst_thn_aka extends Model
{
    protected $connection = "DATA_MYSQL";

    protected $table = "mst_thn_aka";

    protected $primaryKey = "urut";

    public $timestamps = false;

    public $incrementing = false;

    protected $fillable = [
        "thn_aka",
    ];

    protected $appends = [
        'angkatan_label',
    ];

    private const ANGKATAN_MAP_CACHE_KEY = 'mst_thn_aka.angkatan_number_map';

    /** @var array<string, int>|null */
    protected static ?array $angkatanNumberMap = null;

    public static function getMstThnAkaAttributes(): array|object
    {
        return static::select(["thn_aka"])
            ->whereNotNull("thn_aka")
            ->distinct()
            ->orderBy("thn_aka", "desc")
            ->get();
    }

    /**
     * Map each thn_aka value to Angkatan number (oldest year = Angkatan 1).
     *
     * @return array<string, int>
     */
    public static function angkatanNumberMap(): array
    {
        if (static::$angkatanNumberMap !== null) {
            return static::$angkatanNumberMap;
        }

        try {
            static::$angkatanNumberMap = Cache::remember(self::ANGKATAN_MAP_CACHE_KEY, 600, function () {
                $map = [];
                $rows = static::query()
                    ->whereNotNull('thn_aka')
                    ->where('thn_aka', '!=', '')
                    ->orderBy('thn_aka', 'asc')
                    ->pluck('thn_aka')
                    ->unique()
                    ->values();

                $n = 1;
                foreach ($rows as $thn) {
                    $map[(string) $thn] = $n++;
                }

                return $map;
            });
        } catch (\Throwable $e) {
            static::$angkatanNumberMap = [];
        }

        return static::$angkatanNumberMap;
    }

    public static function forgetAngkatanNumberMap(): void
    {
        static::$angkatanNumberMap = null;
        try {
            Cache::forget(self::ANGKATAN_MAP_CACHE_KEY);
        } catch (\Throwable $e) {
            // ignore cache backend failures
        }
    }

    public static function labelFor(?string $thnAka): string
    {
        if ($thnAka === null || $thnAka === '') {
            return '';
        }

        $n = static::angkatanNumberMap()[$thnAka] ?? null;

        return $n ? "Angkatan {$n}" : $thnAka;
    }

    public function getAngkatanLabelAttribute(): string
    {
        return static::labelFor($this->thn_aka !== null ? (string) $this->thn_aka : null);
    }
}
