<?php

namespace App\Models\XassInventario\Sofca;

use App\Models\BaseModel;
use Illuminate\Database\Eloquent\Model;

class Bodega extends BaseModel
{
    protected $connection = "SOFCA";
    protected $table = "SGI_Inv_Bodegas";
    protected $primaryKey = "Id_Fila";

    public function productos()
    {
        $this->hasMany('App\Models\XassInventario\Sofca\Producto');
    }
}
