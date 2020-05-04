<?php

namespace App\Models\Bodega;

use Illuminate\Database\Eloquent\Model;

class EgresoBodegaDetalle extends Model
{
    protected $table = 'BOD_DET_EGRESOS';

    public function cabeceraEgreso()
    {
        return $this->hasOne('App\Models\Bodega\EgresoBodega', 'id', 'idegreso');
    }

    public function materialdetalle()
    {
        return $this->hasOne('App\Models\Bodega\Material', 'id', 'idmaterial');
    }

    public $timestamps  = false;
}
