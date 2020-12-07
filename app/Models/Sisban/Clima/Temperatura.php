<?php

namespace App\Models\Sisban\Clima;

use App\Models\BaseModel;

class Temperatura extends BaseModel
{
    protected $connection = 'sbn_pgsql';
    protected $table = 'cli_temperatura';

    static function existe($fecha)
    {
        return self::where(['fecha' => $fecha])->first();
    }
}
