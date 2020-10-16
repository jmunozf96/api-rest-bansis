<?php

namespace App\Models\Sistema;

use App\Models\BaseModel;
use Illuminate\Database\Eloquent\Model;

class Calendario extends BaseModel
{
    protected $table = 'SIS_CALENDARIO_DOLE';

    protected $hidden = ['id'];

    public static function getCalendario($fecha){
        return Calendario::where('fecha', $fecha)->first();
    }
}
