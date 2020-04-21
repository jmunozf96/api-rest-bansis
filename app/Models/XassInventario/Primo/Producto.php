<?php

namespace App\Models\XassInventario\Primo;

use App\Models\BaseModel;
use Illuminate\Database\Eloquent\Model;

class Producto extends BaseModel
{
    protected $connection = "PRIMO";
    protected $table = "SGI_Inv_Productos";
    protected $primaryKey = "id_fila";

    public function grupo()
    {
        return $this->hasOne('App\Models\XassInventario\Primo\Grupo', 'Codigo', 'grupo');
    }

    public function bodega()
    {
        return $this->hasOne('App\Models\XassInventario\Primo\Bodega', 'Id_Fila', 'bodegacompra');
    }
}
