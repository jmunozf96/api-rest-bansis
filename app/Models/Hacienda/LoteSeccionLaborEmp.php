<?php

namespace App\Models\Hacienda;

use App\Models\BaseModel;
use Illuminate\Database\Eloquent\Model;

class LoteSeccionLaborEmp extends BaseModel
{
    protected $table = 'HAC_LOTSEC_LABEMPLEADO';

    public function labor()
    {
        return $this->hasOne(Labor::class, 'id', 'idlabor');
    }

    public function empleado()
    {
        return $this->hasOne(Empleado::class, 'id', 'idempleado');
    }

    public function detalleSeccionLabor()
    {
        return $this->hasMany(LoteSeccionLaborEmpDet::class, 'idcabecera', 'id');
    }
}
