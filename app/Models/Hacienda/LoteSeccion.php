<?php

namespace App\Models\Hacienda;

use App\Models\BaseModel;
use App\Models\Sistema\Calendario;
use App\Models\Sistema\ManosRecusadas;
use Illuminate\Database\Eloquent\Model;

class LoteSeccion extends BaseModel
{
    protected $table = 'HAC_LOTES_SECCION';

    public function lote()
    {
        return $this->hasOne(Lote::class, 'id', 'idlote');
    }

    //Cosecha-ManosRecusadas
    public function manosRecusadas()
    {
        return $this->hasMany(ManosRecusadas::class, 'idlote','id');
    }

}
