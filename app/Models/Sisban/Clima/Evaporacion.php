<?php

namespace App\Models\Sisban\Clima;

use App\Models\BaseModel;

class Evaporacion extends BaseModel
{
    protected $connection = 'sbn_pgsql';
    protected $table = 'cli_evaporacion';

    static function existe($fecha)
    {
        return self::where(['fecha' => $fecha])->first();
    }
}
