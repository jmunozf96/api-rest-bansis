<?php

namespace App\Models\Hacienda;

use App\Models\BaseModel;
use Illuminate\Database\Eloquent\Model;

class LoteSeccionLaborEmpDet extends BaseModel
{
    protected $table = 'HAC_LOTSEC_LABEMPLEADO_DET';

    public function cabSeccionLabor()
    {
        return $this->hasOne('App\Models\Hacienda\LoteSeccionLaborEmp', 'id', 'idcabecera');
    }

    public function seccionLote()
    {
        return $this->hasOne('App\Models\Hacienda\LoteSeccion', 'id', 'idlote_sec');
    }

    public function enfunde()
    {
        return $this->hasMany('App\Models\Hacienda\EnfundeDet', 'idseccion', 'id');
    }
}
