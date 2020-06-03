<?php

namespace App\Models\Hacienda;

use App\Models\BaseModel;
use Illuminate\Database\Eloquent\Model;

class EnfundeDet extends BaseModel
{
    protected $table = 'HAC_DET_ENFUNDES';

    public function enfunde()
    {
        return $this->hasOne('App\Models\Hacienda\Enfunde', 'id', 'idenfunde');
    }

    public function material()
    {
        return $this->hasOne('App\Models\Bodega\Material', 'id', 'idmaterial');
    }

    public function seccion()
    {
        return $this->hasOne('App\Models\Hacienda\LoteSeccionLaborEmpDet', 'id', 'idseccion');
    }

    public function reelevo()
    {
        return $this->hasOne('App\Models\Hacienda\Empleado', 'id', 'idempleado');
    }
}
