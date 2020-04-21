<?php

namespace App\Models\XassInventario\Primo;

use Illuminate\Database\Eloquent\Model;

class Grupo extends Model
{
    protected $connection = "PRIMO";
    protected $table = "SGI_Inv_Grupos";
    protected $primaryKey = "Id_Fila";

    public function productos()
    {
        $this->hasMany('App\Models\XassInventario\Primo\Producto');
    }
}
