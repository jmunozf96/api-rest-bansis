<?php

namespace App\Models\XassInventario\Primo;

use App\Models\BaseModel;
use Illuminate\Database\Eloquent\Model;

class Bodega extends BaseModel
{
    protected $connection = "PRIMO";
    protected $table = "SGI_Inv_Bodegas";
    protected $primaryKey = "Id_Fila";

    public function productos()
    {
        $this->hasMany('App\Models\XassInventario\Primo\Producto');
    }
}
