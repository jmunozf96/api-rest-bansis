<?php

namespace App\Models\XassInventario\Sofca;

use Illuminate\Database\Eloquent\Model;

class Grupo extends Model
{
    protected $connection = "SOFCA";
    protected $table = "SGI_Inv_Grupos";
    protected $primaryKey = "Id_Fila";

    public function productos()
    {
        $this->hasMany('App\Models\XassInventario\Sofca\Producto');
    }
}
