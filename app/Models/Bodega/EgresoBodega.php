<?php

namespace App\Models\Bodega;

use App\Models\BaseModel;
use App\Models\Sistema\Calendario;
use Illuminate\Database\Eloquent\Model;

class EgresoBodega extends BaseModel
{
    protected $table = 'BOD_EGRESOS';

    public function egresoEmpleado()
    {
        return $this->hasOne('App\Models\Hacienda\Empleado', 'id', 'idempleado');
    }

    public function egresoDetalle()
    {
        return $this->hasMany('App\Models\Bodega\EgresoBodegaDetalle', 'idegreso', 'id');
    }

    public static function existe(EgresoBodega $egreso)
    {
        $item = self::where([
            'fecha_apertura' => $egreso->fecha_apertura,
            'idempleado' => $egreso->idempleado,
            'parcial' => $egreso->parcial,
            'final' => $egreso->final,
            'estado' => $egreso->estado
        ])->first();

        return is_object($item);
    }

    public static function existeById($id)
    {
        return self::where([
            'id' => $id,
        ])->first();
    }

    public static function existeByEmpleado($idemp, $fecha)
    {
        $timestamp = strtotime(str_replace('/', '-', $fecha));
        $fecha = date(config('constants.date'), $timestamp);

        $calendario = Calendario::getCalendario($fecha);

        return self::from('BOD_EGRESOS as egreso')->select('egreso.id')
            ->join('SIS_CALENDARIO_DOLE AS calendario', 'calendario.fecha', 'egreso.fecha_apertura')
            ->where([
                'idempleado' => $idemp,
                'calendario.semana' => $calendario->semana
            ])
            ->first();
    }
}
