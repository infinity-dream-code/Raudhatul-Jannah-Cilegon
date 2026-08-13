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
        'aktif',
        'kelompok_kantin',
    ];

    protected $casts = [
        'batas_belanja_hari' => 'float',
        'batas_cash' => 'float',
        'aktif' => 'integer',
    ];
}
