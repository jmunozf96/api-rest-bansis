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
}
