<?php

namespace App\Models\Bodega;

use Illuminate\Database\Eloquent\Model;

class EgresoBodegaDetalle extends Model
{
    protected $table = 'BOD_DET_EGRESOS';

    protected $hidden = ['idegreso'];

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

    public static function existe(EgresoBodegaDetalle $detalle)
    {
        return self::where([
            'idegreso' => $detalle->idegreso,
            'idmaterial' => $detalle->idmaterial,
            'movimiento' => $detalle->movimiento,
            'fecha_salida' => $detalle->fecha_salida,
            'estado' => $detalle->estado
        ])->first();
    }

    public static function existeById($id)
    {
        return self::where([
            'id' => $id
        ])->first();
    }

    public static function rowsItems($idEgreso)
    {
        //Funcion para saber si le quedan detalles
        return count(self::where(['idegreso' => $idEgreso])->get()->all()) > 0;
    }

    public $timestamps = false;
}
