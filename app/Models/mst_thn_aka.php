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
        "urut",
        "thn_aka",
        "angkatan",
    ];

    protected $appends = [
        "angkatan_label",
    ];

    private const LABEL_MAP_CACHE_KEY = "mst_thn_aka.thn_aka_to_angkatan";

    /** @var array<string, string>|null thn_aka => angkatan number */
    protected static ?array $thnAkaToAngkatanMap = null;

    public static function getMstThnAkaAttributes(): array|object
    {
        return static::select(["thn_aka", "angkatan"])
            ->whereNotNull("thn_aka")
            ->distinct()
            ->orderBy("thn_aka", "desc")
            ->get();
    }

    /**
     * @return array<string, string>
     */
    public static function thnAkaToAngkatanMap(): array
    {
        if (static::$thnAkaToAngkatanMap !== null) {
            return static::$thnAkaToAngkatanMap;
        }

        try {
            static::$thnAkaToAngkatanMap = Cache::remember(self::LABEL_MAP_CACHE_KEY, 600, function () {
                return static::query()
                    ->whereNotNull("thn_aka")
                    ->where("thn_aka", "!=", "")
                    ->get(["thn_aka", "angkatan"])
                    ->mapWithKeys(function ($row) {
                        $no = trim((string) ($row->angkatan ?? ""));

                        return [(string) $row->thn_aka => $no];
                    })
                    ->all();
            });
        } catch (\Throwable $e) {
            static::$thnAkaToAngkatanMap = [];
        }

        return static::$thnAkaToAngkatanMap;
    }

    public static function forgetAngkatanNumberMap(): void
    {
        static::$thnAkaToAngkatanMap = null;
        static::$defaultStartYear = null;
        try {
            Cache::forget(self::LABEL_MAP_CACHE_KEY);
            Cache::forget(self::DEFAULT_START_CACHE_KEY);
        } catch (\Throwable $e) {
            // ignore
        }
    }

    private const UNIT_START_CACHE_KEY = "mst_angkatan_unit.start_year_map";

    private const DEFAULT_START_CACHE_KEY = "mst_thn_aka.default_start_year";

    /** @var array<string, int>|null CODE01 => tahun awal angkatan 1 */
    protected static ?array $unitStartYearMap = null;

    protected static ?int $defaultStartYear = null;

    public static function startYearOf(?string $thnAka): ?int
    {
        if ($thnAka === null) {
            return null;
        }

        return preg_match('/^\s*(\d{4})/', $thnAka, $m) ? (int) $m[1] : null;
    }

    /**
     * Tahun awal dari tahun akademik paling awal di mst_thn_aka.
     */
    public static function defaultStartYear(): ?int
    {
        if (static::$defaultStartYear !== null) {
            return static::$defaultStartYear;
        }

        try {
            $year = Cache::remember(self::DEFAULT_START_CACHE_KEY, 600, function () {
                $min = static::query()
                    ->whereNotNull("thn_aka")
                    ->where("thn_aka", "!=", "")
                    ->selectRaw("MIN(CAST(LEFT(TRIM(thn_aka), 4) AS UNSIGNED)) AS min_year")
                    ->value("min_year");

                return $min ? (int) $min : 0;
            });
        } catch (\Throwable $e) {
            $year = 0;
        }

        static::$defaultStartYear = $year > 0 ? (int) $year : null;

        return static::$defaultStartYear;
    }

    /**
     * @return array<string, int>
     */
    public static function unitStartYearMap(): array
    {
        if (static::$unitStartYearMap !== null) {
            return static::$unitStartYearMap;
        }

        try {
            static::$unitStartYearMap = Cache::remember(self::UNIT_START_CACHE_KEY, 600, function () {
                return MstAngkatanUnit::query()
                    ->get(["CODE01", "thn_aka_mulai"])
                    ->mapWithKeys(function ($row) {
                        $year = static::startYearOf((string) $row->thn_aka_mulai);

                        return $year ? [trim((string) $row->CODE01) => $year] : [];
                    })
                    ->all();
            });
        } catch (\Throwable $e) {
            static::$unitStartYearMap = [];
        }

        return static::$unitStartYearMap;
    }

    public static function forgetUnitStartYearMap(): void
    {
        static::$unitStartYearMap = null;
        try {
            Cache::forget(self::UNIT_START_CACHE_KEY);
        } catch (\Throwable $e) {
            // ignore
        }
    }

    public static function unitStartYear(?string $code01): ?int
    {
        $code01 = trim((string) $code01);
        if ($code01 !== "" && isset(static::unitStartYearMap()[$code01])) {
            return static::unitStartYearMap()[$code01];
        }

        return static::defaultStartYear();
    }

    public static function angkatanNumberFor(?string $thnAka, ?string $code01): ?int
    {
        $year = static::startYearOf($thnAka);
        $start = static::unitStartYear($code01);
        if ($year === null || $start === null) {
            return null;
        }

        $no = $year - $start + 1;

        return $no >= 1 ? $no : null;
    }

    /**
     * @return array{defaultStart: int|null, units: array<string, int>}
     */
    public static function angkatanConfig(): array
    {
        return [
            "defaultStart" => static::defaultStartYear(),
            "units" => (object) static::unitStartYearMap(),
        ];
    }

    public static function nextAngkatanNumber(): int
    {
        $max = static::query()
            ->selectRaw("MAX(CAST(angkatan AS UNSIGNED)) as max_angkatan")
            ->value("max_angkatan");

        return ((int) $max) + 1;
    }

    public static function nextUrut(): int
    {
        return ((int) static::query()->max("urut")) + 1;
    }

    /**
     * Tanpa $code01: nomor angkatan global (mst_thn_aka.angkatan).
     * Dengan $code01: dihitung dari tahun mulai unit (mst_angkatan_unit).
     */
    public static function labelFor(?string $thnAka, ?string $code01 = null): string
    {
        if ($thnAka === null || $thnAka === "") {
            return "";
        }

        if ($code01 !== null && trim($code01) !== "") {
            $unitNo = static::angkatanNumberFor($thnAka, $code01);

            return $unitNo !== null ? "Angkatan {$unitNo}" : $thnAka;
        }

        $no = static::thnAkaToAngkatanMap()[$thnAka] ?? "";

        return $no !== "" ? "Angkatan {$no}" : $thnAka;
    }

    public function getAngkatanLabelAttribute(): string
    {
        $no = trim((string) ($this->attributes["angkatan"] ?? ""));

        return $no !== "" ? "Angkatan {$no}" : (string) ($this->thn_aka ?? "");
    }
}
