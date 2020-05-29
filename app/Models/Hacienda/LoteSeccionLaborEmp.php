<?php

namespace App\Models\Hacienda;

use App\Models\BaseModel;
use Illuminate\Database\Eloquent\Model;

class LoteSeccionLaborEmp extends BaseModel
{
    protected $table = 'HAC_LOTSEC_LABEMPLEADO';

    public function labor()
    {
        return $this->hasOne('App\Models\Hacienda\Labor', 'id', 'idlabor');
    }

    public function empleado()
    {
        return $this->hasOne('App\Models\Hacienda\Empleado', 'id', 'idempleado');
    }

    public function detalleSeccionLabor()
    {
        return $this->hasMany('App\Models\Hacienda\LoteSeccionLaborEmpDet', 'idcabecera', 'id');
    }
}
