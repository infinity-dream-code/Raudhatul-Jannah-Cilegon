<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SmKantin extends Model
{
    protected $connection = 'DATA_MYSQL';

    protected $table = 'sm_kantin';

    protected $primaryKey = 'urut';

    public $timestamps = false;

    protected $fillable = [
        'KDKANTIN',
        'NamaKantin',
        'KDMERCAN',
        'username',
        'password',
    ];

    protected $hidden = [
        'password',
    ];
}
