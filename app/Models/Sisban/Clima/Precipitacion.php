<?php

namespace App\Models\Sisban\Clima;

use App\Models\BaseModel;

class Precipitacion extends BaseModel
{
    protected $connection = 'sbn_pgsql';
    protected $table = 'cli_precipitacion';

    static function existe($fecha, $hacienda)
    {
        return self::where(['fecha' => $fecha, 'idhacienda' => $hacienda])->first();
    }
}
