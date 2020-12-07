<?php

namespace App\Models\Sisban\Clima;

use App\Models\BaseModel;

class Termometro extends BaseModel
{
    protected $connection = 'sbn_pgsql';
    protected $table = 'cli_termometro';

    static function existe($fecha)
    {
        return self::where(['fecha' => $fecha])->first();
    }
}
