<?php

namespace App\Models\Hacienda;

use App\Models\BaseModel;
use Illuminate\Database\Eloquent\Model;

class LoteSeccion extends BaseModel
{
    protected $table = 'HAC_LOTES_SECCION';

    public function lote()
    {
        return $this->hasOne('App\Models\Hacienda\Lote', 'id', 'idlote');
    }
}
