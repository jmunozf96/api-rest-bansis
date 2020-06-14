<?php

namespace App\Models\Hacienda;

use App\Models\BaseModel;
use Illuminate\Database\Eloquent\Model;

class Empleado extends BaseModel
{
    protected $table = 'HAC_EMPLEADOS';

    public function hacienda()
    {
        return $this->hasOne('App\Models\Hacienda\Hacienda', 'id', 'idhacienda');
    }

    public function labor()
    {
        return $this->hasOne('App\Models\Hacienda\Labor', 'id', 'idlabor');
    }

    public function inventario()
    {
        return $this->hasMany('App\Models\Hacienda\InventarioEmpleado', 'idempleado', 'id');
    }

    public function user()
    {
        return $this->hasOne('App\User', 'idempleado', 'id');
    }
}
