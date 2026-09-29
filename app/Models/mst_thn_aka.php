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
        try {
            Cache::forget(self::LABEL_MAP_CACHE_KEY);
        } catch (\Throwable $e) {
            // ignore
        }
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

    public static function labelFor(?string $thnAka): string
    {
        if ($thnAka === null || $thnAka === "") {
            return "";
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
