<?php

namespace App\Models\Bodega;

use App\Models\BaseModel;
use Illuminate\Database\Eloquent\Model;

class EgresoBodega extends BaseModel
{
    protected $table = 'BOD_EGRESOS';

    public function egresoEmpleado()
    {
        return $this->hasOne('App\Models\Hacienda\Empleado', 'id', 'idempleado');
    }

    public function egresoDetalle()
    {
        return $this->hasMany('App\Models\Bodega\EgresoBodegaDetalle', 'idegreso', 'id');
    }
}
