<?php

namespace App\Models\Hacienda;

use App\Models\BaseModel;

class InventarioEmpleado extends BaseModel
{
    protected $table = 'HAC_INVENTARIO_EMPLEADO';

    public function material()
    {
        return $this->hasOne('App\Models\Bodega\Material', 'id', 'idmaterial');
    }

    public function empleado(){
        return $this->hasOne(Empleado::class, 'id', 'idempleado');
    }

    public function saldoFinal()
    {
        $this->sld_final = ((+$this->sld_inicial + +$this->tot_egreso) - +$this->tot_consumo);
    }

    public static function existeInventario(InventarioEmpleado $inventario)
    {
        $inventario = self::where([
            'idcalendar' => $inventario->idcalendar,
            'idempleado' => $inventario->idempleado,
            'idmaterial' => $inventario->idmaterial
        ])->first();

        return $inventario;
    }
}
