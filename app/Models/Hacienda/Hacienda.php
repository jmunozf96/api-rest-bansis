<?php

namespace App\Models\Hacienda;

use App\Models\BaseModel;
use Illuminate\Database\Eloquent\Model;

class Hacienda extends BaseModel
{
    protected $table = 'HACIENDAS';

    public static function getHaciendas()
    {
        return self::select('id', 'detalle')->get();
    }
}
