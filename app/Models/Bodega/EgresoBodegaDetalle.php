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

    public function debito_transfer()
    {
        return $this->hasOne('App\Models\Bodega\EgresoBodegaDetalle', 'id', 'id_origen');
    }

    public function credito_transfer()
    {
        return $this->hasMany('App\Models\Bodega\EgresoBodegaDetalle', 'id_origen', 'id');
    }

    public $timestamps = false;
}
