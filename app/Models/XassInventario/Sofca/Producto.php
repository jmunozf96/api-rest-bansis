<?php

namespace App\Models\XassInventario\Sofca;

use App\Models\BaseModel;
use Illuminate\Database\Eloquent\Model;

class Producto extends BaseModel
{
    protected $connection = "SOFCA";
    protected $table = "SGI_Inv_Productos";
    protected $primaryKey = "id_fila";

    public function grupo()
    {
        return $this->hasOne('App\Models\XassInventario\Sofca\Grupo', 'Codigo', 'grupo');
    }

    public function bodega()
    {
        return $this->hasOne('App\Models\XassInventario\Sofca\Bodega', 'Id_Fila', 'bodegacompra');
    }
}
