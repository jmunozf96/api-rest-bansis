<?php

namespace App\Models\Bodega;

use App\Models\BaseModel;
use Illuminate\Database\Eloquent\Model;

class BOD_EGRESO extends BaseModel
{
    protected $table = 'BOD_EGRESOS';

    protected $hidden = [
        'id'
    ];

    public function detalle_egreso()
    {
        $this->hasMany('App\Models\Bodega\BOD_DET_EGRESO', 'idegreso', 'id');
    }

    public function empleado()
    {
        $this->hasOne('App\Models\Hacienda\HAC_EMPLEADO', 'idempleado');
    }
}
