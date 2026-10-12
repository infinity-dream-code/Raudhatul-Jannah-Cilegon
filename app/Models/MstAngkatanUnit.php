<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MstAngkatanUnit extends Model
{
    protected $connection = 'DATA_MYSQL';

    protected $table = 'mst_angkatan_unit';

    protected $primaryKey = 'urut';

    public $timestamps = false;

    protected $fillable = [
        'CODE01',
        'thn_aka_mulai',
        'updated_by',
        'updated_at',
    ];
}
